<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionUsage extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'promotion_usage';

    protected $fillable = [
        'tenant_id',
        'promotion_id',
        'sale_id',
        'customer_id',
        'user_id',
        'discount_amount',
        'coupon_code',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
    ];

    /**
     * Relación con la promoción
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * Relación con la venta
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Relación con el cliente
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relación con el usuario que aplicó la promoción
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
