<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\User;
use App\Models\WarehouseInventory;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['franchise', 'items.product.category', 'orderedByUser'])
            ->withCount(['acceptedPayments as payment_accepted_count']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('delivery_status')) {
            $query->where('delivery_status', $request->delivery_status);
        }

        if ($request->has('franchise_id')) {
            $query->where('franchise_id', $request->franchise_id);
        }

        $orders = $query->latest()->paginate(20);

        return response()->json($orders);
    }

    public function show(Order $order): JsonResponse
    {
        $order->loadCount(['acceptedPayments as payment_accepted_count']);
        $order->load([
            'franchise',
            'items.product.category',
            'orderedByUser',
            'stockReceipt',
            'financeVerifiedByUser:id,name',
            'approvedByUser:id,name',
            'payments' => fn ($q) => $q
                ->with(['franchise:id,name', 'verifier:id,name', 'acceptor:id,name'])
                ->latest('submitted_at'),
        ]);

        return response()->json(['data' => $order]);
    }

    public function approve(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'expected_delivery_date' => 'required|date|after:now',
            'notes' => 'nullable|string',
        ]);

        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Order is not pending.'], 422);
        }

        foreach ($order->items as $item) {
            $warehouse = WarehouseInventory::where('product_id', $item->product_id)->first();
            if (! $warehouse || $warehouse->quantity < $item->quantity) {
                return response()->json([
                    'message' => "Insufficient warehouse stock for {$item->product->name}.",
                ], 422);
            }
        }

        $result = DB::transaction(function () use ($request, $order) {
            foreach ($order->items as $item) {
                $warehouse = WarehouseInventory::where('product_id', $item->product_id)->first();
                $warehouse->quantity -= $item->quantity;
                $warehouse->save();

                $item->update(['status' => 'approved']);

                StockMovement::log('warehouse_out', $item->product_id, -$item->quantity, $item->unit_price, Order::class, $order->id, "Order {$order->order_number} approved", $request->user()->id);
            }

            $order->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'expected_delivery_date' => $request->expected_delivery_date,
                'notes' => $request->notes ?? $order->notes,
            ]);

            $stockReceipt = StockReceipt::create([
                'receipt_number' => StockReceipt::generateReceiptNumber(),
                'order_id' => $order->id,
                'franchise_id' => $order->franchise_id,
                'status' => 'pending',
                'notes' => $request->notes,
            ]);

            foreach ($order->items as $item) {
                $stockReceipt->items()->create([
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'ordered_quantity' => $item->quantity,
                    'received_quantity' => 0,
                ]);
            }

            return $order;
        });

        $result->load(['franchise', 'items.product.category', 'stockReceipt.items.product']);

        ActivityLogger::orderApproved($result, $request->user()->id);

        $franchiseUser = User::where('franchise_id', $result->franchise_id)
            ->whereHas('role', fn ($q) => $q->where('name', 'Franchise Partner'))
            ->pluck('id')
            ->all();

        $deliveryDate = $request->expected_delivery_date;

        NotificationService::send(
            $franchiseUser,
            'Order Approved — '.$result->order_number,
            'Your order has been approved. Expected delivery: '.$deliveryDate.'.',
            'order_approved',
            Order::class,
            $result->id,
            'franchise/orders/detail',
        );

        NotificationService::push($franchiseUser, 'Order Approved', 'Your order '.$result->order_number.' is approved.', 'franchise/orders/detail');

        event(new OrderStatusChanged($result, 'approved', 'pending'));

        return response()->json([
            'message' => 'Order approved. Warehouse stock reserved and stock receipt created.',
            'data' => $result,
        ]);
    }

    public function decline(Request $request, Order $order): JsonResponse
    {
        $request->validate(['decline_reason' => 'required|string']);

        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Order is not pending.'], 422);
        }

        $order->update([
            'status' => 'declined',
            'declined_by' => $request->user()->id,
            'declined_at' => now(),
            'decline_reason' => $request->decline_reason,
        ]);

        $order->items()->update(['status' => 'declined']);

        ActivityLogger::orderDeclined($order, $request->user()->id, $request->decline_reason);

        $franchiseUser = User::where('franchise_id', $order->franchise_id)
            ->whereHas('role', fn ($q) => $q->where('name', 'Franchise Partner'))
            ->pluck('id')
            ->all();

        NotificationService::send(
            $franchiseUser,
            'Order Declined — '.$order->order_number,
            'Your order was declined: '.$request->decline_reason,
            'order_declined',
            Order::class,
            $order->id,
            'franchise/orders/detail',
        );

        event(new OrderStatusChanged($order, 'declined', 'pending'));

        return response()->json(['message' => 'Order declined.', 'data' => $order->fresh(['franchise', 'items.product.category'])]);
    }

    public function adjust(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.order_item_id' => 'required|exists:order_items,id',
            'items.*.adjusted_quantity' => 'required|numeric|min:0',
            'items.*.adjustment_notes' => 'nullable|string',
        ]);

        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Order is not pending.'], 422);
        }

        $newTotal = 0;
        $newTax = 0;

        foreach ($request->items as $item) {
            $orderItem = $order->items()->find($item['order_item_id']);
            if ($orderItem) {
                $adjustedQty = (float) $item['adjusted_quantity'];
                $finalUnitPrice = (float) $orderItem->unit_price;
                $unitTax = round($finalUnitPrice - (float) ($orderItem->base_unit_price ?? $orderItem->unit_price), 2);

                $orderItem->update([
                    'adjusted_quantity' => $adjustedQty,
                    'adjustment_notes' => $item['adjustment_notes'] ?? null,
                    'status' => 'adjusted',
                    'tax_amount' => round($adjustedQty * $unitTax, 2),
                    'subtotal' => round($adjustedQty * $finalUnitPrice, 2),
                ]);

                $newTotal += round($adjustedQty * $finalUnitPrice, 2);
                $newTax += round($adjustedQty * $unitTax, 2);
            }
        }

        $order->update([
            'total_amount' => round($newTotal, 2),
            'tax_amount' => round($newTax, 2),
        ]);

        $franchiseUser = User::where('franchise_id', $order->franchise_id)
            ->whereHas('role', fn ($q) => $q->where('name', 'Franchise Partner'))
            ->pluck('id')
            ->all();

        NotificationService::send(
            $franchiseUser,
            'Order Adjusted — '.$order->order_number,
            'Your order quantities were adjusted by staff. Please review the new totals.',
            'order',
            Order::class,
            $order->id,
            'franchise/orders/detail',
        );

        return response()->json(['message' => 'Order items adjusted.', 'data' => $order->fresh(['items.product.category'])]);
    }

    public function dispatch(Request $request, Order $order): JsonResponse
    {
        // Strict protocol: dispatch is only allowed once Finance has ACCEPTED
        // (fully approved) a payment for this order. Verification alone is not
        // enough — the delivery_status is only advanced to ready_for_delivery
        // inside FinanceController::accept(), which is the final approval.
        $hasAcceptedPayment = $order->acceptedPayments()->exists();
        $readyForDelivery = in_array($order->delivery_status, ['payment_verified', 'ready_for_delivery']);

        if (! $hasAcceptedPayment && ! $readyForDelivery) {
            return response()->json([
                'message' => 'Order cannot be dispatched. Finance must first accept (approve) a payment for this order.',
            ], 422);
        }

        $order->update([
            'delivery_status' => 'out_for_delivery',
            'served_at' => now(),
        ]);

        ActivityLogger::log('delivery_dispatched', "Order {$order->order_number} marked Out for Delivery", $order, $request->user()->id);

        $franchiseUser = User::where('franchise_id', $order->franchise_id)
            ->whereHas('role', fn ($q) => $q->where('name', 'Franchise Partner'))
            ->pluck('id')
            ->all();

        NotificationService::send(
            $franchiseUser,
            'Out for Delivery — '.$order->order_number,
            'Your order is now out for delivery. Please be ready to receive it.',
            'delivery',
            Order::class,
            $order->id,
            'franchise/orders/detail',
        );

        event(new OrderStatusChanged($order, 'approved', 'out_for_delivery'));

        return response()->json(['message' => 'Order marked Out for Delivery.', 'data' => $order->fresh()]);
    }

    public function markDelivered(Request $request, Order $order): JsonResponse
    {
        if ($order->delivery_status !== 'out_for_delivery') {
            return response()->json(['message' => 'Order is not out for delivery.'], 422);
        }

        $order->update([
            'delivery_status' => 'delivered',
            'status' => 'delivered',
            'completed_at' => now(),
        ]);

        ActivityLogger::log('delivery_completed', "Order {$order->order_number} marked Delivered", $order, $request->user()->id);

        $franchiseUser = User::where('franchise_id', $order->franchise_id)
            ->whereHas('role', fn ($q) => $q->where('name', 'Franchise Partner'))
            ->pluck('id')
            ->all();

        NotificationService::send(
            $franchiseUser,
            'Delivered — '.$order->order_number,
            'Your order has been delivered. Please confirm receipt to complete the delivery.',
            'delivery',
            Order::class,
            $order->id,
            'franchise/orders/detail',
        );

        NotificationService::push($franchiseUser, 'Order Delivered', 'Your order '.$order->order_number.' has been delivered.', 'franchise/orders/detail');

        event(new OrderStatusChanged($order, 'delivered', 'delivered'));

        return response()->json(['message' => 'Order marked as Delivered.', 'data' => $order->fresh()]);
    }

    public function declineDelivery(Request $request, Order $order): JsonResponse
    {
        $request->validate(['reason' => 'required|string|min:5']);

        if (! in_array($order->delivery_status, ['out_for_delivery', 'ready_for_delivery', 'payment_verified', 'delivered'])) {
            return response()->json(['message' => 'Order cannot have delivery declined at this stage.'], 422);
        }

        $order->update([
            'delivery_status' => 'delivery_declined',
            'delivery_declined_reason' => $request->reason,
        ]);

        ActivityLogger::log('delivery_declined', "Order {$order->order_number} delivery declined: {$request->reason}", $order, $request->user()->id);

        $franchiseUser = User::where('franchise_id', $order->franchise_id)
            ->whereHas('role', fn ($q) => $q->where('name', 'Franchise Partner'))
            ->pluck('id')
            ->all();

        NotificationService::send(
            $franchiseUser,
            'Delivery Issue — '.$order->order_number,
            'Delivery was declined: '.$request->reason,
            'delivery',
            Order::class,
            $order->id,
            'franchise/orders/detail',
        );

        return response()->json(['message' => 'Delivery declined.', 'data' => $order->fresh()]);
    }
}
