<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_number', 'franchise_id', 'order_id', 'amount',
        'payment_method', 'transaction_reference', 'bank_name',
        'proof_of_payment_path', 'status', 'submitted_at',
        'verified_by', 'verified_at', 'accepted_by', 'accepted_at',
        'rejected_by', 'rejected_at', 'rejection_reason', 'finance_notes', 'verified_amount',
        'info_requested_by', 'info_requested_at', 'info_request_note', 'acceptance_signature',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'verified_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'info_requested_at' => 'datetime',
    ];

    public function franchise()
    {
        return $this->belongsTo(Franchise::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'payment_order')
            ->withPivot('allocated_amount')
            ->withTimestamps();
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function acceptor()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function infoRequestedBy()
    {
        return $this->belongsTo(User::class, 'info_requested_by');
    }

    public function getProofUrlAttribute(): ?string
    {
        if (empty($this->proof_of_payment_path)) {
            return null;
        }

        if (filter_var($this->proof_of_payment_path, FILTER_VALIDATE_URL)) {
            return $this->proof_of_payment_path;
        }

        return url('storage/'.ltrim($this->proof_of_payment_path, '/'));
    }

    public static function generatePaymentNumber(): string
    {
        $prefix = 'PAY-'.date('Ym');
        $last = self::where('payment_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();

        if ($last) {
            $sequence = intval(substr($last->payment_number, -4)) + 1;
        } else {
            $sequence = 1;
        }

        return $prefix.'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
