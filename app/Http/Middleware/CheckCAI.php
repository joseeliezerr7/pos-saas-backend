<?php

namespace App\Http\Middleware;

use App\Models\Fiscal\CAI;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCAI
{
    /**
     * Handle an incoming request.
     *
     * Verifies that there is an active CAI with available correlatives
     * before allowing invoice generation.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check for invoice creation (POST)
        if (!$request->is('api/invoices') || !$request->isMethod('post')) {
            return $next($request);
        }

        $user = auth()->user();
        $branchId = $user->branch_id ?? $request->input('branch_id');

        if (!$branchId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_BRANCH',
                    'message' => 'No se ha especificado una sucursal',
                ],
            ], 400);
        }

        $documentType = $request->input('document_type', 'FACTURA');

        $cai = CAI::where('branch_id', $branchId)
                  ->where('document_type', $documentType)
                  ->where('status', 'active')
                  ->where('expiration_date', '>=', now()->toDateString())
                  ->first();

        if (!$cai) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_CAI',
                    'message' => 'No hay CAI vigente para esta sucursal y tipo de documento',
                    'document_type' => $documentType,
                ],
            ], 403);
        }

        $availableCorrelatives = $cai->getAvailableCorrelativesCount();

        if ($availableCorrelatives === 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_CORRELATIVES_AVAILABLE',
                    'message' => 'No hay correlativos disponibles en el CAI activo',
                    'cai_number' => $cai->cai_number,
                ],
            ], 403);
        }

        // Warn if low on correlatives
        if ($cai->isLowOnCorrelatives()) {
            $request->merge(['warning' => [
                'code' => 'LOW_CORRELATIVES',
                'message' => "Quedan solo {$availableCorrelatives} correlativos disponibles",
                'available_correlatives' => $availableCorrelatives,
            ]]);
        }

        // Warn if CAI is near expiration
        if ($cai->isNearExpiration()) {
            $daysRemaining = $cai->expiration_date->diffInDays(now());
            $request->merge(['warning' => [
                'code' => 'CAI_NEAR_EXPIRATION',
                'message' => "El CAI vence en {$daysRemaining} días",
                'expiration_date' => $cai->expiration_date->format('Y-m-d'),
            ]]);
        }

        // Attach CAI to request for use in controller
        $request->merge(['active_cai' => $cai]);

        return $next($request);
    }
}
