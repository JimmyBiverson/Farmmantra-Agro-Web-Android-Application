<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'sort_order', 'image', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }

        return url('storage/'.ltrim($this->image, '/'));
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
