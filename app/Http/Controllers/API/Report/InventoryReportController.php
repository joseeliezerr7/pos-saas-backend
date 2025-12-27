<?php

namespace App\Http\Controllers\API\Report;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Stock;
use App\Models\Inventory\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InventoryReportController extends Controller
{
    /**
     * Generate inventory report
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'nullable|string|in:all,in_stock,low_stock,out_of_stock',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
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

        // Get stock for branch
        $stockQuery = Stock::with(['product.category'])
            ->where('branch_id', $request->branch_id);

        // Filter by category if specified
        if ($request->filled('category_id')) {
            $stockQuery->whereHas('product', function ($q) use ($request) {
                $q->where('category_id', $request->category_id);
            });
        }

        $stocks = $stockQuery->get();

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $stocks = $stocks->filter(function ($stock) use ($request) {
                switch ($request->status) {
                    case 'in_stock':
                        return $stock->quantity > $stock->product->stock_min;
                    case 'low_stock':
                        return $stock->quantity <= $stock->product->stock_min && $stock->quantity > 0;
                    case 'out_of_stock':
                        return $stock->quantity <= 0;
                    default:
                        return true;
                }
            });
        }

        // Calculate summary
        $summary = [
            'total_products' => $stocks->count(),
            'total_value' => $stocks->sum(function ($stock) {
                return $stock->quantity * $stock->product->price;
            }),
            'total_cost' => $stocks->sum(function ($stock) {
                return $stock->quantity * $stock->product->cost;
            }),
            'in_stock_count' => $stocks->filter(fn($s) => $s->quantity > $s->product->stock_min)->count(),
            'low_stock_count' => $stocks->filter(fn($s) => $s->quantity <= $s->product->stock_min && $s->quantity > 0)->count(),
            'out_of_stock_count' => $stocks->filter(fn($s) => $s->quantity <= 0)->count(),
        ];

        // Stock movements if date range specified
        $movements = null;
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $movementsQuery = StockMovement::with(['product'])
                ->where('branch_id', $request->branch_id)
                ->whereBetween('created_at', [$request->date_from, $request->date_to]);

            $movements = $movementsQuery->get()->groupBy('movement_type')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_quantity' => $group->sum('quantity'),
                ];
            });
        }

        // Products detail
        $products = $stocks->map(function ($stock) {
            return [
                'product_id' => $stock->product_id,
                'product_name' => $stock->product->name,
                'sku' => $stock->product->sku,
                'category' => $stock->product->category->name ?? null,
                'quantity' => $stock->quantity,
                'min_stock' => $stock->product->stock_min,
                'cost' => (float) $stock->product->cost,
                'price' => (float) $stock->product->price,
                'total_value' => $stock->quantity * $stock->product->price,
                'status' => $this->getStockStatus($stock),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'products' => $products,
                'movements' => $movements,
            ],
        ]);
    }

    /**
     * Get stock status for a product
     */
    protected function getStockStatus($stock): string
    {
        if ($stock->quantity <= 0) {
            return 'out_of_stock';
        }

        if ($stock->quantity <= $stock->product->stock_min) {
            return 'low_stock';
        }

        return 'in_stock';
    }
}
