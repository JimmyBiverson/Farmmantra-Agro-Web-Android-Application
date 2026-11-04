<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'quantity', 'reserved_quantity',
        'reorder_level', 'last_restocked_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'reserved_quantity' => 'decimal:2',
        'reorder_level' => 'decimal:2',
        'last_restocked_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getAvailableQuantityAttribute(): float
    {
        return $this->quantity - $this->reserved_quantity;
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->quantity <= $this->reorder_level;
    }
}
