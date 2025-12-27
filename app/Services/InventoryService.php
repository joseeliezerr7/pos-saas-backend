<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\Inventory\StockMovement;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Check if product has sufficient stock
     */
    public function hasStock(
        int $productId,
        int $branchId,
        float $quantity,
        ?int $variantId = null
    ): bool {
        $stock = $this->getStock($productId, $branchId, $variantId);

        if (!$stock) {
            return false;
        }

        return $stock->quantity >= $quantity;
    }

    /**
     * Get stock for a product in a branch
     */
    public function getStock(int $productId, int $branchId, ?int $variantId = null): ?Stock
    {
        return Stock::where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('variant_id', $variantId)
            ->first();
    }

    /**
     * Reduce stock quantity
     */
    public function reduceStock(
        int $productId,
        int $branchId,
        float $quantity,
        ?int $variantId = null,
        string $movementType = 'sale',
        ?int $referenceId = null
    ): void {
        DB::transaction(function () use ($productId, $branchId, $quantity, $variantId, $movementType, $referenceId) {
            $stock = Stock::where('product_id', $productId)
                ->where('branch_id', $branchId)
                ->where('variant_id', $variantId)
                ->lockForUpdate()
                ->firstOrFail();

            $previousQuantity = $stock->quantity;
            $newQuantity = $previousQuantity - $quantity;

            if ($newQuantity < 0) {
                throw new \Exception('Stock insuficiente');
            }

            $stock->update([
                'quantity' => $newQuantity,
                'last_movement_at' => now(),
            ]);

            // Record movement
            $this->recordMovement(
                $productId,
                $branchId,
                $variantId,
                'exit',
                $quantity,
                $previousQuantity,
                $newQuantity,
                $movementType,
                $referenceId
            );
        });
    }

    /**
     * Increase stock quantity
     */
    public function increaseStock(
        int $productId,
        int $branchId,
        float $quantity,
        ?int $variantId = null,
        string $movementType = 'purchase',
        ?int $referenceId = null,
        float $cost = 0
    ): void {
        DB::transaction(function () use ($productId, $branchId, $quantity, $variantId, $movementType, $referenceId, $cost) {
            $stock = Stock::where('product_id', $productId)
                ->where('branch_id', $branchId)
                ->where('variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                $stock = Stock::create([
                    'tenant_id' => auth()->user()->tenant_id,
                    'product_id' => $productId,
                    'branch_id' => $branchId,
                    'variant_id' => $variantId,
                    'quantity' => 0,
                    'average_cost' => 0,
                ]);
            }

            $previousQuantity = $stock->quantity;
            $newQuantity = $previousQuantity + $quantity;

            // Update average cost if cost is provided
            if ($cost > 0) {
                $totalCost = ($stock->average_cost * $previousQuantity) + ($cost * $quantity);
                $newAverageCost = $newQuantity > 0 ? $totalCost / $newQuantity : 0;

                $stock->update([
                    'quantity' => $newQuantity,
                    'average_cost' => $newAverageCost,
                    'last_movement_at' => now(),
                ]);
            } else {
                $stock->update([
                    'quantity' => $newQuantity,
                    'last_movement_at' => now(),
                ]);
            }

            // Record movement
            $this->recordMovement(
                $productId,
                $branchId,
                $variantId,
                'entry',
                $quantity,
                $previousQuantity,
                $newQuantity,
                $movementType,
                $referenceId,
                $cost
            );
        });
    }

    /**
     * Adjust stock (can be positive or negative)
     */
    public function adjustStock(
        int $branchId,
        int $productId,
        float $quantity,
        string $type,
        string $reason = 'adjustment',
        ?int $variantId = null,
        ?int $adjustmentId = null
    ): Stock {
        $stock = $this->getStock($productId, $branchId, $variantId);

        if (!$stock) {
            // Create stock if it doesn't exist
            $stock = Stock::create([
                'tenant_id' => auth()->user()->tenant_id,
                'product_id' => $productId,
                'branch_id' => $branchId,
                'variant_id' => $variantId,
                'quantity' => 0,
                'average_cost' => 0,
            ]);
        }

        if ($type === 'increase') {
            $this->increaseStock($productId, $branchId, $quantity, $variantId, $reason, $adjustmentId);
        } elseif ($type === 'decrease') {
            $this->reduceStock($productId, $branchId, $quantity, $variantId, $reason, $adjustmentId);
        }

        return $stock->fresh();
    }

    /**
     * Transfer stock between branches
     */
    public function transferStock(
        int $fromBranchId,
        int $toBranchId,
        int $productId,
        float $quantity,
        ?string $notes = null,
        ?int $variantId = null,
        ?int $transferId = null
    ): array {
        $result = [];

        DB::transaction(function () use ($productId, $fromBranchId, $toBranchId, $quantity, $variantId, $transferId, &$result) {
            // Check if source has enough stock
            if (!$this->hasStock($productId, $fromBranchId, $quantity, $variantId)) {
                throw new \Exception('Stock insuficiente en la sucursal de origen');
            }

            // Reduce from source branch
            $this->reduceStock($productId, $fromBranchId, $quantity, $variantId, 'transfer_out', $transferId);

            // Increase in destination branch
            $sourceStock = $this->getStock($productId, $fromBranchId, $variantId);
            $cost = $sourceStock ? $sourceStock->average_cost : 0;

            $this->increaseStock($productId, $toBranchId, $quantity, $variantId, 'transfer_in', $transferId, $cost);

            $result = [
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranchId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'transferred_at' => now(),
            ];
        });

        return $result;
    }

    /**
     * Record stock movement
     */
    protected function recordMovement(
        int $productId,
        int $branchId,
        ?int $variantId,
        string $type,
        float $quantity,
        float $previousQuantity,
        float $newQuantity,
        string $referenceType,
        ?int $referenceId,
        float $cost = 0
    ): void {
        StockMovement::create([
            'tenant_id' => auth()->user()->tenant_id,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'user_id' => auth()->id(),
            'type' => $type,
            'quantity' => $quantity,
            'cost' => $cost,
            'previous_quantity' => $previousQuantity,
            'new_quantity' => $newQuantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Get low stock products for a branch
     */
    public function getLowStockProducts(int $branchId): \Illuminate\Support\Collection
    {
        return Stock::where('branch_id', $branchId)
            ->whereColumn('quantity', '<=', 'min_stock')
            ->with('product')
            ->get();
    }

    /**
     * Get stock value for a branch
     */
    public function getStockValue(int $branchId): float
    {
        return Stock::where('branch_id', $branchId)
            ->get()
            ->sum(fn($stock) => $stock->quantity * $stock->average_cost);
    }

    /**
     * Get stock movements history
     */
    public function getStockMovements(
        ?int $branchId = null,
        ?int $productId = null,
        ?string $type = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $perPage = 20
    ) {
        $query = StockMovement::with(['product', 'branch', 'user'])
            ->where('tenant_id', auth()->user()->tenant_id);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($productId) {
            $query->where('product_id', $productId);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }
}
