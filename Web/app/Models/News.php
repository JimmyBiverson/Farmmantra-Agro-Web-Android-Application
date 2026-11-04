<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    protected $fillable = ['title', 'slug', 'excerpt', 'body', 'image', 'is_published', 'published_at'];

    protected $casts = ['is_published' => 'boolean', 'published_at' => 'datetime'];

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
}
