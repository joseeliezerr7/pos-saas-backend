<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryAdjustmentDetailResource extends JsonResource
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
            'adjustment_id' => $this->adjustment_id,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'product_name' => $this->product->name ?? null,
            'product_sku' => $this->product->sku ?? null,
            'variant_name' => $this->variant->name ?? null,
            'system_quantity' => (float) $this->system_quantity,
            'physical_quantity' => (float) $this->physical_quantity,
            'difference' => (float) $this->difference,
            'cost' => (float) $this->cost,
            'total' => (float) $this->total,
            'adjustment_type' => $this->adjustment_type,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),

            // Relationships
            'product' => new ProductResource($this->whenLoaded('product')),
            'variant' => new ProductVariantResource($this->whenLoaded('variant')),
        ];
    }
}
