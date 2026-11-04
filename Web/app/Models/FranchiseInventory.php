<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FranchiseInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'franchise_id', 'product_id', 'quantity',
        'reorder_level', 'total_value',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'reorder_level' => 'decimal:2',
        'total_value' => 'decimal:2',
    ];

    public function franchise()
    {
        return $this->belongsTo(Franchise::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
