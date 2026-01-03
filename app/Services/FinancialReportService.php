<?php

namespace App\Services;

use App\Models\Sale\Sale;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\CashRegister\CashOpening;
use App\Models\Catalog\Product;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialReportService
{
    /**
     * Generar Estado de Resultados (P&L - Profit & Loss)
     */
    public function getProfitAndLoss($companyId, $startDate, $endDate, $branchId = null)
    {
        $startDate = Carbon::parse($startDate)->startOfDay();
        $endDate = Carbon::parse($endDate)->endOfDay();

        // Ingresos por ventas
        $salesQuery = Sale::where('tenant_id', $companyId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($branchId) {
            $salesQuery->where('branch_id', $branchId);
        }

        $totalRevenue = $salesQuery->sum('total');
        $totalSales = $salesQuery->count();

        // Costo de ventas (productos vendidos)
        $costOfSales = DB::table('sale_details')
            ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->join('products', 'sale_details.product_id', '=', 'products.id')
            ->where('sales.tenant_id', $companyId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->when($branchId, function ($query) use ($branchId) {
                return $query->where('sales.branch_id', $branchId);
            })
            ->sum(DB::raw('sale_details.quantity * products.cost'));

        // Margen bruto
        $grossProfit = $totalRevenue - $costOfSales;
        $grossMarginPercentage = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0;

        // Gastos operativos
        $expensesQuery = Expense::where('tenant_id', $companyId)
            ->whereBetween('expense_date', [$startDate, $endDate]);

        if ($branchId) {
            $expensesQuery->where('branch_id', $branchId);
        }

        $operatingExpenses = $expensesQuery->sum('amount');
        $expensesByCategory = $expensesQuery->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->get()
            ->pluck('total', 'category')
            ->toArray();

        // Utilidad operativa
        $operatingProfit = $grossProfit - $operatingExpenses;

        // Utilidad neta (sin impuestos por ahora)
        $netProfit = $operatingProfit;
        $netMarginPercentage = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'revenue' => [
                'total_sales' => round($totalRevenue, 2),
                'number_of_sales' => $totalSales,
                'average_sale' => $totalSales > 0 ? round($totalRevenue / $totalSales, 2) : 0,
            ],
            'cost_of_sales' => round($costOfSales, 2),
            'gross_profit' => [
                'amount' => round($grossProfit, 2),
                'margin_percentage' => round($grossMarginPercentage, 2),
            ],
            'operating_expenses' => [
                'total' => round($operatingExpenses, 2),
                'by_category' => $expensesByCategory,
            ],
            'operating_profit' => round($operatingProfit, 2),
            'net_profit' => [
                'amount' => round($netProfit, 2),
                'margin_percentage' => round($netMarginPercentage, 2),
            ],
        ];
    }

    /**
     * Generar Balance General (Balance Sheet)
     */
    public function getBalanceSheet($companyId, $asOfDate, $branchId = null)
    {
        $asOfDate = Carbon::parse($asOfDate)->endOfDay();

        // ACTIVOS
        // Efectivo en cajas registradoras
        $cashOpenings = CashOpening::where('tenant_id', $companyId)
            ->where('is_open', true)
            ->when($branchId, function ($query) use ($branchId) {
                // CashOpening no tiene branch_id directo, se relaciona a través de cash_register
                return $query->whereHas('cashRegister', function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                });
            })
            ->get();

        $cashInRegisters = $cashOpenings->sum(function ($opening) {
            return $opening->getExpectedAmount();
        });

        // Inventario (valor del stock)
        $inventoryValue = DB::table('stock')
            ->join('products', 'stock.product_id', '=', 'products.id')
            ->where('products.tenant_id', $companyId)
            ->when($branchId, function ($query) use ($branchId) {
                return $query->where('stock.branch_id', $branchId);
            })
            ->sum(DB::raw('stock.quantity * products.cost'));

        // Cuentas por cobrar (crédito a clientes)
        $accountsReceivable = DB::table('customers')
            ->where('tenant_id', $companyId)
            ->sum('current_balance');

        $totalAssets = $cashInRegisters + $inventoryValue + $accountsReceivable;

        // PASIVOS
        // Cuentas por pagar (simplificado - sería deuda con proveedores)
        $accountsPayable = 0; // Por implementar según necesidad

        $totalLiabilities = $accountsPayable;

        // CAPITAL (Patrimonio)
        $equity = $totalAssets - $totalLiabilities;

        return [
            'as_of_date' => $asOfDate->toDateString(),
            'assets' => [
                'current_assets' => [
                    'cash_in_registers' => round($cashInRegisters, 2),
                    'inventory' => round($inventoryValue, 2),
                    'accounts_receivable' => round($accountsReceivable, 2),
                ],
                'total' => round($totalAssets, 2),
            ],
            'liabilities' => [
                'current_liabilities' => [
                    'accounts_payable' => round($accountsPayable, 2),
                ],
                'total' => round($totalLiabilities, 2),
            ],
            'equity' => [
                'total' => round($equity, 2),
            ],
        ];
    }

    /**
     * Generar Flujo de Caja (Cash Flow Statement)
     */
    public function getCashFlow($companyId, $startDate, $endDate, $branchId = null)
    {
        $startDate = Carbon::parse($startDate)->startOfDay();
        $endDate = Carbon::parse($endDate)->endOfDay();

        // Entradas de efectivo
        // 1. Ventas en efectivo
        $cashSales = Sale::where('tenant_id', $companyId)
            ->where('status', 'completed')
            ->where('payment_method', 'cash')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($branchId, function ($query) use ($branchId) {
                return $query->where('branch_id', $branchId);
            })
            ->sum('total');

        // 2. Cobros de clientes (pagos de crédito)
        $customerPayments = \App\Models\Credit\CustomerPayment::where('tenant_id', $companyId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->when($branchId, function ($query) use ($branchId) {
                return $query->where('branch_id', $branchId);
            })
            ->sum('amount');

        $cashInflows = $cashSales + $customerPayments;

        // Salidas de efectivo
        // 1. Compras
        $purchasesOutflow = Purchase::where('tenant_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($branchId, function ($query) use ($branchId) {
                return $query->where('branch_id', $branchId);
            })
            ->sum('total');

        // 2. Gastos
        $expensesOutflow = Expense::where('tenant_id', $companyId)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->when($branchId, function ($query) use ($branchId) {
                return $query->where('branch_id', $branchId);
            })
            ->sum('amount');

        $totalOutflows = $purchasesOutflow + $expensesOutflow;

        // Flujo neto
        $netCashFlow = $cashInflows - $totalOutflows;

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'operating_activities' => [
                'cash_inflows' => [
                    'cash_sales' => round($cashSales, 2),
                    'customer_payments' => round($customerPayments, 2),
                    'total' => round($cashInflows, 2),
                ],
                'cash_outflows' => [
                    'purchases' => round($purchasesOutflow, 2),
                    'expenses' => round($expensesOutflow, 2),
                    'total' => round($totalOutflows, 2),
                ],
                'net_cash_flow' => round($netCashFlow, 2),
            ],
        ];
    }

    /**
     * Análisis de rentabilidad por producto
     */
    public function getProductProfitability($companyId, $startDate, $endDate, $limit = 20)
    {
        $startDate = Carbon::parse($startDate)->startOfDay();
        $endDate = Carbon::parse($endDate)->endOfDay();

        $products = DB::table('sale_details')
            ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->join('products', 'sale_details.product_id', '=', 'products.id')
            ->where('sales.tenant_id', $companyId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(sale_details.quantity) as units_sold'),
                DB::raw('SUM(sale_details.quantity * sale_details.price) as total_revenue'),
                DB::raw('SUM(sale_details.quantity * products.cost) as total_cost'),
                DB::raw('SUM(sale_details.quantity * (sale_details.price - products.cost)) as total_profit'),
                DB::raw('AVG((sale_details.price - products.cost) / NULLIF(sale_details.price, 0) * 100) as margin_percentage')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_profit')
            ->limit($limit)
            ->get();

        return $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'units_sold' => (int) $product->units_sold,
                'revenue' => round($product->total_revenue, 2),
                'cost' => round($product->total_cost, 2),
                'profit' => round($product->total_profit, 2),
                'margin_percentage' => round($product->margin_percentage, 2),
            ];
        });
    }

    /**
     * Análisis de rentabilidad por categoría
     */
    public function getCategoryProfitability($companyId, $startDate, $endDate)
    {
        $startDate = Carbon::parse($startDate)->startOfDay();
        $endDate = Carbon::parse($endDate)->endOfDay();

        $categories = DB::table('sale_details')
            ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->join('products', 'sale_details.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('sales.tenant_id', $companyId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->select(
                'categories.id',
                'categories.name',
                DB::raw('SUM(sale_details.quantity) as units_sold'),
                DB::raw('SUM(sale_details.quantity * sale_details.price) as total_revenue'),
                DB::raw('SUM(sale_details.quantity * products.cost) as total_cost'),
                DB::raw('SUM(sale_details.quantity * (sale_details.price - products.cost)) as total_profit')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_profit')
            ->get();

        return $categories->map(function ($category) {
            $marginPercentage = $category->total_revenue > 0
                ? (($category->total_revenue - $category->total_cost) / $category->total_revenue) * 100
                : 0;

            return [
                'id' => $category->id,
                'name' => $category->name,
                'units_sold' => (int) $category->units_sold,
                'revenue' => round($category->total_revenue, 2),
                'cost' => round($category->total_cost, 2),
                'profit' => round($category->total_profit, 2),
                'margin_percentage' => round($marginPercentage, 2),
            ];
        });
    }

    /**
     * Análisis de rentabilidad por sucursal
     */
    public function getBranchProfitability($companyId, $startDate, $endDate)
    {
        $startDate = Carbon::parse($startDate)->startOfDay();
        $endDate = Carbon::parse($endDate)->endOfDay();

        $branches = DB::table('sales')
            ->join('branches', 'sales.branch_id', '=', 'branches.id')
            ->leftJoin('sale_details', 'sales.id', '=', 'sale_details.sale_id')
            ->leftJoin('products', 'sale_details.product_id', '=', 'products.id')
            ->where('sales.tenant_id', $companyId)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->select(
                'branches.id',
                'branches.name',
                DB::raw('COUNT(DISTINCT sales.id) as total_sales'),
                DB::raw('SUM(sales.total) as total_revenue'),
                DB::raw('SUM(sale_details.quantity * products.cost) as total_cost')
            )
            ->groupBy('branches.id', 'branches.name')
            ->orderByDesc('total_revenue')
            ->get();

        return $branches->map(function ($branch) {
            $profit = $branch->total_revenue - ($branch->total_cost ?? 0);
            $marginPercentage = $branch->total_revenue > 0
                ? ($profit / $branch->total_revenue) * 100
                : 0;

            return [
                'id' => $branch->id,
                'name' => $branch->name,
                'total_sales' => (int) $branch->total_sales,
                'revenue' => round($branch->total_revenue, 2),
                'cost' => round($branch->total_cost ?? 0, 2),
                'profit' => round($profit, 2),
                'margin_percentage' => round($marginPercentage, 2),
            ];
        });
    }

    /**
     * Comparativo mensual
     */
    public function getMonthlyComparison($companyId, $year, $branchId = null)
    {
        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

            $salesQuery = Sale::where('tenant_id', $companyId)
                ->where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate]);

            if ($branchId) {
                $salesQuery->where('branch_id', $branchId);
            }

            $revenue = $salesQuery->sum('total');
            $salesCount = $salesQuery->count();

            $expensesQuery = Expense::where('tenant_id', $companyId)
                ->whereBetween('expense_date', [$startDate, $endDate]);

            if ($branchId) {
                $expensesQuery->where('branch_id', $branchId);
            }

            $expenses = $expensesQuery->sum('amount');

            $months[] = [
                'month' => $month,
                'month_name' => $startDate->format('F'),
                'revenue' => round($revenue, 2),
                'expenses' => round($expenses, 2),
                'profit' => round($revenue - $expenses, 2),
                'sales_count' => $salesCount,
            ];
        }

        return $months;
    }
}
