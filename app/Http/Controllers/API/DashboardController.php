<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale\Sale;
use App\Models\Sale\ProductReturn;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function stats(): JsonResponse
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // Today's sales
        $todaySales = Sale::whereDate('created_at', $today)
            ->where('status', '!=', 'voided')
            ->get();

        $todayTotal = $todaySales->sum('total');
        $todayCount = $todaySales->count();
        $todayCash = $todaySales->where('payment_method', 'cash')->sum('total');
        $todayCard = $todaySales->where('payment_method', 'card')->sum('total');

        // Today's returns (subtract from totals)
        $todayReturns = ProductReturn::whereDate('returned_at', $today)
            ->where('status', 'completed')
            ->sum('refund_amount');

        // This month's sales
        $monthSales = Sale::whereDate('created_at', '>=', $thisMonth)
            ->where('status', '!=', 'voided')
            ->get();

        $monthTotal = $monthSales->sum('total');
        $monthCount = $monthSales->count();

        // This month's returns
        $monthReturns = ProductReturn::whereDate('returned_at', '>=', $thisMonth)
            ->where('status', 'completed')
            ->sum('refund_amount');

        // Last month's sales for comparison
        $lastMonthTotal = Sale::whereDate('created_at', '>=', $lastMonth)
            ->whereDate('created_at', '<=', $lastMonthEnd)
            ->where('status', '!=', 'voided')
            ->sum('total');

        // Last month's returns
        $lastMonthReturns = ProductReturn::whereDate('returned_at', '>=', $lastMonth)
            ->whereDate('returned_at', '<=', $lastMonthEnd)
            ->where('status', 'completed')
            ->sum('refund_amount');

        $netMonthTotal = $monthTotal - $monthReturns;
        $netLastMonthTotal = $lastMonthTotal - $lastMonthReturns;

        $monthChange = $netLastMonthTotal > 0
            ? (($netMonthTotal - $netLastMonthTotal) / $netLastMonthTotal) * 100
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'today' => [
                    'total' => round($todayTotal, 2),
                    'returns' => round($todayReturns, 2),
                    'net_total' => round($todayTotal - $todayReturns, 2),
                    'count' => $todayCount,
                    'average' => $todayCount > 0 ? round($todayTotal / $todayCount, 2) : 0,
                    'cash' => round($todayCash, 2),
                    'card' => round($todayCard, 2),
                ],
                'month' => [
                    'total' => round($monthTotal, 2),
                    'returns' => round($monthReturns, 2),
                    'net_total' => round($netMonthTotal, 2),
                    'count' => $monthCount,
                    'average' => $monthCount > 0 ? round($monthTotal / $monthCount, 2) : 0,
                    'change_percentage' => round($monthChange, 2),
                ],
            ],
        ]);
    }

    /**
     * Get sales chart data (last 7 days)
     */
    public function salesChart(): JsonResponse
    {
        $days = [];
        $totals = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayName = $date->locale('es')->isoFormat('ddd D');

            $dayTotal = Sale::whereDate('created_at', $date->toDateString())
                ->where('status', '!=', 'voided')
                ->sum('total');

            // Subtract returns for this day
            $dayReturns = ProductReturn::whereDate('returned_at', $date->toDateString())
                ->where('status', 'completed')
                ->sum('refund_amount');

            $days[] = $dayName;
            $totals[] = round($dayTotal - $dayReturns, 2);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'labels' => $days,
                'values' => $totals,
            ],
        ]);
    }

    /**
     * Get top 10 selling products
     */
    public function topProducts(): JsonResponse
    {
        $topProducts = DB::table('sale_details')
            ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->join('products', 'sale_details.product_id', '=', 'products.id')
            ->leftJoin('return_details', function ($join) {
                $join->on('sale_details.id', '=', 'return_details.sale_detail_id')
                     ->join('returns', function ($returnJoin) {
                         $returnJoin->on('return_details.return_id', '=', 'returns.id')
                                   ->where('returns.status', '=', 'completed');
                     });
            })
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(sale_details.quantity) as total_quantity'),
                DB::raw('COALESCE(SUM(return_details.quantity_returned), 0) as total_returned'),
                DB::raw('SUM(sale_details.quantity * sale_details.price) as total_revenue'),
                DB::raw('COALESCE(SUM(return_details.subtotal), 0) as returned_revenue')
            )
            ->where('sales.status', '!=', 'voided')
            ->whereDate('sales.created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc(DB::raw('SUM(sale_details.quantity) - COALESCE(SUM(return_details.quantity_returned), 0)'))
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $topProducts->map(function ($product) {
                $netQuantity = $product->total_quantity - $product->total_returned;
                $netRevenue = $product->total_revenue - $product->returned_revenue;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'quantity_sold' => (int) $product->total_quantity,
                    'quantity_returned' => (int) $product->total_returned,
                    'net_quantity' => (int) $netQuantity,
                    'revenue' => round($product->total_revenue, 2),
                    'returned_revenue' => round($product->returned_revenue, 2),
                    'net_revenue' => round($netRevenue, 2),
                ];
            }),
        ]);
    }

    /**
     * Get dashboard alerts
     */
    public function alerts(): JsonResponse
    {
        // For now, return empty alerts
        // TODO: Implement proper stock alerts when stock management system is fully implemented
        $alerts = [];

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }
}
