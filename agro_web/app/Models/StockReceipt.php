<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number', 'order_id', 'franchise_id',
        'received_by', 'received_at', 'status',
        'notes', 'discrepancy_notes', 'terms_accepted_at', 'signature_number',
    ];

    protected $casts = ['received_at' => 'datetime', 'terms_accepted_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function franchise()
    {
        return $this->belongsTo(Franchise::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items()
    {
        return $this->hasMany(StockReceiptItem::class);
    }

    public static function generateReceiptNumber(): string
    {
        $prefix = 'RCV-'.date('Ym');
        $last = self::where('receipt_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();

        if ($last) {
            $sequence = intval(substr($last->receipt_number, -4)) + 1;
        } else {
            $sequence = 1;
        }

        return $prefix.'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
