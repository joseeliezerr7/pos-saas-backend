<?php

namespace App\Http\Controllers\API\Settings;

use App\Http\Controllers\Controller;
use App\Models\Fiscal\CAI;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CAIController extends Controller
{
    /**
     * Get all CAIs for current tenant
     */
    public function index(Request $request): JsonResponse
    {
        $query = CAI::with(['branch'])
            ->where('tenant_id', auth()->user()->tenant_id);

        // Filter by branch
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by document type
        if ($request->has('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        $cais = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $cais
        ]);
    }

    /**
     * Get active CAI for a branch and document type
     */
    public function getActive(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id', auth()->user()->branch_id);
        $documentType = $request->input('document_type', 'FACTURA');

        $cai = CAI::where('tenant_id', auth()->user()->tenant_id)
            ->where('branch_id', $branchId)
            ->where('document_type', $documentType)
            ->where('status', 'active')
            ->where('expiration_date', '>=', now()->toDateString())
            ->whereRaw('used_documents < total_documents')
            ->first();

        if (!$cai) {
            return response()->json([
                'success' => false,
                'message' => 'No hay CAI activo disponible'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $cai
        ]);
    }

    /**
     * Get single CAI
     */
    public function show(int $id): JsonResponse
    {
        $cai = CAI::with(['branch'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $cai
        ]);
    }

    /**
     * Create new CAI
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'cai_number' => 'required|string|max:50|unique:cais,cai_number',
            'document_type' => 'required|in:FACTURA,NOTA_CREDITO,NOTA_DEBITO,RECIBO_HONORARIOS,FACTURA_EXPORTACION',
            'range_start' => 'required|string|max:20',
            'range_end' => 'required|string|max:20',
            'authorization_date' => 'required|date',
            'expiration_date' => 'required|date|after:authorization_date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Calculate total documents
        $rangeStart = (int) filter_var($request->range_start, FILTER_SANITIZE_NUMBER_INT);
        $rangeEnd = (int) filter_var($request->range_end, FILTER_SANITIZE_NUMBER_INT);
        $totalDocuments = $rangeEnd - $rangeStart + 1;

        $cai = CAI::create([
            'tenant_id' => auth()->user()->tenant_id,
            'branch_id' => $request->branch_id,
            'cai_number' => $request->cai_number,
            'document_type' => $request->document_type,
            'range_start' => $request->range_start,
            'range_end' => $request->range_end,
            'total_documents' => $totalDocuments,
            'used_documents' => 0,
            'authorization_date' => $request->authorization_date,
            'expiration_date' => $request->expiration_date,
            'status' => 'active',
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'data' => $cai->load('branch'),
            'message' => 'CAI creado exitosamente'
        ], 201);
    }

    /**
     * Update CAI
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $cai = CAI::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:active,expired,depleted,canceled',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $cai->update($request->only(['status', 'notes']));

        return response()->json([
            'success' => true,
            'data' => $cai->load('branch'),
            'message' => 'CAI actualizado exitosamente'
        ]);
    }

    /**
     * Delete CAI
     */
    public function destroy(int $id): JsonResponse
    {
        $cai = CAI::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);

        if ($cai->used_documents > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un CAI que ya ha sido utilizado'
            ], 400);
        }

        $cai->delete();

        return response()->json([
            'success' => true,
            'message' => 'CAI eliminado exitosamente'
        ]);
    }
}
