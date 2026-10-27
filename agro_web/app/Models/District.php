<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    protected $fillable = ['name', 'official_region', 'region_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
