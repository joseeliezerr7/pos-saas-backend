<?php

namespace App\Http\Controllers\API\Report;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FinancialReportController extends Controller
{
    /**
     * Generate financial report
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Los datos proporcionados no son válidos',
                    'errors' => $validator->errors(),
                ],
            ], 422);
        }

        $tenantId = auth()->user()->tenant_id;
        $userBranchId = auth()->user()->branch_id;

        // Determine which branch to filter by
        $filterBranchId = $userBranchId ?? $request->branch_id ?? null;

        // Sales data
        $salesQuery = Sale::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$request->date_from . ' 00:00:00', $request->date_to . ' 23:59:59']);

        if ($filterBranchId) {
            $salesQuery->where('branch_id', $filterBranchId);
        }

        $sales = $salesQuery->get();

        $revenue = [
            'gross_sales' => $sales->sum('subtotal'),
            'discounts' => $sales->sum('discount'),
            'tax' => $sales->sum('tax'),
            'net_sales' => $sales->sum('total'),
            'transactions_count' => $sales->count(),
        ];

        // Cost of goods sold
        $cogs = $sales->sum(function ($sale) {
            return $sale->details->sum(function ($detail) {
                return $detail->cost * $detail->quantity;
            });
        });

        // Purchases data
        $purchasesQuery = Purchase::where('tenant_id', $tenantId)
            ->whereIn('status', ['received', 'completed'])
            ->whereBetween('created_at', [$request->date_from, $request->date_to]);

        if ($filterBranchId) {
            $purchasesQuery->where('branch_id', $filterBranchId);
        }

        $purchases = $purchasesQuery->get();

        // Operating expenses data
        $operatingExpensesQuery = Expense::where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$request->date_from, $request->date_to]);

        if ($filterBranchId) {
            $operatingExpensesQuery->where('branch_id', $filterBranchId);
        }

        $operatingExpenses = $operatingExpensesQuery->get();

        $expenses = [
            'purchases' => $purchases->sum('total'),
            'purchases_count' => $purchases->count(),
            'operating_expenses' => $operatingExpenses->sum('amount'),
            'operating_expenses_count' => $operatingExpenses->count(),
            'total_expenses' => $purchases->sum('total') + $operatingExpenses->sum('amount'),
        ];

        // Calculate profit
        $grossProfit = $revenue['net_sales'] - $cogs;
        $netProfit = $grossProfit - $expenses['total_expenses'];

        // Profit margin
        $grossProfitMargin = $revenue['net_sales'] > 0
            ? ($grossProfit / $revenue['net_sales']) * 100
            : 0;

        $netProfitMargin = $revenue['net_sales'] > 0
            ? ($netProfit / $revenue['net_sales']) * 100
            : 0;

        // Daily breakdown
        $dailyData = $sales->groupBy(function ($sale) {
            return $sale->sold_at->format('Y-m-d');
        })->map(function ($daySales, $date) use ($request, $tenantId) {
            $dayCogs = $daySales->sum(function ($sale) {
                return $sale->details->sum(function ($detail) {
                    return $detail->cost * $detail->quantity;
                });
            });

            $dayPurchases = Purchase::where('tenant_id', $tenantId)
                ->whereIn('status', ['received', 'completed'])
                ->whereDate('created_at', $date)
                ->sum('total');

            $dayOperatingExpenses = Expense::where('tenant_id', $tenantId)
                ->whereDate('expense_date', $date)
                ->when($filterBranchId, fn($q) => $q->where('branch_id', $filterBranchId))
                ->sum('amount');

            $revenue = $daySales->sum('total');
            $totalDayExpenses = $dayPurchases + $dayOperatingExpenses;
            $profit = $revenue - $dayCogs - $totalDayExpenses;

            return [
                'date' => $date,
                'revenue' => $revenue,
                'cogs' => $dayCogs,
                'expenses' => $totalDayExpenses,
                'purchases' => $dayPurchases,
                'operating_expenses' => $dayOperatingExpenses,
                'profit' => $profit,
                'transactions' => $daySales->count(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'revenue' => $revenue,
                    'cogs' => $cogs,
                    'expenses' => $expenses,
                    'gross_profit' => $grossProfit,
                    'net_profit' => $netProfit,
                    'gross_profit_margin' => round($grossProfitMargin, 2),
                    'net_profit_margin' => round($netProfitMargin, 2),
                ],
                'daily_data' => $dailyData,
                'period' => [
                    'from' => $request->date_from,
                    'to' => $request->date_to,
                ],
            ],
        ]);
    }
}
