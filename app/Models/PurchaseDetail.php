<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'variant_id',
        'product_name',
        'quantity',
        'quantity_received',
        'cost',
        'tax_rate',
        'discount',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'cost' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // Relationships
    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    // Helper methods
    public function calculateSubtotal()
    {
        $this->subtotal = ($this->quantity * $this->cost) - $this->discount;
    }

    public function getPendingQuantity()
    {
        return $this->quantity - $this->quantity_received;
    }

    public function isFullyReceived()
    {
        return $this->quantity_received >= $this->quantity;
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($detail) {
            $detail->calculateSubtotal();
        });
    }
}
