<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditNote extends Model
{
    protected $fillable = [
        'note_number', 'franchise_id', 'type', 'order_id', 'amount', 'items',
        'reason', 'signature_number', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'items' => 'array',
    ];

    public function franchise()
    {
        return $this->belongsTo(Franchise::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateNumber(string $type): string
    {
        $prefix = ($type === 'sales_return' ? 'SR-' : 'CN-').date('Ym');
        $last = self::where('note_number', 'like', $prefix.'%')->orderByDesc('id')->value('note_number');
        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
