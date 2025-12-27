<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    /**
     * Display a listing of variants for a product
     */
    public function index(Request $request, string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $variants = ProductVariant::where('product_id', $productId)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $variants,
        ]);
    }

    /**
     * Store a newly created variant
     */
    public function store(Request $request, string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:50|unique:product_variants,sku',
            'barcode' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'image' => 'nullable|string',
            'attributes' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $variant = ProductVariant::create([
                'product_id' => $productId,
                'name' => $request->name,
                'sku' => $request->sku,
                'barcode' => $request->barcode,
                'price' => $request->price,
                'cost' => $request->cost ?? 0,
                'image' => $request->image,
                'attributes' => $request->attributes,
                'is_active' => $request->is_active ?? true,
            ]);

            // Update product to indicate it has variants
            $product->update(['has_variants' => true]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Variante creada exitosamente',
                'data' => $variant,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la variante'
            ], 500);
        }
    }

    /**
     * Display the specified variant
     */
    public function show(string $productId, string $id): JsonResponse
    {
        $variant = ProductVariant::where('product_id', $productId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $variant,
        ]);
    }

    /**
     * Update the specified variant
     */
    public function update(Request $request, string $productId, string $id): JsonResponse
    {
        $variant = ProductVariant::where('product_id', $productId)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:50|unique:product_variants,sku,' . $id,
            'barcode' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'image' => 'nullable|string',
            'attributes' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $variant->update([
            'name' => $request->name,
            'sku' => $request->sku,
            'barcode' => $request->barcode,
            'price' => $request->price,
            'cost' => $request->cost ?? 0,
            'image' => $request->image,
            'attributes' => $request->attributes,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Variante actualizada exitosamente',
            'data' => $variant,
        ]);
    }

    /**
     * Remove the specified variant
     */
    public function destroy(string $productId, string $id): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $variant = ProductVariant::where('product_id', $productId)
            ->findOrFail($id);

        DB::beginTransaction();
        try {
            $variant->delete();

            // Check if product still has variants
            $remainingVariants = ProductVariant::where('product_id', $productId)->count();
            if ($remainingVariants === 0) {
                $product->update(['has_variants' => false]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Variante eliminada exitosamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la variante'
            ], 500);
        }
    }
}
