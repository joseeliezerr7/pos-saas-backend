<?php

namespace App\Http\Controllers\API\Sales;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuotationResource;
use App\Models\Sale\Quotation;
use App\Services\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QuotationController extends Controller
{
    public function __construct(
        protected QuotationService $quotationService
    ) {}

    /**
     * Get all quotations
     */
    public function index(Request $request): JsonResponse
    {
        $query = Quotation::with(['details.product', 'user', 'customer', 'branch'])
            ->where('tenant_id', auth()->user()->tenant_id);

        // Filters
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('quoted_at', [$request->date_from, $request->date_to]);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('quotation_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_rtn', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'quoted_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 20);
        $quotations = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => QuotationResource::collection($quotations),
            'meta' => [
                'current_page' => $quotations->currentPage(),
                'last_page' => $quotations->lastPage(),
                'per_page' => $quotations->perPage(),
                'total' => $quotations->total(),
            ],
        ]);
    }

    /**
     * Create a new quotation
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_rtn' => 'nullable|string|max:14',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'valid_until' => 'nullable|date|after:today',
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
            $quotation = $this->quotationService->createQuotation($request->all());

            return response()->json([
                'success' => true,
                'data' => new QuotationResource($quotation),
                'message' => 'Cotización creada exitosamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'QUOTATION_CREATION_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get a single quotation
     */
    public function show(int $id): JsonResponse
    {
        $quotation = Quotation::with(['details.product', 'user', 'customer', 'branch', 'sale'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new QuotationResource($quotation),
        ]);
    }

    /**
     * Update a quotation
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $quotation = Quotation::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        if ($quotation->isConverted()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'QUOTATION_ALREADY_CONVERTED',
                    'message' => 'No se puede editar una cotización que ya fue convertida a venta',
                ],
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'customer_name' => 'sometimes|required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'items' => 'sometimes|required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'valid_until' => 'nullable|date',
            'status' => 'sometimes|in:pending,accepted,rejected,expired',
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
            $quotation = $this->quotationService->updateQuotation($quotation, $request->all());

            return response()->json([
                'success' => true,
                'data' => new QuotationResource($quotation),
                'message' => 'Cotización actualizada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'QUOTATION_UPDATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Delete a quotation
     */
    public function destroy(int $id): JsonResponse
    {
        $quotation = Quotation::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        if ($quotation->isConverted()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'QUOTATION_ALREADY_CONVERTED',
                    'message' => 'No se puede eliminar una cotización que ya fue convertida a venta',
                ],
            ], 400);
        }

        try {
            $quotation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cotización eliminada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'QUOTATION_DELETE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Convert quotation to sale
     */
    public function convertToSale(Request $request, int $id): JsonResponse
    {
        $quotation = Quotation::with('details')
            ->where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        if ($quotation->isConverted()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'QUOTATION_ALREADY_CONVERTED',
                    'message' => 'Esta cotización ya fue convertida a venta',
                ],
            ], 400);
        }

        if ($quotation->isExpired()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'QUOTATION_EXPIRED',
                    'message' => 'Esta cotización ha expirado',
                ],
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'cash_opening_id' => 'nullable|exists:cash_openings,id',
            'payment_method' => 'required|in:cash,card,transfer,credit,qr,mixed',
            'payment_details' => 'nullable|array',
            'amount_paid' => 'nullable|numeric|min:0',
            'amount_change' => 'nullable|numeric|min:0',
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
            $sale = $this->quotationService->convertToSale($quotation, $request->all());

            return response()->json([
                'success' => true,
                'data' => $sale,
                'message' => 'Cotización convertida a venta exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CONVERSION_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }
}
