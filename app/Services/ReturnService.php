<?php

namespace App\Services;

use App\Models\Sale\ProductReturn;
use App\Models\Sale\ReturnDetail;
use App\Models\Sale\Sale;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Create a new return
     */
    public function createReturn(array $data): ProductReturn
    {
        return DB::transaction(function () use ($data) {
            $sale = Sale::with('details')->findOrFail($data['sale_id']);

            // Validate sale can be returned
            if ($sale->status === 'voided') {
                throw new \Exception("No se puede devolver una venta anulada");
            }

            // Calculate totals
            $subtotal = 0;
            $tax = 0;
            $discount = 0;

            foreach ($data['items'] as $item) {
                $itemSubtotal = $item['quantity_returned'] * $item['price'];
                $itemDiscount = $item['discount'] ?? 0;
                $taxRate = $item['tax_rate'] ?? 15.0;

                $subtotal += $itemSubtotal;
                $discount += $itemDiscount;
                $tax += ($itemSubtotal - $itemDiscount) * ($taxRate / 100);
            }

            $total = $subtotal + $tax - $discount;
            $refundAmount = $data['refund_amount'] ?? $total;

            // Determine return type
            $returnType = count($data['items']) === $sale->details->count() ? 'full' : 'partial';

            // Create return
            $return = ProductReturn::create([
                'tenant_id' => auth()->user()->tenant_id,
                'branch_id' => $data['branch_id'] ?? $sale->branch_id,
                'sale_id' => $sale->id,
                'user_id' => auth()->id(),
                'customer_id' => $sale->customer_id,
                'customer_name' => $sale->customer_name,
                'return_type' => $returnType,
                'reason' => $data['reason'] ?? null,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'refund_method' => $data['refund_method'] ?? 'cash',
                'refund_amount' => $refundAmount,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'returned_at' => now(),
            ]);

            // Create return details and restore inventory
            foreach ($data['items'] as $item) {
                $saleDetail = $sale->details()->find($item['sale_detail_id']);

                if (!$saleDetail) {
                    throw new \Exception("Detalle de venta no encontrado: {$item['sale_detail_id']}");
                }

                // Validate quantity
                if ($item['quantity_returned'] > $saleDetail->quantity) {
                    throw new \Exception("No se puede devolver más cantidad de la vendida");
                }

                $itemSubtotal = ($item['quantity_returned'] * $item['price']) - ($item['discount'] ?? 0);
                $taxRate = $item['tax_rate'] ?? 15.0;
                $taxAmount = $itemSubtotal * ($taxRate / 100);

                ReturnDetail::create([
                    'return_id' => $return->id,
                    'sale_detail_id' => $saleDetail->id,
                    'product_id' => $saleDetail->product_id,
                    'variant_id' => $saleDetail->variant_id,
                    'product_name' => $saleDetail->product_name,
                    'product_sku' => $saleDetail->product_sku ?? '',
                    'quantity_returned' => $item['quantity_returned'],
                    'price' => $item['price'],
                    'discount' => $item['discount'] ?? 0,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'subtotal' => $itemSubtotal,
                    'reason' => $item['reason'] ?? null,
                ]);

                // Restore inventory
                $this->inventoryService->increaseStock(
                    $saleDetail->product_id,
                    $sale->branch_id,
                    $item['quantity_returned'],
                    $saleDetail->variant_id,
                    'return',
                    $return->id
                );
            }

            return $return->fresh(['details']);
        });
    }

    /**
     * Complete a return (process refund)
     */
    public function completeReturn(ProductReturn $return): ProductReturn
    {
        return DB::transaction(function () use ($return) {
            if ($return->isCompleted()) {
                throw new \Exception("Esta devolución ya fue procesada");
            }

            if ($return->isCancelled()) {
                throw new \Exception("Esta devolución fue cancelada");
            }

            // Mark return as completed
            $return->update(['status' => 'completed']);

            // If cash refund, register transaction in cash register
            if (in_array($return->refund_method, ['cash', 'mixed'])) {
                $cashOpening = \App\Models\CashRegister\CashOpening::where('is_open', true)
                    ->where('tenant_id', $return->tenant_id)
                    ->first();

                if ($cashOpening) {
                    \App\Models\CashRegister\CashTransaction::create([
                        'tenant_id' => $return->tenant_id,
                        'cash_opening_id' => $cashOpening->id,
                        'user_id' => auth()->id(),
                        'type' => 'withdrawal',
                        'amount' => $return->refund_amount,
                        'payment_method' => 'cash',
                        'reference' => "Devolución {$return->return_number}",
                        'notes' => "Reembolso de devolución",
                    ]);
                }
            }

            return $return->fresh();
        });
    }

    /**
     * Cancel a return
     */
    public function cancelReturn(ProductReturn $return, string $reason): ProductReturn
    {
        return DB::transaction(function () use ($return, $reason) {
            if ($return->isCompleted()) {
                throw new \Exception("No se puede cancelar una devolución ya procesada");
            }

            // Reverse inventory changes
            foreach ($return->details as $detail) {
                $this->inventoryService->reduceStock(
                    $detail->product_id,
                    $return->branch_id,
                    $detail->quantity_returned,
                    $detail->variant_id
                );
            }

            // Mark as cancelled
            $return->update([
                'status' => 'cancelled',
                'notes' => ($return->notes ?? '') . "\n\nMotivo de cancelación: " . $reason,
            ]);

            return $return->fresh();
        });
    }
}
