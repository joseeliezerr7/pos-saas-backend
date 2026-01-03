<?php

namespace App\Services;

use App\Events\SaleCompleted;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleDetail;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected PromotionService $promotionService,
        protected CreditService $creditService
    ) {}

    /**
     * Create a new sale
     */
    public function createSale(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            // Auto-find cash opening if not provided
            if (!isset($data['cash_opening_id']) || empty($data['cash_opening_id'])) {
                $cashOpening = \App\Models\CashRegister\CashOpening::where('is_open', true)
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->first();

                if (!$cashOpening) {
                    throw new \Exception("Debe tener una caja abierta para realizar ventas");
                }

                $data['cash_opening_id'] = $cashOpening->id;
            }

            // Validate stock availability
            foreach ($data['items'] as $item) {
                if (!$this->inventoryService->hasStock(
                    $item['product_id'],
                    $data['branch_id'],
                    $item['quantity'],
                    $item['variant_id'] ?? null
                )) {
                    throw new \Exception("Stock insuficiente para el producto ID: {$item['product_id']}");
                }
            }

            // Calculate totals
            $subtotal = 0;
            $tax = 0;
            $discount = $data['discount'] ?? 0;

            foreach ($data['items'] as $item) {
                $itemSubtotal = $item['quantity'] * $item['price'];
                $itemDiscount = $item['discount'] ?? 0;
                $taxRate = $item['tax_rate'] ?? 15.0;

                $subtotal += $itemSubtotal - $itemDiscount;
                $tax += ($itemSubtotal - $itemDiscount) * ($taxRate / 100);
            }

            // Apply loyalty tier discount if customer has a tier
            $tierDiscount = 0;
            if (!empty($data['customer_id'])) {
                $customerLoyalty = \App\Models\Loyalty\CustomerLoyalty::where('customer_id', $data['customer_id'])
                    ->with('currentTier')
                    ->first();

                if ($customerLoyalty && $customerLoyalty->currentTier && $customerLoyalty->currentTier->discount_percentage > 0) {
                    $tierDiscount = $subtotal * ($customerLoyalty->currentTier->discount_percentage / 100);
                    $discount += $tierDiscount;
                }
            }

            $total = $subtotal + $tax - $discount;

            // Validate credit limit if payment method is credit
            if ($data['payment_method'] === 'credit') {
                if (empty($data['customer_id'])) {
                    throw new \Exception("Las ventas a crédito requieren un cliente asignado");
                }

                $customer = \App\Models\Customer::findOrFail($data['customer_id']);

                $creditValidation = $this->creditService->validateCreditLimit($customer, $total);

                // Show warning but allow override if explicitly requested
                if (!$creditValidation['valid']) {
                    if (!isset($data['override_credit_limit']) || !$data['override_credit_limit']) {
                        throw new \Exception($creditValidation['warning']);
                    }
                }
            }

            // Create sale
            $sale = Sale::create([
                'tenant_id' => auth()->user()->tenant_id,
                'branch_id' => $data['branch_id'],
                'cash_opening_id' => $data['cash_opening_id'],
                'user_id' => auth()->id(),
                'customer_id' => $data['customer_id'] ?? null,
                'customer_rtn' => $data['customer_rtn'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'promotion_id' => $data['promotion_id'] ?? null,
                'coupon_code' => $data['coupon_code'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'payment_method' => $data['payment_method'],
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'payment_details' => $data['payment_details'] ?? null,
                'amount_paid' => $data['amount_paid'] ?? $total,
                'amount_change' => $data['amount_change'] ?? 0,
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
                'sold_at' => now(),
            ]);

            // Create sale details and update inventory
            foreach ($data['items'] as $item) {
                $product = \App\Models\Catalog\Product::find($item['product_id']);

                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'cost' => $product->cost,
                    'discount' => $item['discount'] ?? 0,
                    'tax_rate' => $item['tax_rate'] ?? 15.0,
                    'subtotal' => ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0),
                ]);

                // Reduce stock
                $this->inventoryService->reduceStock(
                    $item['product_id'],
                    $data['branch_id'],
                    $item['quantity'],
                    $item['variant_id'] ?? null,
                    'sale',
                    $sale->id
                );
            }

            // Register cash transaction (always required now)
            \App\Models\CashRegister\CashTransaction::create([
                'tenant_id' => $sale->tenant_id,
                'cash_opening_id' => $data['cash_opening_id'],
                'user_id' => auth()->id(),
                'type' => 'sale',
                'amount' => $total,
                'payment_method' => $data['payment_method'],
                'reference' => $sale->sale_number,
            ]);

            // Record promotion usage if promotion was applied
            if (!empty($data['promotion_id']) && $discount > 0) {
                $promotion = \App\Models\Promotion::find($data['promotion_id']);
                if ($promotion) {
                    $this->promotionService->recordPromotionUsage(
                        $promotion,
                        $sale,
                        $discount,
                        $data['coupon_code'] ?? null
                    );
                }
            }

            // Create credit sale record if payment method is credit
            if ($sale->payment_method === 'credit' && $sale->customer_id) {
                $customer = \App\Models\Customer::findOrFail($sale->customer_id);
                $this->creditService->createCreditSale($sale, $customer);
            }

            // Dispatch event
            event(new SaleCompleted($sale));

            return $sale->load('details');
        });
    }

    /**
     * Void a sale
     */
    public function voidSale(Sale $sale, string $reason = null): bool
    {
        if ($sale->isVoided()) {
            throw new \Exception('La venta ya está anulada');
        }

        if ($sale->hasInvoice() && !$sale->invoice->isVoided()) {
            throw new \Exception('Debe anular la factura antes de anular la venta');
        }

        return DB::transaction(function () use ($sale, $reason) {
            // Restore inventory
            foreach ($sale->details as $detail) {
                $this->inventoryService->increaseStock(
                    $detail->product_id,
                    $sale->branch_id,
                    $detail->quantity,
                    $detail->variant_id,
                    'return',
                    $sale->id
                );
            }

            // Update sale status
            $sale->update([
                'status' => 'voided',
                'notes' => ($sale->notes ?? '') . "\nANULADA: {$reason}",
            ]);

            return true;
        });
    }

    /**
     * Get sales statistics
     */
    public function getSalesStatistics(int $branchId, string $startDate, string $endDate): array
    {
        $sales = Sale::where('branch_id', $branchId)
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$startDate, $endDate])
            ->get();

        return [
            'total_sales' => $sales->count(),
            'total_revenue' => $sales->sum('total'),
            'total_tax' => $sales->sum('tax'),
            'total_discount' => $sales->sum('discount'),
            'average_sale' => $sales->avg('total'),
            'total_items' => $sales->sum(fn($sale) => $sale->getTotalItems()),
        ];
    }
}
