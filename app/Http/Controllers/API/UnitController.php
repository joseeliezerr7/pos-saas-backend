<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class UnitController extends Controller
{
    public function index(): JsonResponse
    {
        $units = Unit::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $units,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'abbreviation' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $unit = Unit::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $request->name,
            'abbreviation' => $request->abbreviation,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Unidad creada exitosamente',
            'data' => $unit,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $unit = Unit::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'abbreviation' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $unit->update([
            'name' => $request->name,
            'abbreviation' => $request->abbreviation,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Unidad actualizada exitosamente',
            'data' => $unit,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $unit = Unit::findOrFail($id);

        // Check if unit is being used by products
        if ($unit->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar una unidad que está siendo utilizada por productos'
            ], 422);
        }

        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Unidad eliminada exitosamente',
        ]);
    }
}
