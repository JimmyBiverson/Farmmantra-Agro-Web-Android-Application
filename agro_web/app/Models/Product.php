<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'sku', 'category_id', 'unit_of_measure',
        'packaging_details', 'description', 'selling_price',
        'standard_price', 'image', 'is_active',
        'tax_enabled', 'tax_type', 'tax_rate', 'tax_amount', 'base_price', 'final_price',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'standard_price' => 'decimal:2',
        'tax_enabled' => 'boolean',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'base_price' => 'decimal:2',
        'final_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = ['image_url', 'all_images', 'effective_price'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function priceSlabs()
    {
        return $this->hasMany(PriceSlab::class);
    }

    public function warehouseInventory()
    {
        return $this->hasOne(WarehouseInventory::class);
    }

    public function franchiseInventories()
    {
        return $this->hasMany(FranchiseInventory::class);
    }

    public function getBestPrice(float $quantity): float
    {
        $slab = $this->priceSlabs()
            ->where('is_active', true)
            ->where('min_quantity', '<=', $quantity)
            ->where(function ($q) use ($quantity) {
                $q->whereNull('max_quantity')
                    ->orWhere('max_quantity', '>=', $quantity);
            })
            ->orderByDesc('min_quantity')
            ->first();

        return $slab ? (float) $slab->slab_price : (float) $this->standard_price;
    }

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }

        $cleanPath = ltrim($this->image, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        }

        return url('storage/'.$cleanPath);
    }

    public function getAllImagesAttribute(): array
    {
        $urls = [];
        if ($this->image_url) {
            $urls[] = $this->image_url;
        }

        if ($this->relationLoaded('images')) {
            foreach ($this->images as $img) {
                if ($img->image_url && ! in_array($img->image_url, $urls)) {
                    $urls[] = $img->image_url;
                }
            }
        }

        return $urls;
    }

    public function getEffectivePriceAttribute(): float
    {
        if ($this->tax_enabled && $this->final_price > 0) {
            return (float) $this->final_price;
        }

        return (float) $this->standard_price;
    }

    public function calculateTaxPrice(?float $basePrice = null): array
    {
        $base = $basePrice ?? (float) $this->standard_price;
        if (! $this->tax_enabled) {
            return [
                'base_price' => $base,
                'tax_enabled' => false,
                'tax_type' => 'percentage',
                'tax_rate' => 0.00,
                'tax_amount' => 0.00,
                'final_price' => $base,
            ];
        }

        $taxAmount = 0.00;
        if ($this->tax_type === 'percentage') {
            $taxAmount = round(($base * ((float) $this->tax_rate / 100)), 2);
        } else {
            $taxAmount = (float) $this->tax_rate;
        }
        $finalPrice = round($base + $taxAmount, 2);

        return [
            'base_price' => $base,
            'tax_enabled' => true,
            'tax_type' => $this->tax_type,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => $taxAmount,
            'final_price' => $finalPrice,
        ];
    }
}
