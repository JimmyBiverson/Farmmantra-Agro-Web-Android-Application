<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'franchise_id', 'name', 'customer_type', 'district_id', 'created_by', 'customer_code', 'phone',
        'email', 'address', 'city', 'tax_id',
        'opening_balance', 'credit_balance', 'is_wholesale',
        'balance', 'loyalty_points', 'branch_id', 'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'credit_balance' => 'decimal:2',
        'balance' => 'decimal:2',
        'is_wholesale' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function franchise()
    {
        return $this->belongsTo(Franchise::class);
    }

    public const TYPES = ['retailer' => 'Retailer', 'farmer' => 'Farmer'];

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForFranchise($query, $franchiseId)
    {
        return $query->where('franchise_id', $franchiseId);
    }

    /**
     * Customers a franchise may see and sell to: those assigned to it, plus
     * every customer located in a district of the franchise's region.
     */
    public function scopeVisibleToFranchise($query, ?Franchise $franchise)
    {
        if (! $franchise) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($franchise) {
            $q->where('franchise_id', $franchise->id);
            if ($franchise->region_id) {
                $q->orWhereIn('district_id', District::where('region_id', $franchise->region_id)->select('id'));
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
