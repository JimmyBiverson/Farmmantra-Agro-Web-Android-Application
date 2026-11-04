<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line of a franchise account statement. Purchases are posted in
 * "credit"; payments, credit notes and sales returns in "debit" (client's
 * statement layout). Amount owed = SUM(credit) - SUM(debit).
 */
class AccountTransaction extends Model
{
    public const TYPES = ['opening_balance', 'purchase', 'payment', 'credit_note', 'sales_return', 'adjustment'];

    protected $fillable = [
        'franchise_id', 'type', 'reference_number', 'reference_type', 'reference_id',
        'description', 'credit', 'debit', 'transaction_date', 'created_by',
    ];

    protected $casts = [
        'credit' => 'decimal:2',
        'debit' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function franchise()
    {
        return $this->belongsTo(Franchise::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'opening_balance' => 'Opening Balance',
            'purchase' => 'Purchase',
            'payment' => 'Payment',
            'credit_note' => 'Credit Note',
            'sales_return' => 'Sales Return',
            default => 'Adjustment',
        };
    }
}
