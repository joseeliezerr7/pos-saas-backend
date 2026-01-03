<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Credit\CustomerPayment;
use App\Models\Credit\CreditSale;
use App\Models\Credit\PaymentAllocation;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Record a customer payment and apply FIFO to credit sales
     *
     * @param array $data
     * @return CustomerPayment
     * @throws \Exception
     */
    public function recordPayment(array $data): CustomerPayment
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::findOrFail($data['customer_id']);

            // Create payment record
            $payment = CustomerPayment::create([
                'tenant_id' => auth()->user()->tenant_id,
                'customer_id' => $customer->id,
                'branch_id' => $data['branch_id'],
                'user_id' => auth()->id(),
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'payment_details' => $data['payment_details'] ?? null,
                'balance_before' => $customer->current_balance,
                'balance_after' => $customer->current_balance - $data['amount'],
                'notes' => $data['notes'] ?? null,
                'payment_date' => $data['payment_date'] ?? now(),
            ]);

            // Apply payment based on allocation type
            if (isset($data['allocation_type']) && $data['allocation_type'] === 'specific') {
                $this->applyPaymentToSales($payment, $data['specific_allocations'] ?? []);
            } else {
                // Default: FIFO application
                $this->applyPaymentFIFO($payment, $customer, $data['amount']);
            }

            // Update customer balance
            $customer->decrement('current_balance', $data['amount']);

            return $payment->load('allocations.creditSale.sale');
        });
    }

    /**
     * Apply payment to credit sales using FIFO (oldest first)
     *
     * @param CustomerPayment $payment
     * @param Customer $customer
     * @param float $amount
     * @return void
     */
    protected function applyPaymentFIFO(CustomerPayment $payment, Customer $customer, float $amount): void
    {
        // Get pending credit sales ordered by due date (oldest first)
        $pendingSales = CreditSale::where('customer_id', $customer->id)
            ->where('balance_due', '>', 0)
            ->orderBy('due_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $remainingAmount = $amount;

        foreach ($pendingSales as $creditSale) {
            if ($remainingAmount <= 0) break;

            $amountToApply = min($remainingAmount, $creditSale->balance_due);

            // Create allocation record
            PaymentAllocation::create([
                'tenant_id' => $payment->tenant_id,
                'customer_payment_id' => $payment->id,
                'credit_sale_id' => $creditSale->id,
                'amount_allocated' => $amountToApply,
            ]);

            // Update credit sale
            $creditSale->amount_paid += $amountToApply;
            $creditSale->balance_due -= $amountToApply;

            if ($creditSale->balance_due <= 0.01) { // Account for floating point
                $creditSale->balance_due = 0;
                $creditSale->status = 'paid';
                $creditSale->paid_at = now();
            } else {
                $creditSale->status = 'partial';
            }

            $creditSale->save();

            $remainingAmount -= $amountToApply;
        }

        // If there's still remaining amount, it becomes customer credit balance
        // (customer overpaid or paid in advance)
        // The customer balance will be negative, showing they have credit
    }

    /**
     * Apply payment to specific credit sale(s)
     *
     * @param CustomerPayment $payment
     * @param array $saleAllocations
     * @return void
     * @throws \Exception
     */
    public function applyPaymentToSales(CustomerPayment $payment, array $saleAllocations): void
    {
        DB::transaction(function () use ($payment, $saleAllocations) {
            foreach ($saleAllocations as $allocation) {
                $creditSale = CreditSale::findOrFail($allocation['credit_sale_id']);
                $amountToApply = min($allocation['amount'], $creditSale->balance_due);

                PaymentAllocation::create([
                    'tenant_id' => $payment->tenant_id,
                    'customer_payment_id' => $payment->id,
                    'credit_sale_id' => $creditSale->id,
                    'amount_allocated' => $amountToApply,
                ]);

                $creditSale->amount_paid += $amountToApply;
                $creditSale->balance_due -= $amountToApply;

                if ($creditSale->balance_due <= 0.01) {
                    $creditSale->markAsPaid();
                } else {
                    $creditSale->status = 'partial';
                    $creditSale->save();
                }
            }
        });
    }

    /**
     * Generate payment receipt PDF
     *
     * @param CustomerPayment $payment
     * @return string PDF path
     */
    public function generateReceipt(CustomerPayment $payment): string
    {
        // Ensure payment has all required relationships loaded
        $payment->load([
            'customer',
            'user',
            'branch.company',
            'allocations.creditSale.sale'
        ]);

        // Generate PDF using DomPDF
        $pdf = \PDF::loadView('receipts.payment', compact('payment'));

        // Ensure receipts directory exists
        $receiptDir = storage_path('app/receipts');
        if (!file_exists($receiptDir)) {
            mkdir($receiptDir, 0755, true);
        }

        // Save PDF to storage
        $fileName = "recibo-{$payment->payment_number}.pdf";
        $filePath = "{$receiptDir}/{$fileName}";
        $pdf->save($filePath);

        return $filePath;
    }
}
