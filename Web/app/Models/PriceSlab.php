<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceSlab extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'min_quantity', 'max_quantity', 'slab_price', 'is_active'];

    protected $casts = [
        'slab_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
