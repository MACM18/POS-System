<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends TenantModel
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'credit_limit',
        'balance',
        'metadata',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'credit_limit' => 'decimal:2',
        'balance' => 'decimal:2',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the sales for this customer.
     */
    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Get the full address attribute.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Check if customer has credit available.
     */
    public function hasCredit(float $amount): bool
    {
        return ($this->credit_limit - $this->balance) >= $amount;
    }

    /**
     * Add to customer balance.
     */
    public function addToBalance(float $amount): void
    {
        $this->increment('balance', $amount);
    }

    /**
     * Reduce customer balance.
     */
    public function reduceBalance(float $amount): void
    {
        $this->decrement('balance', $amount);
    }

    /**
     * Get total purchases.
     */
    public function getTotalPurchasesAttribute(): float
    {
        return $this->sales()
            ->where('status', 'completed')
            ->sum('total');
    }

    /**
     * Scope a query to only include active customers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to search customers.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")
                ->orWhere('phone', 'ilike', "%{$search}%");
        });
    }
}
