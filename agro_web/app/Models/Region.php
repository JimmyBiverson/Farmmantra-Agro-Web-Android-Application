<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function districts()
    {
        return $this->hasMany(District::class)->orderBy('name');
    }

    public function franchises()
    {
        return $this->hasMany(Franchise::class);
    }
}
