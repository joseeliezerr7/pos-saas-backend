<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Display a listing of products
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'brand', 'productUnits.unit']);

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by brand
        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        // Add stock information
        $products->getCollection()->transform(function ($product) {
            $product->total_stock = $product->stock()->sum('quantity');
            return $product;
        });

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Search products (for POS)
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->get('q', '');

        $products = Product::with(['category'])
            ->active()
            ->search($search)
            ->limit(20)
            ->get()
            ->map(function ($product) {
                $product->total_stock = $product->stock()->sum('quantity');
                return $product;
            });

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Find product by barcode
     */
    public function findByBarcode(string $barcode): JsonResponse
    {
        $product = Product::with(['category'])
            ->where('barcode', $barcode)
            ->active()
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado'
            ], 404);
        }

        $product->total_stock = $product->stock()->sum('quantity');

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Store a newly created product
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'stock' => 'nullable|integer|min:0',
            'stock_min' => 'nullable|integer|min:0',
            'stock_max' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'is_active' => 'boolean',
            'is_service' => 'boolean',
            'manage_stock' => 'boolean',
            'units' => 'nullable|array',
            'units.*.unit_id' => 'required|exists:units,id',
            'units.*.quantity' => 'required|numeric|min:0.01',
            'units.*.price' => 'required|numeric|min:0',
            'units.*.barcode' => 'nullable|string|max:100',
            'units.*.is_base_unit' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::create([
            'tenant_id' => auth()->user()->tenant_id,
            'category_id' => $request->category_id,
            'brand_id' => $request->brand_id,
            'name' => $request->name,
            'sku' => $request->sku,
            'barcode' => $request->barcode,
            'description' => $request->description,
            'image' => $request->image,
            'price' => $request->price,
            'cost' => $request->cost ?? 0,
            'tax_rate' => $request->tax_rate ?? 0,
            'stock' => $request->stock ?? 0,
            'stock_min' => $request->stock_min ?? 0,
            'stock_max' => $request->stock_max ?? 0,
            'is_active' => $request->is_active ?? true,
            'is_service' => $request->is_service ?? false,
            'manage_stock' => $request->manage_stock ?? true,
        ]);

        // Create product units
        if ($request->has('units') && is_array($request->units)) {
            foreach ($request->units as $unit) {
                $product->productUnits()->create([
                    'unit_id' => $unit['unit_id'],
                    'quantity' => $unit['quantity'],
                    'price' => $unit['price'],
                    'barcode' => $unit['barcode'] ?? null,
                    'is_base_unit' => $unit['is_base_unit'] ?? false,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Producto creado exitosamente',
            'data' => $product->load(['category', 'productUnits.unit']),
        ], 201);
    }

    /**
     * Display the specified product
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::with(['category', 'brand', 'stock', 'productUnits.unit'])->findOrFail($id);
        $product->total_stock = $product->stock()->sum('quantity');

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'sku' => 'nullable|string|max:100|unique:products,sku,' . $id,
            'barcode' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'stock' => 'nullable|integer|min:0',
            'stock_min' => 'nullable|integer|min:0',
            'stock_max' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'is_active' => 'boolean',
            'is_service' => 'boolean',
            'manage_stock' => 'boolean',
            'units' => 'nullable|array',
            'units.*.unit_id' => 'required|exists:units,id',
            'units.*.quantity' => 'required|numeric|min:0.01',
            'units.*.price' => 'required|numeric|min:0',
            'units.*.barcode' => 'nullable|string|max:100',
            'units.*.is_base_unit' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product->update([
            'category_id' => $request->category_id,
            'brand_id' => $request->brand_id,
            'name' => $request->name,
            'sku' => $request->sku,
            'barcode' => $request->barcode,
            'description' => $request->description,
            'image' => $request->image,
            'price' => $request->price,
            'cost' => $request->cost ?? 0,
            'tax_rate' => $request->tax_rate ?? 0,
            'stock' => $request->stock ?? 0,
            'stock_min' => $request->stock_min ?? 0,
            'stock_max' => $request->stock_max ?? 0,
            'is_active' => $request->is_active ?? true,
            'is_service' => $request->is_service ?? false,
            'manage_stock' => $request->manage_stock ?? true,
        ]);

        // Update product units
        if ($request->has('units') && is_array($request->units)) {
            // Delete existing units
            $product->productUnits()->delete();

            // Create new units
            foreach ($request->units as $unit) {
                $product->productUnits()->create([
                    'unit_id' => $unit['unit_id'],
                    'quantity' => $unit['quantity'],
                    'price' => $unit['price'],
                    'barcode' => $unit['barcode'] ?? null,
                    'is_base_unit' => $unit['is_base_unit'] ?? false,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Producto actualizado exitosamente',
            'data' => $product->load(['category', 'productUnits.unit']),
        ]);
    }

    /**
     * Remove the specified product
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        // Check if product has stock movements or sales
        // For now, allow deletion

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado exitosamente',
        ]);
    }
}
