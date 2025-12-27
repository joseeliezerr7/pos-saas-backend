<?php

namespace App\Http\Controllers\API\Report;

use App\Http\Controllers\Controller;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Sale\ProductReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    /**
     * Generate sales report
     */
    public function salesReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'branch_id' => 'nullable|exists:branches,id',
            'payment_method' => 'nullable|string',
            'group_by' => 'nullable|in:day,week,month,user,branch',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = auth()->user()->tenant_id;
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;
        $branchId = $request->branch_id;
        $paymentMethod = $request->payment_method;
        $groupBy = $request->group_by;

        // Base query
        $salesQuery = Sale::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($branchId) {
            $salesQuery->where('branch_id', $branchId);
        }

        if ($paymentMethod) {
            $salesQuery->where('payment_method', $paymentMethod);
        }

        $sales = $salesQuery->with(['user', 'branch'])->get();

        // Get returns for the same period
        $returnsQuery = ProductReturn::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('returned_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($branchId) {
            $returnsQuery->where('branch_id', $branchId);
        }

        $returns = $returnsQuery->with('details')->get();

        // Calculate summary
        $totalSales = $sales->count();
        $totalAmount = $sales->sum('total');
        $totalReturns = $returns->count();
        $totalReturnsAmount = $returns->sum('refund_amount');
        $netAmount = $totalAmount - $totalReturnsAmount;
        $averageSale = $totalSales > 0 ? $totalAmount / $totalSales : 0;

        // Get total items sold
        $totalItemsSold = SaleItem::whereIn('sale_id', $sales->pluck('id'))->sum('quantity');

        // Get total items returned
        $totalItemsReturned = DB::table('return_details')
            ->whereIn('return_id', $returns->pluck('id'))
            ->sum('quantity_returned');

        // Group sales by payment method
        $paymentMethods = [];
        foreach ($sales->groupBy('payment_method') as $method => $methodSales) {
            $paymentMethods[$method] = [
                'count' => $methodSales->count(),
                'total' => $methodSales->sum('total'),
            ];
        }

        // Get top products
        $topProducts = SaleItem::select('product_id', 'product_name')
            ->selectRaw('SUM(quantity) as quantity_sold')
            ->selectRaw('SUM(subtotal) as total_revenue')
            ->whereIn('sale_id', $sales->pluck('id'))
            ->groupBy('product_id', 'product_name')
            ->orderBy('quantity_sold', 'desc')
            ->limit(10)
            ->get();

        // Group data
        $groupedData = null;
        if ($groupBy) {
            $groupedData = $this->groupSalesData($sales, $groupBy);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_sales' => $totalSales,
                    'total_amount' => round($totalAmount, 2),
                    'total_returns' => $totalReturns,
                    'total_returns_amount' => round($totalReturnsAmount, 2),
                    'net_amount' => round($netAmount, 2),
                    'average_sale' => round($averageSale, 2),
                    'total_items_sold' => $totalItemsSold,
                    'total_items_returned' => $totalItemsReturned,
                    'net_items_sold' => $totalItemsSold - $totalItemsReturned,
                ],
                'payment_methods' => $paymentMethods,
                'top_products' => $topProducts,
                'grouped_data' => $groupedData,
                'returns' => $returns->map(function ($return) {
                    return [
                        'id' => $return->id,
                        'return_number' => $return->return_number,
                        'returned_at' => $return->returned_at,
                        'total' => $return->total,
                        'refund_amount' => $return->refund_amount,
                        'items_count' => $return->details->count(),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Group sales data based on group_by parameter
     */
    private function groupSalesData($sales, $groupBy)
    {
        $grouped = [];

        switch ($groupBy) {
            case 'day':
                foreach ($sales->groupBy(function ($sale) {
                    return $sale->created_at->format('Y-m-d');
                }) as $date => $dateSales) {
                    $grouped[$date] = [
                        'count' => $dateSales->count(),
                        'total' => round($dateSales->sum('total'), 2),
                    ];
                }
                break;

            case 'week':
                foreach ($sales->groupBy(function ($sale) {
                    return $sale->created_at->format('Y-W');
                }) as $week => $weekSales) {
                    $grouped["Semana $week"] = [
                        'count' => $weekSales->count(),
                        'total' => round($weekSales->sum('total'), 2),
                    ];
                }
                break;

            case 'month':
                foreach ($sales->groupBy(function ($sale) {
                    return $sale->created_at->format('Y-m');
                }) as $month => $monthSales) {
                    $grouped[$month] = [
                        'count' => $monthSales->count(),
                        'total' => round($monthSales->sum('total'), 2),
                    ];
                }
                break;

            case 'user':
                foreach ($sales->groupBy('user_id') as $userId => $userSales) {
                    $userName = $userSales->first()->user->name ?? 'Desconocido';
                    $grouped[$userName] = [
                        'count' => $userSales->count(),
                        'total' => round($userSales->sum('total'), 2),
                    ];
                }
                break;

            case 'branch':
                foreach ($sales->groupBy('branch_id') as $branchId => $branchSales) {
                    $branchName = $branchSales->first()->branch->name ?? 'Desconocido';
                    $grouped[$branchName] = [
                        'count' => $branchSales->count(),
                        'total' => round($branchSales->sum('total'), 2),
                    ];
                }
                break;
        }

        return $grouped;
    }

    /**
     * Download a generated report
     */
    public function download(int $id): JsonResponse
    {
        // In a full implementation, this would:
        // 1. Fetch the report from a reports table
        // 2. Check permissions
        // 3. Generate/retrieve the file (PDF, Excel, etc.)
        // 4. Return the file for download

        // For now, returning a placeholder response
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'NOT_IMPLEMENTED',
                'message' => 'La funcionalidad de descarga de reportes estará disponible próximamente',
            ],
        ], 501);
    }
}
