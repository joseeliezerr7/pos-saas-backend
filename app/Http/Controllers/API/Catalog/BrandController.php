<?php

namespace App\Http\Controllers\API\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    /**
     * Display a listing of brands
     */
    public function index(Request $request): JsonResponse
    {
        $query = Brand::withCount('products')
            ->where('tenant_id', auth()->user()->tenant_id);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('is_active')) {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);

        if ($request->get('all') === 'true') {
            $brands = $query->get();
            return response()->json([
                'success' => true,
                'data' => $brands,
            ]);
        }

        $brands = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $brands->items(),
            'meta' => [
                'current_page' => $brands->currentPage(),
                'last_page' => $brands->lastPage(),
                'per_page' => $brands->perPage(),
                'total' => $brands->total(),
            ],
        ]);
    }

    /**
     * Store a newly created brand
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'logo' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
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

        $brand = Brand::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'logo' => $request->logo,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Marca creada exitosamente',
            'data' => $brand,
        ], 201);
    }

    /**
     * Display the specified brand
     */
    public function show(string $id): JsonResponse
    {
        $brand = Brand::withCount('products')
            ->where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $brand,
        ]);
    }

    /**
     * Update the specified brand
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $brand = Brand::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'logo' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
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

        $brand->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'logo' => $request->logo,
            'is_active' => $request->is_active ?? $brand->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Marca actualizada exitosamente',
            'data' => $brand,
        ]);
    }

    /**
     * Remove the specified brand
     */
    public function destroy(string $id): JsonResponse
    {
        $brand = Brand::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        // Check if brand has products
        if ($brand->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BRAND_HAS_PRODUCTS',
                    'message' => 'No se puede eliminar una marca con productos asociados',
                ],
            ], 400);
        }

        $brand->delete();

        return response()->json([
            'success' => true,
            'message' => 'Marca eliminada exitosamente',
        ]);
    }
}
