<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'franchise_id', 'product_category_id',
        'target_amount', 'target_quantity',
        'month', 'year',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'target_quantity' => 'decimal:2',
    ];

    public function franchise()
    {
        return $this->belongsTo(Franchise::class);
    }

    public function productCategory()
    {
        return $this->belongsTo(Category::class, 'product_category_id');
    }
}
