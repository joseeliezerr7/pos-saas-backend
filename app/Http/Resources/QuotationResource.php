<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
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
            'branch_id' => $this->branch_id,
            'user_id' => $this->user_id,
            'customer_id' => $this->customer_id,
            'quotation_number' => $this->quotation_number,
            'customer_rtn' => $this->customer_rtn,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'tax' => (float) $this->tax,
            'total' => (float) $this->total,
            'status' => $this->status,
            'notes' => $this->notes,
            'valid_until' => $this->valid_until?->format('Y-m-d H:i:s'),
            'quoted_at' => $this->quoted_at?->format('Y-m-d H:i:s'),
            'converted_to_sale_id' => $this->converted_to_sale_id,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

            // Relationships
            'details' => QuotationDetailResource::collection($this->whenLoaded('details')),
            'user' => new UserResource($this->whenLoaded('user')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'sale' => new SaleResource($this->whenLoaded('sale')),

            // Computed
            'total_items' => $this->when(
                $this->relationLoaded('details'),
                fn() => $this->details->sum('quantity')
            ),
            'is_converted' => $this->converted_to_sale_id !== null,
            'is_expired' => $this->valid_until && $this->valid_until->isPast(),
            'days_until_expiry' => $this->valid_until ? now()->diffInDays($this->valid_until, false) : null,
        ];
    }
}
