<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    public static function assertWarehouseReady(Order $order): void
    {
        if ($order->review_stage && $order->review_stage !== 'warehouse') {
            throw ValidationException::withMessages(['order' => 'Sales Head and Admin approval are required before warehouse acceptance.']);
        }
    }

    public static function review(Order $order, User $actor, bool $approve, string $notes): Order
    {
        $result = DB::transaction(function () use ($order, $actor, $approve, $notes) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $allowed = ($locked->review_stage === 'sales_head' && $actor->isSalesHead()) || ($locked->review_stage === 'admin' && $actor->isAdmin());
            abort_unless($allowed, 403, 'This review belongs to a different role or has already been completed.');
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['order' => 'Only pending orders can be reviewed.']);
            }
            $history = $locked->review_history ?? [];
            $history[] = ['stage' => $locked->review_stage, 'user_id' => $actor->id, 'name' => $actor->name, 'approved' => $approve, 'notes' => $notes, 'at' => now()->toIso8601String()];
            $locked->update(['review_stage' => $approve ? ($locked->review_stage === 'sales_head' ? 'admin' : 'warehouse') : 'declined', 'review_history' => $history,
                ...($approve ? [] : ['status' => 'declined', 'declined_by' => $actor->id, 'declined_at' => now(), 'decline_reason' => $notes])]);
            ActivityLogger::log('order.reviewed', 'Order '.$locked->order_number.' reviewed', $locked, $actor->id, end($history));

            return $locked;
        });
        NotificationService::send(User::orderWatchers()->pluck('id')->merge(OrderService::franchiseUserIds($result->franchise_id))->unique()->all(), 'Order review: '.$result->order_number, 'Current stage: '.$result->review_stage, 'order', Order::class, $result->id, 'staff/orders/detail');

        return $result;
    }
}
