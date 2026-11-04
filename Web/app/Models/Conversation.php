<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'franchise_id', 'created_by', 'subject',
        'priority', 'status',
    ];

    public function franchise()
    {
        return $this->belongsTo(Franchise::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function getUnreadCountAttribute(): int
    {
        return $this->messages()->where('is_read', false)->count();
    }

    /**
     * Unread messages for a given user (messages not sent by them and unread).
     */
    public function getUnreadForUserAttribute(): int
    {
        $userId = auth()->id();
        if (! $userId) {
            return 0;
        }

        return $this->messages()
            ->where('is_read', false)
            ->where('sender_id', '!=', $userId)
            ->count();
    }

    public function scopeForUser($query, User $user)
    {
        if ($user->role?->name === 'Franchise Partner') {
            return $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhere('franchise_id', $user->franchise_id);
            });
        }

        return $query;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('subject', 'like', "%{$term}%")
                ->orWhereHas('franchise', function ($franchiseQuery) use ($term) {
                    $franchiseQuery->where('name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%");
                })
                ->orWhereHas('creator', function ($creatorQuery) use ($term) {
                    $creatorQuery->where('name', 'like', "%{$term}%");
                });
        });
    }

    public function isAccessibleBy(User $user): bool
    {
        if ($user->role?->name !== 'Franchise Partner') {
            return true;
        }

        return $this->franchise_id === $user->franchise_id || $this->created_by === $user->id;
    }
}
