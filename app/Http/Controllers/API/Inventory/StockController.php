<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Stock;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StockController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Get all stock for the current tenant
     */
    public function index(Request $request): JsonResponse
    {
        $query = Stock::with(['product.category', 'branch'])
            ->whereHas('product', function($q) {
                $q->where('tenant_id', auth()->user()->tenant_id);
            });

        // Filters
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('product', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // Low stock filter
        if ($request->filled('low_stock') && $request->low_stock) {
            $query->whereHas('product', function($q) {
                $q->whereColumn('stocks.quantity', '<=', 'products.stock_min');
            });
        }

        $perPage = $request->get('per_page', 20);
        $stock = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $stock->items(),
            'meta' => [
                'current_page' => $stock->currentPage(),
                'last_page' => $stock->lastPage(),
                'per_page' => $stock->perPage(),
                'total' => $stock->total(),
            ],
        ]);
    }

    /**
     * Get stock by branch
     */
    public function byBranch(int $branchId): JsonResponse
    {
        $stock = Stock::with(['product.category', 'branch'])
            ->where('branch_id', $branchId)
            ->whereHas('product', function($q) {
                $q->where('tenant_id', auth()->user()->tenant_id);
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stock,
        ]);
    }

    /**
     * Adjust stock (increase or decrease)
     */
    public function adjust(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|numeric',
            'type' => 'required|in:increase,decrease',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $result = $this->inventoryService->adjustStock(
                $request->branch_id,
                $request->product_id,
                $request->quantity,
                $request->type,
                $request->reason,
                $request->variant_id
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Stock ajustado exitosamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al ajustar stock: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Transfer stock between branches
     */
    public function transfer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from_branch_id' => 'required|exists:branches,id',
            'to_branch_id' => 'required|exists:branches,id|different:from_branch_id',
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $result = $this->inventoryService->transferStock(
                $request->from_branch_id,
                $request->to_branch_id,
                $request->product_id,
                $request->quantity,
                $request->notes,
                $request->variant_id
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Transferencia realizada exitosamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error en la transferencia: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get stock movements history
     */
    public function movements(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'sometimes|exists:branches,id',
            'product_id' => 'sometimes|exists:products,id',
            'type' => 'sometimes|in:entry,exit,adjustment,transfer_in,transfer_out',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $movements = $this->inventoryService->getStockMovements(
            $request->branch_id,
            $request->product_id,
            $request->type,
            $request->date_from,
            $request->date_to,
            $request->get('per_page', 20)
        );

        return response()->json([
            'success' => true,
            'data' => $movements->items(),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
            ],
        ]);
    }

    /**
     * Get products with low stock
     */
    public function lowStock(Request $request): JsonResponse
    {
        $branchId = $request->get('branch_id');

        $query = Product::with(['category', 'unit', 'stock' => function($q) use ($branchId) {
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
            }])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('track_stock', true)
            ->where('is_active', true);

        $products = $query->get()->filter(function($product) {
            $totalStock = $product->stock->sum('quantity');
            return $totalStock <= $product->stock_min;
        })->map(function($product) {
            $totalStock = $product->stock->sum('quantity');
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'category' => $product->category?->name,
                'unit' => $product->unit?->name,
                'current_stock' => $totalStock,
                'stock_min' => $product->stock_min,
                'stock_max' => $product->stock_max,
                'difference' => $product->stock_min - $totalStock,
                'status' => $totalStock <= 0 ? 'out_of_stock' : 'low_stock',
                'stock_by_branch' => $product->stock->map(function($s) {
                    return [
                        'branch_id' => $s->branch_id,
                        'branch_name' => $s->branch?->name,
                        'quantity' => $s->quantity,
                    ];
                }),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $products,
            'meta' => [
                'total' => $products->count(),
                'out_of_stock' => $products->where('status', 'out_of_stock')->count(),
                'low_stock' => $products->where('status', 'low_stock')->count(),
            ],
        ]);
    }

    /**
     * Get current stock for a specific product
     */
    public function getProductStock(int $productId, Request $request): JsonResponse
    {
        $product = Product::where('tenant_id', auth()->user()->tenant_id)
            ->with(['stock.branch', 'variants.stock.branch'])
            ->findOrFail($productId);

        $branchId = $request->get('branch_id');

        $stockData = [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'has_variants' => $product->has_variants,
            'total_stock' => 0,
            'branches' => [],
            'variants' => [],
        ];

        if ($product->has_variants) {
            foreach ($product->variants as $variant) {
                $variantStock = $variant->stock;
                if ($branchId) {
                    $variantStock = $variantStock->where('branch_id', $branchId);
                }

                $stockData['variants'][] = [
                    'variant_id' => $variant->id,
                    'variant_name' => $variant->name,
                    'total_stock' => $variantStock->sum('quantity'),
                    'branches' => $variantStock->map(function($s) {
                        return [
                            'branch_id' => $s->branch_id,
                            'branch_name' => $s->branch->name,
                            'quantity' => $s->quantity,
                        ];
                    }),
                ];

                $stockData['total_stock'] += $variantStock->sum('quantity');
            }
        } else {
            $productStock = $product->stock;
            if ($branchId) {
                $productStock = $productStock->where('branch_id', $branchId);
            }

            $stockData['total_stock'] = $productStock->sum('quantity');
            $stockData['branches'] = $productStock->map(function($s) {
                return [
                    'branch_id' => $s->branch_id,
                    'branch_name' => $s->branch->name,
                    'quantity' => $s->quantity,
                ];
            });
        }

        return response()->json([
            'success' => true,
            'data' => $stockData,
        ]);
    }
}
