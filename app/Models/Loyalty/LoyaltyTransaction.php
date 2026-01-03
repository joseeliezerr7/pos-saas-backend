<?php

namespace App\Models\Loyalty;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Model;

class LoyaltyTransaction extends Model
{
    protected $fillable = [
        'customer_loyalty_id',
        'type',
        'points',
        'balance_after',
        'sale_id',
        'description',
        'expires_at',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
        'expires_at' => 'datetime',
    ];

    // Relaciones
    public function customerLoyalty()
    {
        return $this->belongsTo(CustomerLoyalty::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
