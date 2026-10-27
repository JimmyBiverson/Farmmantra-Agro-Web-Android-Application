<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number', 'franchise_id', 'ordered_by', 'status',
        'delivery_status', 'delivery_declined_reason',
        'received_at', 'served_at', 'completed_at', 'notes',
        'expected_delivery_date', 'approved_by', 'approved_at',
        'declined_by', 'declined_at', 'decline_reason',
        'finance_verified_by', 'finance_verified_at',
        'total_amount', 'tax_amount', 'payment_terms', 'amount_paid',
        'dispatched_by', 'dispatched_at', 'dispatch_means', 'dispatch_vehicle', 'dispatch_remarks',
        'approval_signature', 'dispatch_signature', 'delivery_signature', 'delivery_confirmed_by',
    ];

    public const DISPATCH_MEANS = ['truck' => 'Truck', 'bus' => 'Bus', 'pickup' => 'Pick-up', 'motorcycle' => 'Motorcycle', 'collected' => 'Collected by franchise', 'other' => 'Other'];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'dispatched_at' => 'datetime',
        'expected_delivery_date' => 'datetime',
        'approved_at' => 'datetime',
        'declined_at' => 'datetime',
        'finance_verified_at' => 'datetime',
        'received_at' => 'datetime',
        'served_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function franchise()
    {
        return $this->belongsTo(Franchise::class);
    }

    public function orderedByUser()
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function declinedByUser()
    {
        return $this->belongsTo(User::class, 'declined_by');
    }

    public function dispatchedByUser()
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function deliveryConfirmedByUser()
    {
        return $this->belongsTo(User::class, 'delivery_confirmed_by');
    }

    public function signatures()
    {
        return $this->morphMany(DigitalSignature::class, 'signable')->orderBy('signed_at');
    }

    public static function dispatchTargetDays(): int
    {
        return max(1, (int) Setting::get('dispatch_target_days', 15));
    }

    /** Date by which the order should be dispatched (client target: 15 days). */
    public function getDispatchDueAtAttribute(): ?Carbon
    {
        return $this->created_at?->copy()->addDays(self::dispatchTargetDays());
    }

    public function getIsDispatchOverdueAttribute(): bool
    {
        if (in_array($this->status, ['declined'], true) || $this->dispatched_at || in_array($this->delivery_status, ['out_for_delivery', 'delivered', 'confirmed'], true)) {
            return false;
        }

        return $this->dispatch_due_at !== null && now()->greaterThan($this->dispatch_due_at);
    }

    /** Orders not yet dispatched and older than the dispatch target. */
    public function scopeDispatchOverdue($query)
    {
        return $query->whereIn('status', ['pending', 'approved'])
            ->whereNull('dispatched_at')
            ->whereNotIn('delivery_status', ['out_for_delivery', 'delivered', 'confirmed'])
            ->where('created_at', '<', now()->subDays(self::dispatchTargetDays()));
    }

    public function getBalanceDueAttribute(): float
    {
        return max(0, round((float) $this->total_amount - (float) $this->amount_paid, 2));
    }

    public function financeVerifiedByUser()
    {
        return $this->belongsTo(User::class, 'finance_verified_by');
    }

    public function paymentSubmissions()
    {
        return $this->hasMany(PaymentSubmission::class, 'order_id');
    }

    public function payments()
    {
        return $this->belongsToMany(PaymentSubmission::class, 'payment_order')
            ->withPivot('allocated_amount')
            ->withTimestamps();
    }

    public function acceptedPayments()
    {
        return $this->payments()->where('payment_submissions.status', 'accepted');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockReceipt()
    {
        return $this->hasOne(StockReceipt::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForFranchise($query, $franchiseId)
    {
        return $query->where('franchise_id', $franchiseId);
    }

    /**
     * Base order value (excluding tax).
     */
    public function getSubtotalAmountAttribute(): float
    {
        return round((float) $this->total_amount - (float) $this->tax_amount, 2);
    }

    /**
     * True when finance has accepted an accepted payment for this order.
     */
    public function getPaymentVerifiedAttribute(): bool
    {
        return $this->acceptedPayments()->exists()
            || ($this->finance_verified_by !== null && $this->delivery_status !== 'pending');
    }

    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD-'.date('Ym');
        $last = self::where('order_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();

        if ($last) {
            $sequence = intval(substr($last->order_number, -4)) + 1;
        } else {
            $sequence = 1;
        }

        return $prefix.'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
