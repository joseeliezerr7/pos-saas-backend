<?php

namespace App\Models\Loyalty;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;

class CustomerLoyalty extends Model
{
    protected $table = 'customer_loyalty';

    protected $fillable = [
        'customer_id',
        'loyalty_program_id',
        'current_tier_id',
        'points',
        'lifetime_points',
        'points_redeemed',
        'total_spent',
        'purchases_count',
        'last_purchase_at',
        'enrolled_at',
    ];

    protected $casts = [
        'points' => 'integer',
        'lifetime_points' => 'integer',
        'points_redeemed' => 'integer',
        'total_spent' => 'decimal:2',
        'purchases_count' => 'integer',
        'last_purchase_at' => 'datetime',
        'enrolled_at' => 'datetime',
    ];

    // Relaciones
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function loyaltyProgram()
    {
        return $this->belongsTo(LoyaltyProgram::class);
    }

    public function currentTier()
    {
        return $this->belongsTo(LoyaltyTier::class, 'current_tier_id');
    }

    public function transactions()
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }
}
