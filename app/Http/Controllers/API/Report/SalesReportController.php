<?php

namespace App\Http\Controllers\API\Report;

use App\Http\Controllers\Controller;
use App\Models\Sale\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesReportController extends Controller
{
    /**
     * Generate sales report
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'branch_id' => 'nullable|exists:branches,id',
            'user_id' => 'nullable|exists:users,id',
            'payment_method' => 'nullable|string|in:cash,card,transfer,credit,qr,mixed',
            'group_by' => 'nullable|string|in:day,week,month,user,branch,product',
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

        $query = Sale::with(['details.product', 'user', 'branch'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$request->date_from . ' 00:00:00', $request->date_to . ' 23:59:59']);

        // Filter by user's branch (if user has assigned branch)
        $userBranchId = auth()->user()->branch_id;
        if ($userBranchId) {
            $query->where('branch_id', $userBranchId);
        } elseif ($request->filled('branch_id')) {
            // Admin can filter by specific branch
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $sales = $query->get();

        // Calculate summary
        $summary = [
            'total_sales' => $sales->count(),
            'total_amount' => $sales->sum('total'),
            'total_tax' => $sales->sum('tax'),
            'total_discount' => $sales->sum('discount'),
            'total_items_sold' => $sales->sum(function ($sale) {
                return $sale->details->sum('quantity');
            }),
            'average_sale' => $sales->count() > 0 ? $sales->avg('total') : 0,
        ];

        // Group by if specified
        $grouped = null;
        if ($request->filled('group_by')) {
            $grouped = $this->groupSales($sales, $request->group_by);
        }

        // Payment method breakdown
        $paymentMethods = $sales->groupBy('payment_method')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('total'),
            ];
        });

        // Top products
        $topProducts = $this->getTopProducts($sales, 10);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'payment_methods' => $paymentMethods,
                'top_products' => $topProducts,
                'grouped_data' => $grouped,
                'period' => [
                    'from' => $request->date_from,
                    'to' => $request->date_to,
                ],
            ],
        ]);
    }

    /**
     * Group sales by specified criteria
     */
    protected function groupSales($sales, string $groupBy): array
    {
        switch ($groupBy) {
            case 'day':
                return $sales->groupBy(fn($sale) => $sale->created_at->format('Y-m-d'))
                    ->map(fn($group) => [
                        'count' => $group->count(),
                        'total' => $group->sum('total'),
                    ])
                    ->toArray();

            case 'week':
                return $sales->groupBy(fn($sale) => $sale->created_at->format('Y-W'))
                    ->map(fn($group) => [
                        'count' => $group->count(),
                        'total' => $group->sum('total'),
                    ])
                    ->toArray();

            case 'month':
                return $sales->groupBy(fn($sale) => $sale->created_at->format('Y-m'))
                    ->map(fn($group) => [
                        'count' => $group->count(),
                        'total' => $group->sum('total'),
                    ])
                    ->toArray();

            case 'user':
                return $sales->groupBy('user_id')
                    ->map(fn($group) => [
                        'user_name' => $group->first()->user->name ?? 'Unknown',
                        'count' => $group->count(),
                        'total' => $group->sum('total'),
                    ])
                    ->values()
                    ->toArray();

            case 'branch':
                return $sales->groupBy('branch_id')
                    ->map(fn($group) => [
                        'branch_name' => $group->first()->branch->name ?? 'Unknown',
                        'count' => $group->count(),
                        'total' => $group->sum('total'),
                    ])
                    ->values()
                    ->toArray();

            default:
                return [];
        }
    }

    /**
     * Get top selling products
     */
    protected function getTopProducts($sales, int $limit = 10): array
    {
        $products = [];

        foreach ($sales as $sale) {
            foreach ($sale->details as $detail) {
                $productId = $detail->product_id;

                if (!isset($products[$productId])) {
                    $products[$productId] = [
                        'product_id' => $productId,
                        'product_name' => $detail->product_name,
                        'quantity_sold' => 0,
                        'total_revenue' => 0,
                    ];
                }

                $products[$productId]['quantity_sold'] += $detail->quantity;
                $products[$productId]['total_revenue'] += $detail->subtotal;
            }
        }

        usort($products, fn($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);

        return array_slice($products, 0, $limit);
    }
}
