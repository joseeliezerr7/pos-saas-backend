<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Credit\CreditSale;
use App\Models\Credit\CustomerPayment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreditReportService
{
    /**
     * Generate customer account statement
     *
     * @param int $customerId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getCustomerStatement(int $customerId, string $startDate, string $endDate): array
    {
        $customer = Customer::with(['creditSales.sale', 'payments.allocations'])
            ->findOrFail($customerId);

        // Get all credit sales in period
        $creditSales = $customer->creditSales()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('sale')
            ->orderBy('created_at', 'asc')
            ->get();

        // Get all payments in period
        $payments = $customer->payments()
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->with('allocations.creditSale.sale')
            ->orderBy('payment_date', 'asc')
            ->get();

        // Calculate running balance
        $transactions = [];
        $runningBalance = 0;

        // Merge and sort transactions
        foreach ($creditSales as $creditSale) {
            $transactions[] = [
                'date' => $creditSale->created_at,
                'type' => 'sale',
                'reference' => $creditSale->sale->sale_number,
                'document_number' => $creditSale->sale->sale_number,
                'description' => 'Venta al crédito',
                'due_date' => $creditSale->due_date,
                'charge' => $creditSale->original_amount,
                'payment' => 0,
                'amount' => $creditSale->original_amount,
                'balance' => $runningBalance += $creditSale->original_amount,
            ];
        }

        foreach ($payments as $payment) {
            $transactions[] = [
                'date' => $payment->payment_date,
                'type' => 'payment',
                'reference' => $payment->payment_number,
                'document_number' => $payment->payment_number,
                'description' => 'Pago de cuenta - ' . ucfirst($payment->payment_method),
                'due_date' => null,
                'charge' => 0,
                'payment' => $payment->amount,
                'amount' => $payment->amount,
                'balance' => $runningBalance -= $payment->amount,
            ];
        }

        // Sort by date
        usort($transactions, fn($a, $b) => $a['date'] <=> $b['date']);

        return [
            'customer' => $customer,
            'period' => ['start' => $startDate, 'end' => $endDate],
            'transactions' => $transactions,
            'summary' => [
                'opening_balance' => 0, // Could calculate from prior period
                'total_charges' => $creditSales->sum('original_amount'),
                'total_payments' => $payments->sum('amount'),
                'closing_balance' => $customer->current_balance,
            ],
        ];
    }

    /**
     * Generate aging report (accounts receivable by age)
     *
     * @param int $tenantId
     * @param int|null $branchId
     * @return array
     */
    public function getAgingReport(int $tenantId, ?int $branchId = null): array
    {
        $now = Carbon::now();

        $query = CreditSale::where('tenant_id', $tenantId)
            ->where('balance_due', '>', 0)
            ->with(['customer', 'sale']);

        if ($branchId) {
            $query->whereHas('sale', fn($q) => $q->where('branch_id', $branchId));
        }

        $creditSales = $query->get();

        $aging = [
            'current' => ['count' => 0, 'amount' => 0, 'sales' => []],
            '1_30_days' => ['count' => 0, 'amount' => 0, 'sales' => []],
            '31_60_days' => ['count' => 0, 'amount' => 0, 'sales' => []],
            '60_plus_days' => ['count' => 0, 'amount' => 0, 'sales' => []],
        ];

        foreach ($creditSales as $sale) {
            $daysOverdue = $now->diffInDays($sale->due_date, false);

            if ($daysOverdue <= 0) {
                $bucket = 'current';
            } elseif ($daysOverdue <= 30) {
                $bucket = '1_30_days';
            } elseif ($daysOverdue <= 60) {
                $bucket = '31_60_days';
            } else {
                $bucket = '60_plus_days';
            }

            $aging[$bucket]['count']++;
            $aging[$bucket]['amount'] += $sale->balance_due;
            $aging[$bucket]['sales'][] = [
                'sale_number' => $sale->sale->sale_number,
                'customer_name' => $sale->customer->name,
                'due_date' => $sale->due_date->format('Y-m-d'),
                'days_overdue' => max(0, $daysOverdue),
                'balance_due' => $sale->balance_due,
            ];
        }

        return [
            'as_of_date' => $now->format('Y-m-d'),
            'aging_buckets' => $aging,
            'total_receivable' => array_sum(array_column($aging, 'amount')),
        ];
    }

    /**
     * Get accounts receivable dashboard data
     *
     * @param int $tenantId
     * @return array
     */
    public function getDashboardStats(int $tenantId): array
    {
        $now = Carbon::now();

        // Total receivable
        $totalReceivable = Customer::where('tenant_id', $tenantId)
            ->sum('current_balance');

        // Total credit sales count
        $totalCreditSales = CreditSale::where('tenant_id', $tenantId)
            ->where('balance_due', '>', 0)
            ->count();

        // Overdue sales
        $overdueSales = CreditSale::where('tenant_id', $tenantId)
            ->overdue()
            ->get();

        $overdueAmount = $overdueSales->sum('balance_due');
        $overdueCount = $overdueSales->count();

        // Customers with credit limit exceeded
        $customersOverLimit = Customer::where('tenant_id', $tenantId)
            ->whereRaw('current_balance > credit_limit')
            ->where('credit_limit', '>', 0)
            ->get();

        // Payments this month
        $paymentsThisMonth = CustomerPayment::where('tenant_id', $tenantId)
            ->whereYear('payment_date', $now->year)
            ->whereMonth('payment_date', $now->month)
            ->get();

        // Aging buckets (simplified)
        $agingReport = $this->getAgingReport($tenantId);

        // Top customers with balance
        $topCustomers = Customer::where('tenant_id', $tenantId)
            ->where('current_balance', '>', 0)
            ->orderBy('current_balance', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'balance' => $customer->current_balance,
                    'pending_sales' => CreditSale::where('customer_id', $customer->id)
                        ->where('balance_due', '>', 0)
                        ->count(),
                ];
            });

        // Recent payments
        $recentPayments = CustomerPayment::where('tenant_id', $tenantId)
            ->with(['customer', 'user'])
            ->orderBy('payment_date', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'customer_name' => $payment->customer->name,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->payment_date->format('Y-m-d'),
                    'payment_method' => ucfirst($payment->payment_method),
                ];
            });

        return [
            'total_receivable' => $totalReceivable,
            'total_credit_sales' => $totalCreditSales,
            'overdue_amount' => $overdueAmount,
            'overdue_count' => $overdueCount,
            'customers_over_limit' => [
                'count' => $customersOverLimit->count(),
                'customers' => $customersOverLimit->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'credit_limit' => $c->credit_limit,
                    'current_balance' => $c->current_balance,
                    'over_limit_by' => $c->current_balance - $c->credit_limit,
                ]),
            ],
            'payments_this_month' => $paymentsThisMonth->sum('amount'),
            'payment_count_this_month' => $paymentsThisMonth->count(),
            'aging' => $agingReport['aging_buckets'],
            'top_customers' => $topCustomers,
            'recent_payments' => $recentPayments,
        ];
    }
}
