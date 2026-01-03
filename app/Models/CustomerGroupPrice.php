<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerGroupPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_group_id',
        'product_id',
        'price',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Relación con el grupo de clientes
     */
    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    /**
     * Relación con el producto
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope para precios activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para precios válidos en la fecha actual
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('valid_from')
                ->orWhere('valid_from', '<=', now());
        })->where(function ($q) {
            $q->whereNull('valid_until')
                ->orWhere('valid_until', '>=', now());
        });
    }

    /**
     * Scope para filtrar por grupo
     */
    public function scopeForGroup($query, $groupId)
    {
        return $query->where('customer_group_id', $groupId);
    }

    /**
     * Scope para filtrar por producto
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Verificar si el precio está vigente
     */
    public function isValidNow()
    {
        $now = now();

        $validFrom = !$this->valid_from || $this->valid_from <= $now;
        $validUntil = !$this->valid_until || $this->valid_until >= $now;

        return $this->is_active && $validFrom && $validUntil;
    }
}
