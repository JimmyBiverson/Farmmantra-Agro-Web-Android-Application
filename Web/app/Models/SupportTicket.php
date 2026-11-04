<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    protected $fillable = [
        'ticket_number',
        'user_id',
        'franchise_id',
        'subject',
        'category',
        'priority',
        'status',
        'message',
        'admin_response',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public static function generateTicketNumber(): string
    {
        $prefix = 'TCK-'.date('Ymd').'-';
        $last = self::where('ticket_number', 'like', $prefix.'%')->latest('id')->first();
        $number = $last ? (int) str_replace($prefix, '', $last->ticket_number) + 1 : 1;

        return $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
