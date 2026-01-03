<?php

namespace App\Models\Loyalty;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoyaltyProgram extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'is_active',
        'points_per_currency',
        'min_purchase_amount',
        'point_value',
        'points_expire',
        'expiration_days',
        'special_dates',
        'birthday_multiplier',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'points_per_currency' => 'decimal:2',
        'min_purchase_amount' => 'integer',
        'point_value' => 'decimal:2',
        'points_expire' => 'boolean',
        'expiration_days' => 'integer',
        'special_dates' => 'array',
        'birthday_multiplier' => 'decimal:2',
    ];

    // Relaciones
    public function company()
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }

    public function tiers()
    {
        return $this->hasMany(LoyaltyTier::class)->orderBy('order');
    }

    public function customerLoyalties()
    {
        return $this->hasMany(CustomerLoyalty::class);
    }
}
