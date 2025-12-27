<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate tax amount based on subtotal (price * quantity - discount)
        $subtotal = ($this->price * $this->quantity) - $this->discount;
        $taxAmount = $subtotal * ($this->tax_rate / 100);
        $total = $subtotal + $taxAmount;

        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->price,
            'cost' => (float) $this->cost,
            'discount' => (float) $this->discount,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $taxAmount,
            'subtotal' => (float) $this->subtotal,
            'total' => (float) $total,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),

            // Relationships
            'product' => new ProductResource($this->whenLoaded('product')),
            'variant' => new ProductVariantResource($this->whenLoaded('variant')),
        ];
    }
}
