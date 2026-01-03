<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Branch;
use App\Traits\ValidatesPlanLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BranchController extends Controller
{
    use ValidatesPlanLimits;
    /**
     * Get all branches for the current tenant
     */
    public function index(Request $request): JsonResponse
    {
        $query = Branch::query();

        // Filter by active status if requested
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Filter by main branch if requested
        if ($request->has('is_main')) {
            $query->where('is_main', $request->boolean('is_main'));
        }

        $branches = $query->orderBy('is_main', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $branches
        ]);
    }

    /**
     * Create a new branch
     */
    public function store(Request $request): JsonResponse
    {
        // Validate plan limits before creating branch
        $limitError = $this->validateBranchLimit();
        if ($limitError) {
            return $limitError;
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:20|unique:branches,code,NULL,id,tenant_id,' . auth()->user()->tenant_id,
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'is_main' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // If setting as main branch, unset other main branches
        if ($request->boolean('is_main')) {
            Branch::where('tenant_id', auth()->user()->tenant_id)
                ->where('is_main', true)
                ->update(['is_main' => false]);
        }

        $branch = Branch::create([
            'tenant_id' => auth()->user()->tenant_id,
            'code' => $request->code,
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
            'is_main' => $request->boolean('is_main', false),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'data' => $branch,
            'message' => 'Sucursal creada exitosamente'
        ], 201);
    }

    /**
     * Get a specific branch
     */
    public function show(string $id): JsonResponse
    {
        $branch = Branch::where('tenant_id', auth()->user()->tenant_id)
            ->find($id);

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Sucursal no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $branch
        ]);
    }

    /**
     * Update a branch
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $branch = Branch::where('tenant_id', auth()->user()->tenant_id)
            ->find($id);

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Sucursal no encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|max:20|unique:branches,code,' . $id . ',id,tenant_id,' . auth()->user()->tenant_id,
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'is_main' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // If setting as main branch, unset other main branches
        if ($request->has('is_main') && $request->boolean('is_main')) {
            Branch::where('tenant_id', auth()->user()->tenant_id)
                ->where('id', '!=', $id)
                ->where('is_main', true)
                ->update(['is_main' => false]);
        }

        $branch->update($request->only([
            'code',
            'name',
            'address',
            'phone',
            'email',
            'is_main',
            'is_active',
        ]));

        return response()->json([
            'success' => true,
            'data' => $branch,
            'message' => 'Sucursal actualizada exitosamente'
        ]);
    }

    /**
     * Delete a branch
     */
    public function destroy(string $id): JsonResponse
    {
        $branch = Branch::where('tenant_id', auth()->user()->tenant_id)
            ->find($id);

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Sucursal no encontrada'
            ], 404);
        }

        // Prevent deletion of main branch
        if ($branch->is_main) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la sucursal principal'
            ], 422);
        }

        // Check if branch has related data
        if ($branch->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la sucursal porque tiene usuarios asignados'
            ], 422);
        }

        if ($branch->cashRegisters()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la sucursal porque tiene cajas registradoras'
            ], 422);
        }

        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sucursal eliminada exitosamente'
        ]);
    }
}
