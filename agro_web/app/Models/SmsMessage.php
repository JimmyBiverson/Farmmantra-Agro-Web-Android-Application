<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsMessage extends Model
{
    protected $fillable = [
        'sent_by', 'audience', 'message', 'recipients', 'recipient_count',
        'driver', 'status', 'provider_response',
    ];

    protected $casts = ['recipients' => 'array'];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
