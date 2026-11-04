<?php

namespace App\Services;

use App\Events\OrderStatusChanged;
use App\Models\FranchiseInventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\User;
use App\Models\WarehouseInventory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Order lifecycle shared by the API (mobile) and the web dashboard so both
 * channels apply exactly the same stock, pricing, ledger and audit rules.
 *
 * pending → approved (warehouse, signed) → [cash: payment accepted] →
 * out_for_delivery (warehouse dispatch, means recorded, signed) → delivered →
 * confirmed (franchise accepts delivery + T&C, signed; stock & ledger updated)
 */
class OrderService
{
    public static function franchiseUserIds(int $franchiseId): array
    {
        return User::where('franchise_id', $franchiseId)
            ->whereHas('role', fn ($q) => $q->where('name', 'Franchise Partner'))
            ->pluck('id')->all();
    }

    public static function place(User $user, array $items, ?string $notes, string $paymentTerms = 'credit'): Order
    {
        if (! in_array($paymentTerms, ['credit', 'cash'], true)) {
            throw ValidationException::withMessages(['payment_terms' => 'Payment terms must be credit or cash.']);
        }

        $order = DB::transaction(function () use ($user, $items, $notes, $paymentTerms) {
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'franchise_id' => $user->franchise_id,
                'ordered_by' => $user->id,
                'status' => 'pending',
                'delivery_status' => 'pending',
                'payment_terms' => $paymentTerms,
                'notes' => $notes,
            ]);

            $totalAmount = 0;
            $totalTax = 0;

            foreach ($items as $item) {
                $product = Product::where('is_active', true)->find($item['product_id']);
                if (! $product) {
                    throw ValidationException::withMessages(['items' => 'One of the selected products is not available.']);
                }
                $quantity = (float) $item['quantity'];
                $baseUnitPrice = $product->getBestPrice($quantity);
                $tax = $product->calculateTaxPrice($baseUnitPrice);

                $finalUnitPrice = (float) $tax['final_price'];
                $unitTax = round($finalUnitPrice - $baseUnitPrice, 2);
                $subtotal = round($quantity * $finalUnitPrice, 2);

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $finalUnitPrice,
                    'base_unit_price' => $baseUnitPrice,
                    'tax_rate' => $product->tax_enabled ? (float) $product->tax_rate : 0.00,
                    'tax_amount' => round($quantity * $unitTax, 2),
                    'original_unit_price' => $product->standard_price,
                    'subtotal' => $subtotal,
                ]);

