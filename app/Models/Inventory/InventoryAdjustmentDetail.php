<?php

namespace App\Models\Inventory;

use App\Models\Catalog\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustmentDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'adjustment_id',
        'product_id',
        'variant_id',
        'system_quantity',
        'physical_quantity',
        'difference',
        'cost',
        'total',
    ];

    protected $casts = [
        'system_quantity' => 'decimal:2',
        'physical_quantity' => 'decimal:2',
        'difference' => 'decimal:2',
        'cost' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($detail) {
            if (is_null($detail->difference)) {
                $detail->difference = $detail->physical_quantity - $detail->system_quantity;
            }

            if (is_null($detail->total)) {
                $detail->total = $detail->difference * $detail->cost;
            }
        });
    }

    /**
     * Relationships
     */
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class, 'adjustment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Helpers
     */
    public function isIncrease(): bool
    {
        return $this->difference > 0;
    }

    public function isDecrease(): bool
    {
        return $this->difference < 0;
    }

    public function getAdjustmentTypeAttribute(): string
    {
        if ($this->isIncrease()) {
            return 'increase';
        } elseif ($this->isDecrease()) {
            return 'decrease';
        }
        return 'none';
    }
}
