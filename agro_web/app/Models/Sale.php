<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_number', 'franchise_id', 'customer_id', 'price_tier', 'created_by',
        'total_amount', 'discount', 'final_amount', 'total_cost',
        'payment_method', 'payment_status', 'notes', 'sale_date',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'sale_date' => 'date',
    ];

    public function franchise()
    {
        return $this->belongsTo(Franchise::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public static function generateSaleNumber(): string
    {
        $prefix = 'SAL-'.date('Ym');
        $last = self::where('sale_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();

        if ($last) {
            $sequence = intval(substr($last->sale_number, -4)) + 1;
        } else {
            $sequence = 1;
        }

        return $prefix.'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