                $totalAmount += $subtotal;
                $totalTax += round($quantity * $unitTax, 2);
            }

            // Credit orders must stay within the franchise credit limit. Cash
            // orders are paid before dispatch, so they are not limited.
            $franchise = $user->franchise;
            if ($paymentTerms === 'credit' && $franchise && (float) $franchise->credit_limit > 0) {
                $creditLimit = (float) $franchise->credit_limit;
                $outstanding = (float) $franchise->account_balance;

                if (($outstanding + $totalAmount) > $creditLimit) {
                    throw ValidationException::withMessages([
                        'order' => 'This order exceeds the franchise credit limit (limit: '.number_format($creditLimit, 0).', projected outstanding: '.number_format($outstanding + $totalAmount, 0).'). Choose cash terms or reduce the order.',
                    ]);
                }
            }

            $order->update([
                'total_amount' => round($totalAmount, 2),
                'tax_amount' => round($totalTax, 2),
            ]);

            return $order;
        });

        $order->load('items.product.category');

        ActivityLogger::orderPlaced($order);

        NotificationService::send(
            User::orderWatchers()->pluck('id')->all(),
            'New Order '.$order->order_number,
            "{$user->franchise?->name} placed a new {$paymentTerms} order for ".$order->items->count().' product(s) worth '.number_format((float) $order->total_amount, 0),
            'order',
            Order::class,
            $order->id,
            'staff/orders/detail',
        );

        event(new OrderStatusChanged($order, 'pending', 'pending'));

        return $order;
    }

    public static function approve(Order $order, User $actor, string $expectedDeliveryDate, ?string $notes = null): Order
    {
        self::assertCanManage($actor);

        if ($order->status !== 'pending') {
            throw ValidationException::withMessages(['order' => 'Order is not pending.']);
        }

        foreach ($order->items as $item) {
            $qty = (float) ($item->adjusted_quantity ?? $item->quantity);
            $warehouse = WarehouseInventory::where('product_id', $item->product_id)->first();
            if ($qty > 0 && (! $warehouse || (float) $warehouse->quantity < $qty)) {
                throw ValidationException::withMessages(['order' => "Insufficient warehouse stock for {$item->product->name}."]);
            }
        }

        $order = DB::transaction(function () use ($order, $actor, $expectedDeliveryDate, $notes) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'pending') {
                throw ValidationException::withMessages(['order' => 'Order is not pending.']);
            }

            foreach ($order->items as $item) {
                // An adjusted quantity replaces the originally requested quantity.
                $qty = (float) ($item->adjusted_quantity ?? $item->quantity);
                if ($item->adjusted_quantity !== null) {
                    $item->quantity = $qty;
                }
                $item->status = 'approved';
                $item->save();

                if ($qty > 0) {
                    $warehouse = WarehouseInventory::where('product_id', $item->product_id)->lockForUpdate()->first();
                    $warehouse->quantity -= $qty;
                    $warehouse->save();
                    StockMovement::log('warehouse_out', $item->product_id, -$qty, $item->unit_price, Order::class, $order->id, "Order {$order->order_number} approved", $actor->id);
                }
            }

            $signature = SignatureService::sign($actor, 'order_approved', $order, [
                'total' => (string) $order->total_amount,
                'items' => $order->items->map(fn ($i) => [$i->product_id, (string) $i->quantity])->all(),
            ]);

            $order->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'approval_signature' => $signature->signature_number,
                'expected_delivery_date' => $expectedDeliveryDate,
                'notes' => $notes ?? $order->notes,
            ]);

            $stockReceipt = StockReceipt::create([
                'receipt_number' => StockReceipt::generateReceiptNumber(),
                'order_id' => $order->id,
                'franchise_id' => $order->franchise_id,
                'status' => 'pending',
                'notes' => $notes,
            ]);

            foreach ($order->items as $item) {
                if ((float) $item->quantity <= 0) {
                    continue;
                }
                $stockReceipt->items()->create([
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'ordered_quantity' => $item->quantity,
                    'received_quantity' => 0,
                ]);
            }

            return $order;
        });

        $order->load(['franchise', 'items.product.category', 'stockReceipt.items.product']);

        ActivityLogger::orderApproved($order, $actor->id);

        $franchiseUsers = self::franchiseUserIds($order->franchise_id);
        $terms = $order->payment_terms === 'cash' ? ' Please submit payment so the order can be dispatched.' : '';
        NotificationService::send(
            $franchiseUsers,
            'Order Approved — '.$order->order_number,
            'Your order has been approved. Expected delivery: '.$expectedDeliveryDate.'.'.$terms,
            'order_approved',
            Order::class,
            $order->id,
            'franchise/orders/detail',
        );
        NotificationService::push($franchiseUsers, 'Order Approved', 'Your order '.$order->order_number.' is approved.', 'franchise/orders/detail');

        event(new OrderStatusChanged($order, 'approved', 'pending'));

        return $order;
    }

    public static function decline(Order $order, User $actor, string $reason): Order
    {
        self::assertCanManage($actor);

        if ($order->status !== 'pending') {
            throw ValidationException::withMessages(['order' => 'Order is not pending.']);
        }

        $order->update([
            'status' => 'declined',
            'declined_by' => $actor->id,
            'declined_at' => now(),
            'decline_reason' => $reason,
        ]);
        $order->items()->update(['status' => 'declined']);

        ActivityLogger::orderDeclined($order, $actor->id, $reason);

        NotificationService::send(
            self::franchiseUserIds($order->franchise_id),
            'Order Declined — '.$order->order_number,
            'Your order was declined: '.$reason,
            'order_declined',
            Order::class,
            $order->id,
            'franchise/orders/detail',
        );

        event(new OrderStatusChanged($order, 'declined', 'pending'));

        return $order->fresh(['franchise', 'items.product.category']);
    }

    public static function dispatch(Order $order, User $actor, string $means, ?string $remarks = null, ?string $vehicle = null): Order
    {
        self::assertCanManage($actor);

        if (! array_key_exists($means, Order::DISPATCH_MEANS)) {
            throw ValidationException::withMessages(['dispatch_means' => 'Choose how the order is dispatched.']);
        }

        if ($order->status !== 'approved' || in_array($order->delivery_status, ['out_for_delivery', 'delivered', 'confirmed'], true)) {
            throw ValidationException::withMessages(['order' => 'Only approved orders that have not been dispatched can be dispatched.']);
        }

        // Cash orders are paid before dispatch; credit orders ship on account.
        if ($order->payment_terms === 'cash') {
            $hasAcceptedPayment = $order->acceptedPayments()->exists();
            $readyForDelivery = in_array($order->delivery_status, ['payment_verified', 'ready_for_delivery'], true);
            if (! $hasAcceptedPayment && ! $readyForDelivery) {
                throw ValidationException::withMessages([
                    'order' => 'This is a cash order. Finance must first accept a payment for it before it can be dispatched.',
                ]);
            }
        }

        $signature = SignatureService::sign($actor, 'order_dispatched', $order, ['means' => $means, 'vehicle' => $vehicle]);

        $order->update([
            'delivery_status' => 'out_for_delivery',
            'served_at' => now(),
            'dispatched_by' => $actor->id,
            'dispatched_at' => now(),
            'dispatch_means' => $means,
            'dispatch_vehicle' => $vehicle,
            'dispatch_remarks' => $remarks,
            'dispatch_signature' => $signature->signature_number,
        ]);

        $label = Order::DISPATCH_MEANS[$means];
        ActivityLogger::log('delivery_dispatched', "Order {$order->order_number} dispatched by {$label}", $order, $actor->id, [
            'means' => $means, 'vehicle' => $vehicle, 'remarks' => $remarks, 'signature' => $signature->signature_number,
        ]);

        NotificationService::send(
            self::franchiseUserIds($order->franchise_id),
            'Dispatched — '.$order->order_number,
            "Your order has been dispatched by {$label}".($vehicle ? " ({$vehicle})" : '').($remarks ? ". {$remarks}" : '').'. Please receive and accept it in the app.',
            'delivery',
            Order::class,
            $order->id,
            'franchise/orders/detail',
        );

        event(new OrderStatusChanged($order, 'approved', 'out_for_delivery'));

        return $order->fresh();
    }

    public static function markDelivered(Order $order, User $actor): Order
    {
        self::assertCanManage($actor);

        if ($order->delivery_status !== 'out_for_delivery') {
            throw ValidationException::withMessages(['order' => 'Order is not out for delivery.']);
        }

        // The order is only "delivered" (and stock/ledger updated) once the
        // franchise accepts the delivery; here the goods have arrived.
        $order->update(['delivery_status' => 'delivered']);

        ActivityLogger::log('delivery_completed', "Order {$order->order_number} marked as arrived at franchise", $order, $actor->id);

        $franchiseUsers = self::franchiseUserIds($order->franchise_id);
        NotificationService::send(
            $franchiseUsers,
            'Delivered — '.$order->order_number,
            'Your order has arrived. Please count the goods, read the terms and accept the delivery in the app.',
            'delivery',
            Order::class,
            $order->id,
            'franchise/orders/detail',
        );
        NotificationService::push($franchiseUsers, 'Order Delivered', 'Your order '.$order->order_number.' has arrived. Please accept it.', 'franchise/orders/detail');

        event(new OrderStatusChanged($order, 'delivered', 'delivered'));

        return $order->fresh();
    }

    /**
     * Franchise accepts a delivered order: confirms received quantities,
     * accepts the terms & conditions and signs. Inventory and the account
     * ledger are updated for the quantities actually received.
     *
     * @param  array<int, array{stock_receipt_item_id:int|string, received_quantity:numeric, discrepancy_notes?:string|null}>  $items
     */
    public static function receive(StockReceipt $stockReceipt, User $user, array $items, ?string $notes, bool $termsAccepted): StockReceipt
    {
        if ($stockReceipt->franchise_id !== $user->franchise_id) {
            throw new AuthorizationException('Unauthorized.');
        }

        if (! $termsAccepted) {
            throw ValidationException::withMessages(['accept_terms' => 'You must read and accept the terms and conditions to accept this delivery.']);
        }

        if ($stockReceipt->status !== 'pending') {
            throw ValidationException::withMessages(['receipt' => 'This receipt has already been processed.']);
        }

        $order = $stockReceipt->order;
        if (! in_array($order?->delivery_status, ['out_for_delivery', 'delivered'], true)) {
            throw ValidationException::withMessages(['receipt' => 'This order has not been dispatched yet. You can only accept goods that have been delivered.']);
        }

        $submitted = collect($items)->keyBy(fn ($i) => (string) $i['stock_receipt_item_id']);
        $receiptItemIds = $stockReceipt->items()->pluck('id')->map(fn ($id) => (string) $id);
        if ($submitted->count() !== $receiptItemIds->count() || $receiptItemIds->diff($submitted->keys())->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'Every receipt item must be explicitly reconciled.']);
        }

        $receipt = DB::transaction(function () use ($stockReceipt, $user, $submitted, $notes) {
            $lockedReceipt = StockReceipt::whereKey($stockReceipt->id)->lockForUpdate()->firstOrFail();
            if ($lockedReceipt->status !== 'pending') {
                throw ValidationException::withMessages(['receipt' => 'This receipt has already been processed.']);
            }
            $items = $lockedReceipt->items()->with(['product', 'orderItem'])->get();
            $order = $lockedReceipt->order()->lockForUpdate()->firstOrFail();

            $receivedValue = 0.0;
            foreach ($items as $item) {
                $input = $submitted->get((string) $item->id);
                $received = (float) $input['received_quantity'];
                if ($received > (float) $item->ordered_quantity) {
                    throw ValidationException::withMessages(['items' => "Received quantity for {$item->product->name} cannot exceed the dispatched quantity."]);
                }
                $item->update([
                    'received_quantity' => $received,
                    'discrepancy_notes' => $input['discrepancy_notes'] ?? null,
                ]);
                $unitPrice = (float) ($item->orderItem?->unit_price ?? $item->product->standard_price);
                $receivedValue += round($received * $unitPrice, 2);
            }

            $hasDiscrepancy = $items->contains(fn ($item) => (float) $item->ordered_quantity != (float) $item->received_quantity);

            $signature = SignatureService::sign($user, 'delivery_received', $order, [
                'receipt' => $lockedReceipt->receipt_number,
                'items' => $items->map(fn ($i) => [$i->product_id, (string) $i->received_quantity])->all(),
            ]);

            $lockedReceipt->update([
                'status' => 'confirmed',
                'received_by' => $user->id,
                'received_at' => now(),
                'terms_accepted_at' => now(),
                'signature_number' => $signature->signature_number,
                'notes' => $notes,
                'discrepancy_notes' => $hasDiscrepancy ? 'Discrepancy noted in received items' : null,
            ]);

            foreach ($items as $item) {
                $franchiseInventory = FranchiseInventory::where('franchise_id', $user->franchise_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first() ?? new FranchiseInventory([
                        'franchise_id' => $user->franchise_id,
                        'product_id' => $item->product_id,
                        'quantity' => 0,
                    ]);
                $franchiseInventory->quantity += $item->received_quantity;
                $franchiseInventory->total_value = $franchiseInventory->quantity * $item->product->standard_price;
                $franchiseInventory->save();

                if ($item->received_quantity > 0) {
                    StockMovement::log('franchise_in', $item->product_id, $item->received_quantity, $item->product->standard_price, StockReceipt::class, $lockedReceipt->id, "Receipt {$lockedReceipt->receipt_number} confirmed", $user->id);
                }
            }

            $order->update([
                'status' => 'delivered',
                'delivery_status' => 'confirmed',
                'received_at' => now(),
                'completed_at' => now(),
                'delivery_signature' => $signature->signature_number,
                'delivery_confirmed_by' => $user->id,
            ]);

            // The franchise is charged for what it actually received. Short
            // deliveries are not billed (no loophole for undelivered goods).
            if ($user->franchise && $receivedValue > 0) {
                LedgerService::charge($user->franchise, 'purchase', $receivedValue, "Purchase — order {$order->order_number}", $order, $order->order_number, $user->id);
                PaymentService::allocateOutstanding($user->franchise->fresh());
            }

            return $lockedReceipt->fresh(['items.product', 'order']);
        });

        ActivityLogger::orderDelivered($receipt->order, $user->id);

        NotificationService::send(
            User::orderWatchers()->pluck('id')->all(),
            'Delivery Accepted — '.$receipt->order->order_number,
            "{$user->franchise?->name} accepted delivery of order {$receipt->order->order_number} (signature {$receipt->signature_number}).",
            'delivery',
            Order::class,
            $receipt->order->id,
            'staff/orders/detail',
        );

        event(new OrderStatusChanged($receipt->order, 'delivered', 'confirmed'));

        return $receipt;
    }

    private static function assertCanManage(User $actor): void
    {
        if (! $actor->canManageOrders()) {
            throw new AuthorizationException('Only the warehouse can accept, decline or dispatch orders.');
        }
    }
}
