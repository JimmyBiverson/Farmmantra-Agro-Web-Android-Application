<?php

namespace App\Services;

use App\Events\OrderStatusChanged;
use App\Models\AccountTransaction;
use App\Models\Franchise;
use App\Models\Order;
use App\Models\PaymentSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Franchise payments. Franchises may pay any amount at any time (e.g. daily,
 * as their own customers pay them). Accepted payments reduce the account
 * balance and are applied to orders oldest-first, so each order shows what
 * has been paid and what is still due.
 */
class PaymentService
{
    /** Cash payments are made by mobile money or bank transfer. */
    public const METHODS = ['mobile_money' => 'Mobile Money', 'bank_transfer' => 'Bank Transfer'];

    public static function submit(User $user, array $data, ?UploadedFile $proof = null): PaymentSubmission
    {
        if (! array_key_exists($data['payment_method'] ?? '', self::METHODS)) {
            throw ValidationException::withMessages(['payment_method' => 'Payment method must be mobile money or bank transfer.']);
        }

        $orderIds = array_values(array_unique(array_filter((array) ($data['order_ids'] ?? []))));
        if (! empty($data['order_id'] ?? null) && empty($orderIds)) {
            $orderIds = [$data['order_id']];
        }

        if (! empty($orderIds)) {
            $valid = Order::whereIn('id', $orderIds)
                ->where('franchise_id', $user->franchise_id)
                ->whereIn('status', ['approved', 'delivered'])
                ->count();
            if ($valid !== count($orderIds)) {
                throw ValidationException::withMessages([
                    'order_ids' => 'One or more orders are invalid, not approved, or do not belong to your franchise.',
                ]);
            }
        }

        $payment = PaymentSubmission::create([
            'payment_number' => PaymentSubmission::generatePaymentNumber(),
            'franchise_id' => $user->franchise_id,
            'order_id' => $orderIds[0] ?? null,
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'proof_of_payment_path' => $proof?->store('payment-proofs', 'public'),
            'finance_notes' => null,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        foreach ($orderIds as $orderId) {
            $payment->orders()->attach($orderId, ['allocated_amount' => 0]);
        }

        ActivityLogger::paymentSubmitted($payment);

        $financeIds = User::whereHas('role', fn ($q) => $q->where('name', 'Finance Department'))->pluck('id')->all();
        NotificationService::send(
            $financeIds,
            'New Payment '.$payment->payment_number,
            "{$user->franchise?->name} submitted a payment of ".number_format((float) $payment->amount, 0).' UGX.',
            'payment',
            PaymentSubmission::class,
            $payment->id,
            'finance/payments/detail',
        );

        return $payment->load('orders');
    }

    public static function verify(PaymentSubmission $payment, User $actor, float $verifiedAmount, ?string $notes = null): PaymentSubmission
    {
        if ($payment->status !== 'pending') {
            throw ValidationException::withMessages(['payment' => 'Payment is not pending.']);
        }
        if ($verifiedAmount < 0 || $verifiedAmount > (float) $payment->amount) {
            throw ValidationException::withMessages(['verified_amount' => 'Verified amount must be between 0 and the submitted amount.']);
        }

        $payment->update([
            'status' => 'verified',
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'verified_amount' => $verifiedAmount,
            'finance_notes' => $notes,
        ]);

        ActivityLogger::paymentVerified($payment, $actor->id);

        NotificationService::send(
            OrderService::franchiseUserIds($payment->franchise_id),
            'Payment Verified — '.$payment->payment_number,
            'Your payment of '.number_format($verifiedAmount, 0).' UGX was verified and is pending final acceptance.',
            'payment_verified',
            PaymentSubmission::class,
            $payment->id,
            'franchise/payments/detail',
        );

        return $payment->fresh(['franchise', 'orders']);
    }

    /** @return array{0: PaymentSubmission, 1: Franchise} */
    public static function accept(PaymentSubmission $payment, User $actor): array
    {
        if ($payment->status !== 'verified') {
            throw ValidationException::withMessages(['payment' => 'Payment must be verified first.']);
        }

        [$payment, $linkedOrders] = DB::transaction(function () use ($payment, $actor) {
            $locked = PaymentSubmission::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'verified') {
                throw ValidationException::withMessages(['payment' => 'Payment must be verified first.']);
            }

            $signature = SignatureService::sign($actor, 'payment_accepted', $locked, [
                'amount' => (string) $locked->verified_amount,
                'reference' => $locked->transaction_reference,
            ]);

            $locked->update([
                'status' => 'accepted',
                'accepted_by' => $actor->id,
                'accepted_at' => now(),
                'acceptance_signature' => $signature->signature_number,
            ]);

            $franchise = Franchise::findOrFail($locked->franchise_id);
            LedgerService::settle($franchise, 'payment', (float) $locked->verified_amount, "Payment {$locked->payment_number} (".(self::METHODS[$locked->payment_method] ?? $locked->payment_method).')', $locked, $locked->payment_number, $actor->id);

            $linkedOrders = $locked->orders()->get();
            foreach ($linkedOrders as $order) {
                $order->update([
                    'finance_verified_by' => $actor->id,
                    'finance_verified_at' => now(),
                ]);
                if (in_array($order->delivery_status, ['pending', 'payment_verified'], true) && $order->status === 'approved') {
                    $order->update(['delivery_status' => 'ready_for_delivery']);
                }
            }

            self::allocateOutstanding($franchise->fresh());

            return [$locked->fresh(['franchise', 'orders']), $linkedOrders];
        });

        ActivityLogger::paymentAccepted($payment, $actor->id);

        foreach ($linkedOrders as $order) {
            event(new OrderStatusChanged($order, 'approved', 'ready_for_delivery'));
            NotificationService::send(
                OrderService::franchiseUserIds($order->franchise_id),
                'Ready for Delivery — '.$order->order_number,
                'Your payment was accepted. Order '.$order->order_number.' is ready for dispatch.',
                'delivery',
                Order::class,
                $order->id,
                'franchise/orders/detail',
            );
        }

        NotificationService::send(
            OrderService::franchiseUserIds($payment->franchise_id),
            'Payment Accepted — '.$payment->payment_number,
            'Your payment of '.number_format((float) $payment->verified_amount, 0).' UGX was accepted. New balance: '.number_format((float) $payment->franchise->fresh()->account_balance, 0).' UGX.',
            'payment',
            PaymentSubmission::class,
            $payment->id,
            'franchise/payments/detail',
        );

        if ($linkedOrders->isNotEmpty()) {
            NotificationService::send(
                User::orderWatchers()->pluck('id')->all(),
                'Payment Accepted — Ready for Dispatch',
                'Payment '.$payment->payment_number.' was accepted. Linked orders are ready for dispatch.',
                'payment_verified',
                PaymentSubmission::class,
                $payment->id,
                'staff/orders/detail',
            );
        }

        return [$payment, $payment->franchise->fresh()];
    }

    public static function reject(PaymentSubmission $payment, User $actor, string $reason): PaymentSubmission
    {
        if (! in_array($payment->status, ['pending', 'verified', 'info_requested'], true)) {
            throw ValidationException::withMessages(['payment' => 'Payment cannot be rejected in its current status.']);
        }

        $payment->update([
            'status' => 'rejected',
            'rejected_by' => $actor->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        ActivityLogger::paymentRejected($payment, $actor->id, $reason);

        NotificationService::send(
            OrderService::franchiseUserIds($payment->franchise_id),
            'Payment Rejected — '.$payment->payment_number,
            'Your payment was rejected: '.$reason.'. Please review and re-submit.',
            'payment_rejected',
            PaymentSubmission::class,
            $payment->id,
            'franchise/payments/detail',
        );

        return $payment->fresh('franchise');
    }

    /**
     * Re-apply everything the franchise has settled (payments, credit notes,
     * sales returns) to its orders, oldest first. Idempotent: amounts are
     * recomputed from the ledger every time.
     */
    public static function allocateOutstanding(Franchise $franchise): void
    {
        $settled = (float) AccountTransaction::where('franchise_id', $franchise->id)->sum('debit');
        // Settlements first clear any opening balance / adjustments.
        $settled -= (float) AccountTransaction::where('franchise_id', $franchise->id)
            ->whereIn('type', ['opening_balance', 'adjustment'])
            ->sum('credit');
        $pool = max(0, round($settled, 2));

        $charged = AccountTransaction::where('franchise_id', $franchise->id)
            ->where('type', 'purchase')
            ->where('reference_type', Order::class)
            ->selectRaw('reference_id, SUM(credit) as amount')
            ->groupBy('reference_id')
            ->pluck('amount', 'reference_id');

        $orders = Order::where('franchise_id', $franchise->id)
            ->whereIn('status', ['approved', 'delivered'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($orders as $order) {
            $due = (float) ($charged[$order->id] ?? $order->total_amount);
            $paid = round(min($due, $pool), 2);
            $pool = round($pool - $paid, 2);
            if ((float) $order->amount_paid !== $paid) {
                $order->forceFill(['amount_paid' => $paid])->saveQuietly();
            }
        }
    }
}
