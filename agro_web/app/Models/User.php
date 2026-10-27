<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'branch_id',
        'franchise_id',
        'pin_code',
        'is_active',
        'phone',
        'status',
        'avatar',
        'last_login_at',
        'employee_id',
        'address',
        'gender',
        'date_of_birth',
        'fcm_token',
        'notification_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'pin_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'date_of_birth' => 'date',
            'notification_preferences' => 'array',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function franchise()
    {
        return $this->belongsTo(Franchise::class);
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === 'System Administrator';
    }

    public function isStaff(): bool
    {
        return $this->role?->name === 'Farmmantra Staff';
    }

    public function isFinance(): bool
    {
        return $this->role?->name === 'Finance Department';
    }

    public function isFranchisePartner(): bool
    {
        return $this->role?->name === 'Franchise Partner';
    }

    protected $appends = ['avatar_url'];

    public function scopeStaffAndAdmin($query)
    {
        return $query->whereHas('role', function ($q) {
            $q->whereIn('name', ['System Administrator', 'Farmmantra Staff', 'Finance Department']);
        });
    }

    public function scopeStaffOnly($query)
    {
        return $query->whereHas('role', function ($q) {
            $q->where('name', 'Farmmantra Staff');
        });
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (empty($this->avatar)) {
            return null;
        }

        if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
            return $this->avatar;
        }

        return url('storage/'.ltrim($this->avatar, '/'));
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->employee_id) {
            return "{$this->name} (#{$this->employee_id})";
        }

        return $this->name;
    }

    public function getFranchiseNameAttribute(): string
    {
        return $this->franchise?->name ?? 'N/A';
    }
}
