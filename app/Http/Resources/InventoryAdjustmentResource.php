<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryAdjustmentResource extends JsonResource
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
            'approved_by' => $this->approved_by,
            'adjustment_number' => $this->adjustment_number,
            'reason' => $this->reason,
            'reason_label' => $this->reason_label,
            'notes' => $this->notes,
            'total_adjustment' => (float) $this->total_adjustment,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

            // Relationships
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'user' => new UserResource($this->whenLoaded('user')),
            'approver' => new UserResource($this->whenLoaded('approver')),
            'details' => InventoryAdjustmentDetailResource::collection($this->whenLoaded('details')),

            // Computed
            'can_approve' => $this->canBeApproved(),
            'can_reject' => $this->canBeRejected(),
            'items_count' => $this->when(
                $this->relationLoaded('details'),
                fn() => $this->details->count()
            ),
        ];
    }
}
