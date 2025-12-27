<?php

namespace App\Services;

use App\Models\Sale\Quotation;
use App\Models\Sale\QuotationDetail;
use App\Models\Sale\Sale;
use App\Models\Catalog\Product;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    public function __construct(
        protected SaleService $saleService
    ) {}

    /**
     * Create a new quotation
     */
    public function createQuotation(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
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

            $total = $subtotal + $tax - $discount;

            // Create quotation
            $quotation = Quotation::create([
                'tenant_id' => auth()->user()->tenant_id,
                'branch_id' => $data['branch_id'],
                'user_id' => auth()->id(),
                'customer_id' => $data['customer_id'] ?? null,
                'customer_rtn' => $data['customer_rtn'] ?? null,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'valid_until' => $data['valid_until'] ?? now()->addDays(15),
                'quoted_at' => now(),
            ]);

            // Create quotation details
            foreach ($data['items'] as $item) {
                $product = Product::find($item['product_id']);
                $itemSubtotal = ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0);
                $taxRate = $item['tax_rate'] ?? 15.0;
                $taxAmount = $itemSubtotal * ($taxRate / 100);

                QuotationDetail::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku ?? '',
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'discount' => $item['discount'] ?? 0,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'subtotal' => $itemSubtotal,
                ]);
            }

            return $quotation->fresh(['details']);
        });
    }

    /**
     * Update an existing quotation
     */
    public function updateQuotation(Quotation $quotation, array $data): Quotation
    {
        return DB::transaction(function () use ($quotation, $data) {
            // Update basic info
            $updateData = [];

            if (isset($data['customer_name'])) {
                $updateData['customer_name'] = $data['customer_name'];
            }
            if (isset($data['customer_email'])) {
                $updateData['customer_email'] = $data['customer_email'];
            }
            if (isset($data['customer_phone'])) {
                $updateData['customer_phone'] = $data['customer_phone'];
            }
            if (isset($data['notes'])) {
                $updateData['notes'] = $data['notes'];
            }
            if (isset($data['valid_until'])) {
                $updateData['valid_until'] = $data['valid_until'];
            }
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }

            // If items are updated, recalculate totals
            if (isset($data['items'])) {
                // Delete old details
                $quotation->details()->delete();

                // Calculate new totals
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

                $total = $subtotal + $tax - $discount;

                $updateData['subtotal'] = $subtotal;
                $updateData['discount'] = $discount;
                $updateData['tax'] = $tax;
                $updateData['total'] = $total;

                // Create new details
                foreach ($data['items'] as $item) {
                    $product = Product::find($item['product_id']);
                    $itemSubtotal = ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0);
                    $taxRate = $item['tax_rate'] ?? 15.0;
                    $taxAmount = $itemSubtotal * ($taxRate / 100);

                    QuotationDetail::create([
                        'quotation_id' => $quotation->id,
                        'product_id' => $item['product_id'],
                        'variant_id' => $item['variant_id'] ?? null,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku ?? '',
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'discount' => $item['discount'] ?? 0,
                        'tax_rate' => $taxRate,
                        'tax_amount' => $taxAmount,
                        'subtotal' => $itemSubtotal,
                    ]);
                }
            }

            $quotation->update($updateData);

            return $quotation->fresh(['details']);
        });
    }

    /**
     * Convert quotation to sale
     */
    public function convertToSale(Quotation $quotation, array $paymentData): Sale
    {
        return DB::transaction(function () use ($quotation, $paymentData) {
            // Prepare sale data from quotation
            $saleData = [
                'branch_id' => $quotation->branch_id,
                'cash_opening_id' => $paymentData['cash_opening_id'] ?? null,
                'customer_id' => $quotation->customer_id,
                'customer_rtn' => $quotation->customer_rtn,
                'customer_name' => $quotation->customer_name,
                'discount' => $quotation->discount,
                'payment_method' => $paymentData['payment_method'],
                'payment_details' => $paymentData['payment_details'] ?? null,
                'amount_paid' => $paymentData['amount_paid'] ?? $quotation->total,
                'amount_change' => $paymentData['amount_change'] ?? 0,
                'notes' => $quotation->notes . "\n(Convertida de cotización: {$quotation->quotation_number})",
                'items' => [],
            ];

            // Convert quotation details to sale items
            foreach ($quotation->details as $detail) {
                $saleData['items'][] = [
                    'product_id' => $detail->product_id,
                    'variant_id' => $detail->variant_id,
                    'quantity' => $detail->quantity,
                    'price' => $detail->price,
                    'discount' => $detail->discount,
                    'tax_rate' => $detail->tax_rate,
                ];
            }

            // Create the sale
            $sale = $this->saleService->createSale($saleData);

            // Update quotation status
            $quotation->update([
                'status' => 'converted',
                'converted_to_sale_id' => $sale->id,
            ]);

            return $sale;
        });
    }
}
