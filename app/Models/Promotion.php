<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Promotion extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'code',
        'type',
        'discount_value',
        'buy_quantity',
        'get_quantity',
        'min_purchase_amount',
        'max_discount_amount',
        'usage_limit',
        'usage_per_customer',
        'times_used',
        'applicable_to',
        'applicable_ids',
        'branch_ids',
        'customer_group_ids',
        'start_date',
        'end_date',
        'days_of_week',
        'start_time',
        'end_time',
        'is_active',
        'auto_apply',
        'priority',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_purchase_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'applicable_ids' => 'array',
        'branch_ids' => 'array',
        'customer_group_ids' => 'array',
        'days_of_week' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'auto_apply' => 'boolean',
    ];

    /**
     * Relación con la empresa (tenant)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }

    /**
     * Productos asociados a la promoción
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_products')
            ->withPivot(['variant_id', 'special_price', 'min_quantity'])
            ->withTimestamps();
    }

    /**
     * Historial de uso de la promoción
     */
    public function usages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }

    /**
     * Verifica si la promoción está activa en este momento
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now();

        // Verificar rango de fechas
        if ($now->lt($this->start_date) || $now->gt($this->end_date)) {
            return false;
        }

        // Verificar día de la semana
        if ($this->days_of_week && !in_array($now->dayOfWeek, $this->days_of_week)) {
            return false;
        }

        // Verificar rango horario
        if ($this->start_time && $this->end_time) {
            $currentTime = $now->format('H:i:s');
            if ($currentTime < $this->start_time || $currentTime > $this->end_time) {
                return false;
            }
        }

        // Verificar límite de uso global
        if ($this->usage_limit && $this->times_used >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Verifica si la promoción aplica para una sucursal específica
     */
    public function appliesToBranch(int $branchId): bool
    {
        // Si branch_ids es null, aplica a todas las sucursales
        if (!$this->branch_ids) {
            return true;
        }

        return in_array($branchId, $this->branch_ids);
    }

    /**
     * Verifica si la promoción aplica para un producto específico
     */
    public function appliesToProduct(int $productId, ?int $variantId = null): bool
    {
        if ($this->applicable_to === 'all') {
            return true;
        }

        if ($this->applicable_to === 'products' && $this->applicable_ids) {
            return in_array($productId, $this->applicable_ids);
        }

        if ($this->applicable_to === 'categories') {
            $product = Product::find($productId);
            return $product && in_array($product->category_id, $this->applicable_ids ?? []);
        }

        if ($this->applicable_to === 'brands') {
            $product = Product::find($productId);
            return $product && in_array($product->brand_id, $this->applicable_ids ?? []);
        }

        return false;
    }

    /**
     * Verifica si un cliente puede usar esta promoción
     */
    public function canBeUsedByCustomer(?int $customerId): bool
    {
        if (!$this->usage_per_customer || !$customerId) {
            return true;
        }

        $usageCount = $this->usages()
            ->where('customer_id', $customerId)
            ->count();

        return $usageCount < $this->usage_per_customer;
    }

    /**
     * Calcula el descuento para un monto dado
     */
    public function calculateDiscount(float $amount, int $quantity = 1): float
    {
        $discount = 0;

        switch ($this->type) {
            case 'percentage':
                $discount = $amount * ($this->discount_value / 100);
                break;

            case 'fixed_amount':
                $discount = $this->discount_value;
                break;

            case 'bogo':
                // Lógica para BOGO se maneja en el servicio
                break;

            case 'volume':
                // Descuento por volumen
                if ($quantity >= ($this->buy_quantity ?? 1)) {
                    $discount = $amount * ($this->discount_value / 100);
                }
                break;
        }

        // Aplicar descuento máximo si está definido
        if ($this->max_discount_amount) {
            $discount = min($discount, $this->max_discount_amount);
        }

        return round($discount, 2);
    }

    /**
     * Incrementa el contador de uso
     */
    public function incrementUsage(): void
    {
        $this->increment('times_used');
    }

    /**
     * Scope para obtener solo promociones activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', Carbon::now())
            ->where('end_date', '>=', Carbon::now());
    }

    /**
     * Scope para promociones automáticas
     */
    public function scopeAutoApply($query)
    {
        return $query->where('auto_apply', true);
    }

    /**
     * Scope para ordenar por prioridad
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }
}
