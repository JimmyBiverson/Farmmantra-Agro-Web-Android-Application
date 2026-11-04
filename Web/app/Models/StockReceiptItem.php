<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_receipt_id', 'order_item_id', 'product_id',
        'ordered_quantity', 'received_quantity', 'discrepancy_notes',
    ];

    protected $casts = [
        'ordered_quantity' => 'decimal:2',
        'received_quantity' => 'decimal:2',
    ];

    public function stockReceipt()
    {
        return $this->belongsTo(StockReceipt::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getHasDiscrepancyAttribute(): bool
    {
        return $this->ordered_quantity != $this->received_quantity;
    }
}
