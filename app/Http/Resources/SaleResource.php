<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
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
            'cash_opening_id' => $this->cash_opening_id,
            'user_id' => $this->user_id,
            'customer_id' => $this->customer_id,
            'sale_number' => $this->sale_number,
            'customer_rtn' => $this->customer_rtn,
            'customer_name' => $this->customer_name,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'tax' => (float) $this->tax,
            'total' => (float) $this->total,
            'payment_method' => $this->payment_method,
            'transaction_reference' => $this->transaction_reference,
            'payment_details' => $this->payment_details,
            'amount_paid' => (float) $this->amount_paid,
            'amount_change' => (float) $this->amount_change,
            'status' => $this->status,
            'notes' => $this->notes,
            'sold_at' => $this->sold_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

            // Relationships
            'details' => SaleDetailResource::collection($this->whenLoaded('details')),
            'user' => new UserResource($this->whenLoaded('user')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),

            // Computed
            'total_items' => $this->when(
                $this->relationLoaded('details'),
                fn() => $this->details->sum('quantity')
            ),
            'has_invoice' => $this->when(
                $this->relationLoaded('invoice'),
                fn() => $this->invoice !== null
            ),
        ];
    }
}
