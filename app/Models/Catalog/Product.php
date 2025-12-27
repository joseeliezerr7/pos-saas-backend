<?php

namespace App\Models\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Stock;
use App\Traits\Auditable;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope, Auditable;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'brand_id',
        'sku',
        'barcode',
        'name',
        'description',
        'cost',
        'price',
        'tax_rate',
        'tax_type',
        'image',
        'images',
        'has_variants',
        'track_stock',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'images' => 'array',
        'has_variants' => 'boolean',
        'track_stock' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected $auditEvents = ['created', 'updated', 'deleted'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function stock(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function getStockForBranch(int $branchId): ?Stock
    {
        return $this->stock()->where('branch_id', $branchId)->first();
    }

    public function getTotalStock(): float
    {
        return $this->stock()->sum('quantity');
    }

    public function getPriceWithTax(): float
    {
        if ($this->tax_type === 'exempt') {
            return $this->price;
        }

        return $this->price * (1 + ($this->tax_rate / 100));
    }

    public function getProfit(): float
    {
        return $this->price - $this->cost;
    }

    public function getProfitMargin(): float
    {
        if ($this->cost == 0) {
            return 0;
        }

        return (($this->price - $this->cost) / $this->cost) * 100;
    }

    public function isLowStock(int $branchId): bool
    {
        $stock = $this->getStockForBranch($branchId);
        return $stock && $stock->quantity <= $stock->min_stock;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('sku', 'like', "%{$search}%")
              ->orWhere('barcode', 'like', "%{$search}%");
        });
    }
}
