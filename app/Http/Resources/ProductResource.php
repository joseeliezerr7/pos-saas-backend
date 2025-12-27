<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'unit_id' => $this->unit_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'type' => $this->type,
            'cost' => (float) $this->cost,
            'price' => (float) $this->price,
            'tax_rate' => (float) $this->tax_rate,
            'stock_min' => (int) $this->stock_min,
            'stock_max' => (int) $this->stock_max,
            'is_active' => (bool) $this->is_active,
            'has_variants' => (bool) $this->has_variants,
            'track_stock' => (bool) $this->track_stock,
            'image' => $this->image,
            'images' => $this->images,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

            // Relationships
            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'stock' => StockResource::collection($this->whenLoaded('stock')),

            // Computed
            'total_stock' => $this->when(
                $this->relationLoaded('stock'),
                fn() => $this->stock->sum('quantity')
            ),
        ];
    }
}
