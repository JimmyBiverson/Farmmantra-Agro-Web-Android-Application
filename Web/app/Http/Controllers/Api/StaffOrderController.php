<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'dispatchedByUser:id,name',
            'deliveryConfirmedByUser:id,name',
            'signatures',
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

        $result = OrderService::approve($order, $request->user(), $request->expected_delivery_date, $request->notes);

        return response()->json([
            'message' => 'Order approved. Warehouse stock reserved and stock receipt created.',
            'data' => $result,
        ]);
    }

    public function decline(Request $request, Order $order): JsonResponse
    {
        $request->validate(['decline_reason' => 'required|string']);

        $result = OrderService::decline($order, $request->user(), $request->decline_reason);

        return response()->json(['message' => 'Order declined.', 'data' => $result]);
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

        $itemsById = collect($request->items)->keyBy(fn ($i) => (string) $i['order_item_id']);
        if ($itemsById->keys()->diff($order->items->pluck('id')->map(fn ($id) => (string) $id))->isNotEmpty()) {
            return response()->json(['message' => 'One or more items do not belong to this order.'], 422);
        }

        $newTotal = 0;
        $newTax = 0;

        foreach ($order->items as $orderItem) {
            $input = $itemsById->get((string) $orderItem->id);
            if ($input) {
                $orderItem->update([
                    'adjusted_quantity' => (float) $input['adjusted_quantity'],
                    'adjustment_notes' => $input['adjustment_notes'] ?? null,
                    'status' => 'adjusted',
                ]);
            }

            // Lines that were not adjusted keep their original quantity.
            $qty = (float) ($orderItem->adjusted_quantity ?? $orderItem->quantity);
            $finalUnitPrice = (float) $orderItem->unit_price;
            $unitTax = round($finalUnitPrice - (float) ($orderItem->base_unit_price ?? $orderItem->unit_price), 2);
            $orderItem->update([
                'tax_amount' => round($qty * $unitTax, 2),
                'subtotal' => round($qty * $finalUnitPrice, 2),
            ]);

            $newTotal += round($qty * $finalUnitPrice, 2);
            $newTax += round($qty * $unitTax, 2);
        }

        $order->update([
            'total_amount' => round($newTotal, 2),
            'tax_amount' => round($newTax, 2),
        ]);

        ActivityLogger::log('order.adjusted', "Order {$order->order_number} quantities adjusted", $order, $request->user()->id, ['items' => $request->items]);

        $franchiseUser = User::where('franchise_id', $order->franchise_id)
            ->whereHas('role', fn ($q) => $q->where('name', 'Franchise Partner'))
            ->pluck('id')
            ->all();

        NotificationService::send(
            $franchiseUser,
            'Order Adjusted — '.$order->order_number,
            'Your order quantities were adjusted by the warehouse. Please review the new totals.',
            'order',
            Order::class,
            $order->id,
            'franchise/orders/detail',
        );

        return response()->json(['message' => 'Order items adjusted.', 'data' => $order->fresh(['items.product.category'])]);
    }

    public function dispatch(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'dispatch_means' => 'required|string|in:'.implode(',', array_keys(Order::DISPATCH_MEANS)),
            'dispatch_remarks' => 'nullable|string|max:1000',
            'dispatch_vehicle' => 'nullable|string|max:100',
        ]);

        $result = OrderService::dispatch($order, $request->user(), $request->dispatch_means, $request->dispatch_remarks, $request->dispatch_vehicle);

        return response()->json(['message' => 'Order dispatched.', 'data' => $result]);
    }

    public function markDelivered(Request $request, Order $order): JsonResponse
    {
        $result = OrderService::markDelivered($order, $request->user());

        return response()->json(['message' => 'Order marked as arrived. Waiting for the franchise to accept it.', 'data' => $result]);
    }

    /** Dispatch options for the mobile app. */
    public function dispatchOptions(): JsonResponse
    {
        return response()->json(['data' => collect(Order::DISPATCH_MEANS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()]);
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
