<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Sale\Sale;
use App\Models\Credit\CreditSale;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreditService
{
    /**
     * Validate if customer can make a credit purchase
     *
     * @param Customer $customer
     * @param float $saleAmount
     * @return array
     */
    public function validateCreditLimit(Customer $customer, float $saleAmount): array
    {
        $newBalance = $customer->current_balance + $saleAmount;
        $limitExceeded = $newBalance > $customer->credit_limit;

        return [
            'valid' => !$limitExceeded,
            'current_balance' => $customer->current_balance,
            'credit_limit' => $customer->credit_limit,
            'available_credit' => $customer->getCreditAvailable(),
            'new_balance' => $newBalance,
            'exceeded_by' => $limitExceeded ? ($newBalance - $customer->credit_limit) : 0,
            'warning' => $limitExceeded ? "El límite de crédito será excedido por L. " . number_format($newBalance - $customer->credit_limit, 2) : null,
        ];
    }

    /**
     * Create credit sale record when a sale is made on credit
     *
     * @param Sale $sale
     * @param Customer $customer
     * @return CreditSale
     */
    public function createCreditSale(Sale $sale, Customer $customer): CreditSale
    {
        return DB::transaction(function () use ($sale, $customer) {
            // Calculate due date based on customer's credit days
            $dueDate = Carbon::parse($sale->sold_at)->addDays($customer->credit_days ?? 30);

            // Create credit sale record
            $creditSale = CreditSale::create([
                'tenant_id' => $sale->tenant_id,
                'sale_id' => $sale->id,
                'customer_id' => $customer->id,
                'original_amount' => $sale->total,
                'amount_paid' => 0,
                'balance_due' => $sale->total,
                'due_date' => $dueDate,
                'status' => 'pending',
            ]);

            // Update customer balance
            $customer->increment('current_balance', $sale->total);

            return $creditSale;
        });
    }

    /**
     * Update overdue status for all credit sales
     * Should be run daily via scheduled task
     *
     * @param int $tenantId
     * @return int Number of sales updated
     */
    public function updateOverdueStatus(int $tenantId): int
    {
        $now = Carbon::now();

        $updated = CreditSale::where('tenant_id', $tenantId)
            ->where('status', '!=', 'paid')
            ->where('balance_due', '>', 0)
            ->where('due_date', '<', $now)
            ->update([
                'status' => 'overdue',
                'days_overdue' => DB::raw("DATEDIFF(NOW(), due_date)"),
            ]);

        return $updated;
    }
}
