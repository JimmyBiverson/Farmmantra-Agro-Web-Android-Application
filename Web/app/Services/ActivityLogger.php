<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public static function log(
        string $action,
        ?string $description = null,
        ?Model $subject = null,
        ?int $userId = null,
        ?array $properties = null,
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'properties' => $properties,
        ]);
    }

    public static function orderPlaced($order): ActivityLog
    {
        return self::log('order.placed', "Order {$order->order_number} placed", $order, $order->ordered_by, [
            'franchise_id' => $order->franchise_id,
            'total_amount' => $order->total_amount,
        ]);
    }

    public static function orderApproved($order, int $approvedBy): ActivityLog
    {
        return self::log('order.approved', "Order {$order->order_number} approved", $order, $approvedBy, [
            'franchise_id' => $order->franchise_id,
            'total_amount' => $order->total_amount,
        ]);
    }

    public static function orderDeclined($order, int $declinedBy, string $reason): ActivityLog
    {
        return self::log('order.declined', "Order {$order->order_number} declined: {$reason}", $order, $declinedBy, [
            'franchise_id' => $order->franchise_id,
            'reason' => $reason,
        ]);
    }

    public static function orderDelivered($order, int $confirmedBy): ActivityLog
    {
        return self::log('order.delivered', "Order {$order->order_number} delivery confirmed", $order, $confirmedBy, [
            'franchise_id' => $order->franchise_id,
        ]);
    }

    public static function saleCreated($sale): ActivityLog
    {
        return self::log('sale.created', "Sale {$sale->sale_number} recorded", $sale, $sale->created_by, [
            'franchise_id' => $sale->franchise_id,
            'total_amount' => $sale->final_amount,
        ]);
    }

    public static function paymentSubmitted($payment): ActivityLog
    {
        return self::log('payment.submitted', "Payment {$payment->payment_number} submitted", $payment, null, [
            'franchise_id' => $payment->franchise_id,
            'amount' => $payment->amount,
        ]);
    }

    public static function paymentVerified($payment, int $verifiedBy): ActivityLog
    {
        return self::log('payment.verified', "Payment {$payment->payment_number} verified", $payment, $verifiedBy, [
            'franchise_id' => $payment->franchise_id,
            'verified_amount' => $payment->verified_amount,
        ]);
    }

    public static function paymentAccepted($payment, int $acceptedBy): ActivityLog
    {
        return self::log('payment.accepted', "Payment {$payment->payment_number} accepted", $payment, $acceptedBy, [
            'franchise_id' => $payment->franchise_id,
            'verified_amount' => $payment->verified_amount,
        ]);
    }

    public static function paymentRejected($payment, int $rejectedBy, string $reason): ActivityLog
    {
        return self::log('payment.rejected', "Payment {$payment->payment_number} rejected: {$reason}", $payment, $rejectedBy, [
            'franchise_id' => $payment->franchise_id,
            'reason' => $reason,
        ]);
    }

    public static function userLogin($user): ActivityLog
    {
        return self::log('user.login', "{$user->name} logged in", $user, $user->id, [
            'ip' => request()->ip(),
        ]);
    }

    public static function stockUpdated(string $type, ?Model $subject, array $details): ActivityLog
    {
        return self::log("stock.{$type}", "Stock {$type}: ".($details['description'] ?? ''), $subject, null, $details);
    }
}
