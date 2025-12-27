<?php

namespace App\Services;

use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\InventoryAdjustmentDetail;
use App\Models\Inventory\StockMovement;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentService
{
    /**
     * Create a new inventory adjustment
     */
    public function createAdjustment(array $data): InventoryAdjustment
    {
        return DB::transaction(function () use ($data) {
            // Create adjustment
            $adjustment = InventoryAdjustment::create([
                'tenant_id' => auth()->user()->tenant_id,
                'branch_id' => $data['branch_id'],
                'user_id' => auth()->id(),
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
                'total_adjustment' => 0,
            ]);

            $totalAdjustment = 0;

            // Create adjustment details
            foreach ($data['items'] as $item) {
                // Get current stock
                $stock = Stock::where('product_id', $item['product_id'])
                    ->where('branch_id', $data['branch_id'])
                    ->when(isset($item['variant_id']), fn($q) => $q->where('variant_id', $item['variant_id']))
                    ->first();

                $systemQuantity = $stock ? $stock->quantity : 0;
                $physicalQuantity = $item['physical_quantity'];
                $difference = $physicalQuantity - $systemQuantity;
                $cost = $item['cost'] ?? ($stock ? $stock->average_cost : 0);
                $total = $difference * $cost;

                InventoryAdjustmentDetail::create([
                    'adjustment_id' => $adjustment->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'system_quantity' => $systemQuantity,
                    'physical_quantity' => $physicalQuantity,
                    'difference' => $difference,
                    'cost' => $cost,
                    'total' => $total,
                ]);

                $totalAdjustment += abs($total);
            }

            // Update total adjustment
            $adjustment->update([
                'total_adjustment' => $totalAdjustment,
            ]);

            return $adjustment->load('details.product', 'details.variant');
        });
    }

    /**
     * Approve an adjustment and update stock
     */
    public function approveAdjustment(InventoryAdjustment $adjustment): bool
    {
        if (!$adjustment->canBeApproved()) {
            throw new \Exception('Este ajuste no puede ser aprobado');
        }

        return DB::transaction(function () use ($adjustment) {
            // Approve adjustment
            $adjustment->approve();

            // Update stock for each detail
            foreach ($adjustment->details as $detail) {
                if ($detail->difference == 0) {
                    continue;
                }

                // Get or create stock record
                $stock = Stock::firstOrCreate(
                    [
                        'product_id' => $detail->product_id,
                        'branch_id' => $adjustment->branch_id,
                        'variant_id' => $detail->variant_id,
                    ],
                    [
                        'tenant_id' => $adjustment->tenant_id,
                        'quantity' => 0,
                        'average_cost' => $detail->cost,
                        'min_stock' => 0,
                        'max_stock' => 0,
                    ]
                );

                // Update quantity
                $newQuantity = $stock->quantity + $detail->difference;
                $stock->update(['quantity' => $newQuantity]);

                // Create stock movement
                StockMovement::create([
                    'tenant_id' => $adjustment->tenant_id,
                    'product_id' => $detail->product_id,
                    'variant_id' => $detail->variant_id,
                    'branch_id' => $adjustment->branch_id,
                    'type' => $detail->difference > 0 ? 'adjustment_in' : 'adjustment_out',
                    'quantity' => abs($detail->difference),
                    'cost' => $detail->cost,
                    'reference_type' => 'App\\Models\\Inventory\\InventoryAdjustment',
                    'reference_id' => $adjustment->id,
                    'user_id' => auth()->id(),
                    'notes' => "Ajuste de inventario #{$adjustment->adjustment_number} - {$adjustment->reason_label}",
                    'movement_date' => now(),
                ]);
            }

            return true;
        });
    }

    /**
     * Reject an adjustment
     */
    public function rejectAdjustment(InventoryAdjustment $adjustment): bool
    {
        if (!$adjustment->canBeRejected()) {
            throw new \Exception('Este ajuste no puede ser rechazado');
        }

        return $adjustment->reject();
    }

    /**
     * Delete a pending adjustment
     */
    public function deleteAdjustment(InventoryAdjustment $adjustment): bool
    {
        if (!$adjustment->isPending()) {
            throw new \Exception('Solo se pueden eliminar ajustes pendientes');
        }

        return DB::transaction(function () use ($adjustment) {
            $adjustment->details()->delete();
            return $adjustment->delete();
        });
    }
}
