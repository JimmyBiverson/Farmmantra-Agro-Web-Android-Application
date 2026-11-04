<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'product_id', 'quantity', 'unit_price',
        'base_unit_price', 'tax_rate', 'tax_amount',
        'adjusted_quantity', 'adjustment_notes', 'original_unit_price',
        'subtotal', 'notes', 'rejection_reason', 'status',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'base_unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'original_unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * The quantity to use after any staff adjustment.
     */
    public function getEffectiveQuantityAttribute(): float
    {
        return (float) ($this->adjusted_quantity ?? $this->quantity);
    }

    /**
     * Final line total (quantity × final unit price incl. tax).
     */
    public function getLineTotalAttribute(): float
    {
        return round($this->effective_quantity * (float) $this->unit_price, 2);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
