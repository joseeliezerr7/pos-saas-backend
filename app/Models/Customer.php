<?php

namespace App\Models;

use App\Models\Sale\Sale;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, HasTenantScope, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'customer_group_id',
        'name',
        'rtn',
        'phone',
        'email',
        'address',
        'credit_limit',
        'credit_days',
        'current_balance',
        'loyalty_points',
        'is_active',
        'last_purchase_date',
        'total_purchases',
        'lifetime_value',
        'rfm_recency_score',
        'rfm_frequency_score',
        'rfm_monetary_score',
        'rfm_segment',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'loyalty_points' => 'integer',
        'is_active' => 'boolean',
        'last_purchase_date' => 'date',
        'total_purchases' => 'integer',
        'lifetime_value' => 'decimal:2',
        'rfm_recency_score' => 'integer',
        'rfm_frequency_score' => 'integer',
        'rfm_monetary_score' => 'integer',
    ];

    // Relationships
    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Relación con el grupo de clientes
     */
    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    /**
     * Relación many-to-many con tags
     */
    public function tags()
    {
        return $this->belongsToMany(CustomerTag::class, 'customer_customer_tag')
            ->withTimestamps();
    }

    /**
     * Relación con el programa de lealtad
     */
    public function loyalty()
    {
        return $this->hasOne(\App\Models\Loyalty\CustomerLoyalty::class);
    }

    /**
     * Relación con ventas al crédito
     */
    public function creditSales()
    {
        return $this->hasMany(\App\Models\Credit\CreditSale::class);
    }

    /**
     * Relación con pagos del cliente
     */
    public function payments()
    {
        return $this->hasMany(\App\Models\Credit\CustomerPayment::class);
    }

    // Credit Helper Methods
    public function hasAvailableCredit(float $amount): bool
    {
        return ($this->current_balance + $amount) <= $this->credit_limit;
    }

    public function getCreditAvailable(): float
    {
        return max(0, $this->credit_limit - $this->current_balance);
    }

    public function getOverdueAmount(): float
    {
        return $this->creditSales()->overdue()->sum('balance_due');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('rtn', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
        });
    }

    /**
     * Scope para filtrar por grupo de clientes
     */
    public function scopeInGroup($query, $groupId)
    {
        return $query->where('customer_group_id', $groupId);
    }

    /**
     * Scope para filtrar por segmento RFM
     */
    public function scopeInSegment($query, $segment)
    {
        return $query->where('rfm_segment', $segment);
    }

    /**
     * Scope para clientes con tag específico
     */
    public function scopeWithTag($query, $tagId)
    {
        return $query->whereHas('tags', function ($q) use ($tagId) {
            $q->where('customer_tags.id', $tagId);
        });
    }

    /**
     * Obtener descuento aplicable según el grupo
     */
    public function getApplicableDiscount()
    {
        return $this->customerGroup && $this->customerGroup->is_active
            ? $this->customerGroup->discount_percentage
            : 0;
    }

    /**
     * Obtener precio especial de un producto según el grupo
     */
    public function getProductPrice($productId)
    {
        if (!$this->customerGroup) {
            return null;
        }

        return $this->customerGroup->getProductPrice($productId);
    }
}
