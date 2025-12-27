<?php

namespace App\Http\Controllers\API\Fiscal;

use App\Http\Controllers\Controller;
use App\Models\Fiscal\CAI;
use App\Services\CorrelativeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CorrelativeController extends Controller
{
    public function __construct(
        protected CorrelativeService $correlativeService
    ) {}

    /**
     * Get the next available correlative for a branch
     */
    public function next(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'document_type' => 'nullable|string|in:FACTURA,RECIBO,NOTA_CREDITO,NOTA_DEBITO',
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

        try {
            $documentType = $request->get('document_type', 'FACTURA');
            $correlative = $this->correlativeService->getNextCorrelative(
                $request->branch_id,
                $documentType
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $correlative->id,
                    'cai_id' => $correlative->cai_id,
                    'number' => $correlative->number,
                    'formatted_number' => $correlative->formatted_number,
                    'status' => $correlative->status,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_CAI',
                    'message' => 'No hay un CAI activo para esta sucursal y tipo de documento',
                ],
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CORRELATIVE_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get available correlatives count for a CAI
     */
    public function availableCount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'document_type' => 'nullable|string|in:FACTURA,RECIBO,NOTA_CREDITO,NOTA_DEBITO',
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

        try {
            $documentType = $request->get('document_type', 'FACTURA');

            // Find active CAI
            $cai = CAI::where('branch_id', $request->branch_id)
                      ->where('document_type', $documentType)
                      ->where('status', 'active')
                      ->where('expiration_date', '>=', now()->toDateString())
                      ->firstOrFail();

            $available = $this->correlativeService->getAvailableCount($cai);
            $needsAlert = $this->correlativeService->needsAlert($cai);

            return response()->json([
                'success' => true,
                'data' => [
                    'cai_id' => $cai->id,
                    'cai_number' => $cai->cai_number,
                    'total_documents' => $cai->total_documents,
                    'used_documents' => $cai->used_documents,
                    'available_count' => $available,
                    'needs_alert' => $needsAlert,
                    'expiration_date' => $cai->expiration_date,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_CAI',
                    'message' => 'No hay un CAI activo para esta sucursal y tipo de documento',
                ],
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }
}
