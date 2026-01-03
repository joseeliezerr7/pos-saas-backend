<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'discount_percentage',
        'is_active',
        'color',
        'priority',
    ];

    protected $casts = [
        'discount_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Relación con la empresa
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relación con clientes que pertenecen a este grupo
     */
    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Relación con precios especiales de productos para este grupo
     */
    public function groupPrices()
    {
        return $this->hasMany(CustomerGroupPrice::class);
    }

    /**
     * Obtener productos con precios especiales para este grupo
     */
    public function productsWithSpecialPrices()
    {
        return $this->belongsToMany(Product::class, 'customer_group_prices')
            ->withPivot('price', 'valid_from', 'valid_until', 'is_active')
            ->withTimestamps();
    }

    /**
     * Scope para grupos activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para filtrar por empresa
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope para ordenar por prioridad
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Obtener el precio especial de un producto para este grupo
     */
    public function getProductPrice($productId)
    {
        $specialPrice = $this->groupPrices()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            })
            ->first();

        return $specialPrice ? $specialPrice->price : null;
    }

    /**
     * Verificar si el grupo tiene descuento automático
     */
    public function hasDiscount()
    {
        return $this->discount_percentage > 0;
    }

    /**
     * Obtener el total de clientes en este grupo
     */
    public function getCustomerCountAttribute()
    {
        return $this->customers()->count();
    }
}
