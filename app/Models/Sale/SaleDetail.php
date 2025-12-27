<?php

namespace App\Models\Sale;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'variant_id',
        'product_name',
        'product_sku',
        'quantity',
        'price',
        'cost',
        'discount',
        'tax_rate',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function getTotal(): float
    {
        return ($this->price * $this->quantity) - $this->discount;
    }

    public function getTax(): float
    {
        return $this->getTotal() * ($this->tax_rate / 100);
    }

    public function getProfit(): float
    {
        return ($this->price - $this->cost) * $this->quantity;
    }
}
