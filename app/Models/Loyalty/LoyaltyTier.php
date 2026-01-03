<?php

namespace App\Models\Loyalty;

use Illuminate\Database\Eloquent\Model;

class LoyaltyTier extends Model
{
    protected $fillable = [
        'loyalty_program_id',
        'name',
        'color',
        'min_points',
        'order',
        'discount_percentage',
        'points_multiplier',
        'benefits',
    ];

    protected $casts = [
        'min_points' => 'integer',
        'order' => 'integer',
        'discount_percentage' => 'decimal:2',
        'points_multiplier' => 'decimal:2',
        'benefits' => 'array',
    ];

    // Relaciones
    public function loyaltyProgram()
    {
        return $this->belongsTo(LoyaltyProgram::class);
    }

    public function customerLoyalties()
    {
        return $this->hasMany(CustomerLoyalty::class, 'current_tier_id');
    }
}
