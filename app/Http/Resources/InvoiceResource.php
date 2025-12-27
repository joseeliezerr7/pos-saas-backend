<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
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
            'sale_id' => $this->sale_id,
            'cai_id' => $this->cai_id,
            'correlative_id' => $this->correlative_id,

            // Invoice details
            'invoice_number' => $this->invoice_number,
            'invoice_series' => $this->cai?->document_type ?? 'FACTURA',
            'cai' => $this->cai_number,
            'invoice_date' => $this->issued_at?->format('Y-m-d'),
            'cai_expiration' => $this->cai_expiration_date?->format('Y-m-d'),

            // Customer info
            'customer' => [
                'name' => $this->customer_name,
                'rtn' => $this->customer_rtn,
                'address' => $this->customer_address,
                'email' => $this->sale?->customer?->email,
                'phone' => $this->sale?->customer?->phone,
            ],

            // Amounts
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'taxable_subtotal' => (float) $this->subtotal_taxed,
            'exempt_subtotal' => (float) $this->subtotal_exempt,
            'tax' => (float) $this->tax,
            'total' => (float) $this->total,
            'total_in_words' => $this->total_in_words,

            // Status
            'status' => $this->status,
            'is_voided' => $this->is_voided,
            'void_reason' => $this->void_notes,
            'voided_at' => $this->voided_at?->format('Y-m-d H:i:s'),

            // Items
            'items' => $this->when($this->sale, function () {
                return $this->sale->details->map(function ($detail) {
                    return [
                        'id' => $detail->id,
                        'product_code' => $detail->product?->code,
                        'product_name' => $detail->product_name,
                        'quantity' => (float) $detail->quantity,
                        'price' => (float) $detail->price,
                        'discount' => (float) $detail->discount,
                        'tax_rate' => (float) $detail->tax_rate,
                        'tax' => (float) $detail->tax,
                        'total' => (float) $detail->total,
                    ];
                });
            }),

            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Relationships
            'sale' => new SaleResource($this->whenLoaded('sale')),
        ];
    }
}
