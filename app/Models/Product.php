<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'barcode',
        'description',
        'image',
        'price',
        'cost',
        'tax_rate',
        'stock',
        'stock_min',
        'stock_max',
        'is_active',
        'is_service',
        'manage_stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'stock' => 'integer',
        'stock_min' => 'integer',
        'stock_max' => 'integer',
        'is_active' => 'boolean',
        'is_service' => 'boolean',
        'manage_stock' => 'boolean',
    ];

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function stock()
    {
        return $this->hasMany(Stock::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function units()
    {
        return $this->belongsToMany(Unit::class, 'product_units')
            ->withPivot('quantity', 'price', 'barcode', 'is_base_unit')
            ->withTimestamps();
    }

    public function productUnits()
    {
        return $this->hasMany(ProductUnit::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('sku', 'like', "%{$search}%")
              ->orWhere('barcode', 'like', "%{$search}%");
        });
    }

    // Helpers
    public function getTotalStockAttribute()
    {
        // If user has assigned branch, sum stock only from that branch
        $userBranchId = auth()->check() ? auth()->user()->branch_id : null;

        if ($userBranchId) {
            return $this->stock()->where('branch_id', $userBranchId)->sum('quantity');
        }

        // If admin (no branch assigned), sum all stock
        return $this->stock()->sum('quantity');
    }

    public function getPriceWithTaxAttribute()
    {
        return $this->price + ($this->price * ($this->tax_rate / 100));
    }
}
