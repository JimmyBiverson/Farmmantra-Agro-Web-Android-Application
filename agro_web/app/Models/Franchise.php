<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Franchise extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'contact_person', 'phone', 'email',
        'region', 'region_id', 'district_id', 'address', 'credit_limit', 'account_balance',
        'monthly_target', 'profile_photo', 'is_active',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'account_balance' => 'decimal:2',
        'monthly_target' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function regionModel()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function accountTransactions()
    {
        return $this->hasMany(AccountTransaction::class);
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function stockReceipts(): HasMany
    {
        return $this->hasMany(StockReceipt::class);
    }

    public function inventories()
    {
        return $this->hasMany(FranchiseInventory::class);
    }

    public function paymentSubmissions()
    {
        return $this->hasMany(PaymentSubmission::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function getPendingOrdersCountAttribute(): int
    {
        return $this->orders()->where('status', 'pending')->count();
    }
}
