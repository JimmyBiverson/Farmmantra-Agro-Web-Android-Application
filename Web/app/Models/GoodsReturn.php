<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReturn extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['items' => 'array', 'photos' => 'array', 'history' => 'array'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function franchise()
    {
        return $this->belongsTo(Franchise::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
