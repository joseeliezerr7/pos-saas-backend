<?php

namespace App\Http\Controllers\API\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryAdjustmentResource;
use App\Models\Inventory\InventoryAdjustment;
use App\Services\InventoryAdjustmentService;
use App\Traits\FiltersByBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InventoryAdjustmentController extends Controller
{
    use FiltersByBranch;

    public function __construct(
        protected InventoryAdjustmentService $adjustmentService
    ) {}

    /**
     * Get all inventory adjustments
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryAdjustment::with(['details.product', 'details.variant', 'branch', 'user', 'approver'])
            ->where('tenant_id', auth()->user()->tenant_id);

        // Apply branch filter
        $query = $this->applyBranchFilter($query, $request->branch_id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('created_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ]);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('adjustment_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 20);
        $adjustments = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => InventoryAdjustmentResource::collection($adjustments),
            'meta' => [
                'current_page' => $adjustments->currentPage(),
                'last_page' => $adjustments->lastPage(),
                'per_page' => $adjustments->perPage(),
                'total' => $adjustments->total(),
            ],
        ]);
    }

    /**
     * Create a new inventory adjustment
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'reason' => 'required|in:physical_count,damage,theft,expiration,correction,other',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.physical_quantity' => 'required|numeric|min:0',
            'items.*.cost' => 'nullable|numeric|min:0',
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
            $adjustment = $this->adjustmentService->createAdjustment($request->all());

            return response()->json([
                'success' => true,
                'data' => new InventoryAdjustmentResource($adjustment),
                'message' => 'Ajuste de inventario creado exitosamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ADJUSTMENT_CREATION_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get a single inventory adjustment
     */
    public function show(int $id): JsonResponse
    {
        $adjustment = InventoryAdjustment::with(['details.product', 'details.variant', 'branch', 'user', 'approver'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new InventoryAdjustmentResource($adjustment),
        ]);
    }

    /**
     * Approve an inventory adjustment
     */
    public function approve(int $id): JsonResponse
    {
        $adjustment = InventoryAdjustment::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        try {
            $this->adjustmentService->approveAdjustment($adjustment);

            return response()->json([
                'success' => true,
                'message' => 'Ajuste de inventario aprobado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'APPROVAL_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Reject an inventory adjustment
     */
    public function reject(int $id): JsonResponse
    {
        $adjustment = InventoryAdjustment::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        try {
            $this->adjustmentService->rejectAdjustment($adjustment);

            return response()->json([
                'success' => true,
                'message' => 'Ajuste de inventario rechazado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REJECTION_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Delete a pending inventory adjustment
     */
    public function destroy(int $id): JsonResponse
    {
        $adjustment = InventoryAdjustment::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        try {
            $this->adjustmentService->deleteAdjustment($adjustment);

            return response()->json([
                'success' => true,
                'message' => 'Ajuste de inventario eliminado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELETION_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }
}
