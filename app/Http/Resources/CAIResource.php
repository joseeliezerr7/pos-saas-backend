<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CAIResource extends JsonResource
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
            'cai_number' => $this->cai_number,
            'document_type' => $this->document_type,
            'range_start' => $this->range_start,
            'range_end' => $this->range_end,
            'current_number' => $this->current_number,
            'expiration_date' => $this->expiration_date?->format('Y-m-d'),
            'status' => $this->status,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

            // Computed
            'available_numbers' => $this->range_end - $this->current_number,
            'is_active' => $this->status === 'active',
            'is_expired' => $this->expiration_date < now(),
        ];
    }
}
