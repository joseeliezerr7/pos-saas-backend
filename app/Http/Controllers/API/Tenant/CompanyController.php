<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompanyController extends Controller
{
    /**
     * Get current company information
     */
    public function show(): JsonResponse
    {
        $company = Company::with(['branches', 'plan', 'subscription'])
            ->find(auth()->user()->tenant_id);

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $company
        ]);
    }

    /**
     * Update company information
     */
    public function update(Request $request): JsonResponse
    {
        $company = Company::find(auth()->user()->tenant_id);

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'legal_name' => 'sometimes|string|max:255',
            'rtn' => 'sometimes|string|size:14',
            'address' => 'sometimes|string',
            'phone' => 'sometimes|string|max:20',
            'email' => 'sometimes|email',
            'website' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $company->update($request->only([
            'name',
            'legal_name',
            'rtn',
            'address',
            'phone',
            'email',
            'website',
        ]));

        return response()->json([
            'success' => true,
            'data' => $company,
            'message' => 'Empresa actualizada exitosamente'
        ]);
    }
}
