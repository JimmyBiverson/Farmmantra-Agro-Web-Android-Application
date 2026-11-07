<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['details' => 'array', 'history' => 'array', 'amount' => 'decimal:2', 'entry_date' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function franchise()
    {
        return $this->belongsTo(Franchise::class);
    }
}
