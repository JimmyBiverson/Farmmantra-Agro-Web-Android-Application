<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigitalSignature extends Model
{
    protected $fillable = [
        'signature_number', 'user_id', 'user_name', 'user_role', 'action',
        'signable_type', 'signable_id', 'payload_hash', 'ip_address', 'signed_at',
    ];

    protected $casts = ['signed_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function signable()
    {
        return $this->morphTo();
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'order_approved' => 'Order confirmed',
            'order_dispatched' => 'Order dispatched',
            'delivery_received' => 'Delivery received & accepted',
            'payment_accepted' => 'Payment accepted',
            'credit_note_issued' => 'Credit note issued',
            'sales_return_recorded' => 'Sales return recorded',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}
