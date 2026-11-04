<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = ['title', 'slug', 'body', 'meta_description', 'is_published'];

    protected $casts = ['is_published' => 'boolean'];
}
