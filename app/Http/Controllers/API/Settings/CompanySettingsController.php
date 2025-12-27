<?php

namespace App\Http\Controllers\API\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CompanySettingsController extends Controller
{
    /**
     * Get current company settings
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
     * Update company settings
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
            'settings' => 'nullable|array',
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
            'settings'
        ]));

        return response()->json([
            'success' => true,
            'data' => $company,
            'message' => 'Configuración actualizada exitosamente'
        ]);
    }

    /**
     * Upload company logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $company = Company::find(auth()->user()->tenant_id);

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada'
            ], 404);
        }

        // Delete old logo if exists
        if ($company->logo && Storage::disk('public')->exists($company->logo)) {
            Storage::disk('public')->delete($company->logo);
        }

        // Store new logo
        $file = $request->file('logo');
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('logos', $filename, 'public');

        $company->update(['logo' => $path]);

        return response()->json([
            'success' => true,
            'data' => [
                'logo' => $path,
                'url' => Storage::url($path)
            ],
            'message' => 'Logo actualizado exitosamente'
        ]);
    }

    /**
     * Delete company logo
     */
    public function deleteLogo(): JsonResponse
    {
        $company = Company::find(auth()->user()->tenant_id);

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada'
            ], 404);
        }

        if ($company->logo && Storage::disk('public')->exists($company->logo)) {
            Storage::disk('public')->delete($company->logo);
        }

        $company->update(['logo' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Logo eliminado exitosamente'
        ]);
    }
}
