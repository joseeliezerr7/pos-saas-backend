<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_yearly',
        'features',
        'max_branches',
        'max_users',
        'max_products',
        'max_monthly_transactions',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'features' => 'array',
        'max_branches' => 'integer',
        'max_users' => 'integer',
        'max_products' => 'integer',
        'max_monthly_transactions' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function getPrice(string $cycle = 'monthly'): float
    {
        return $cycle === 'yearly' ? $this->price_yearly : $this->price_monthly;
    }

    public function hasUnlimitedFeature(string $feature): bool
    {
        return $this->{"max_$feature"} === -1;
    }

    public function canAccommodate(string $feature, int $currentCount): bool
    {
        $limit = $this->{"max_$feature"};
        return $limit === -1 || $currentCount < $limit;
    }
}
