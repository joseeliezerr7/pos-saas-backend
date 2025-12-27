<?php

namespace App\Http\Controllers\API\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sale\ProductReturn;
use App\Services\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReturnController extends Controller
{
    public function __construct(
        protected ReturnService $returnService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ProductReturn::with(['details.product', 'sale', 'user', 'customer', 'branch']);

        if ($request->filled('branch_id')) $query->where('branch_id', $request->branch_id);
        if ($request->filled('sale_id')) $query->where('sale_id', $request->sale_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('return_type')) $query->where('return_type', $request->return_type);
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('returned_at', [$request->date_from, $request->date_to]);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        $query->orderBy($request->get('sort_by', 'returned_at'), $request->get('sort_order', 'desc'));
        $returns = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $returns->items(),
            'meta' => [
                'current_page' => $returns->currentPage(),
                'last_page' => $returns->lastPage(),
                'per_page' => $returns->perPage(),
                'total' => $returns->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sale_id' => 'required|exists:sales,id',
            'branch_id' => 'nullable|exists:branches,id',
            'reason' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.sale_detail_id' => 'required|exists:sale_details,id',
            'items.*.quantity_returned' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.reason' => 'nullable|string',
            'refund_method' => 'required|in:cash,card,transfer,credit,mixed',
            'refund_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Los datos proporcionados no son válidos',
                'errors' => $validator->errors(),
            ]], 422);
        }

        try {
            $return = $this->returnService->createReturn($request->all());
            return response()->json(['success' => true, 'data' => $return, 'message' => 'Devolución creada exitosamente'], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => [
                'code' => 'RETURN_CREATION_FAILED',
                'message' => $e->getMessage(),
            ]], 400);
        }
    }

    public function show(int $id): JsonResponse
    {
        $return = ProductReturn::with(['details.product', 'details.saleDetail', 'sale', 'user', 'customer', 'branch'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $return]);
    }

    public function complete(int $id): JsonResponse
    {
        $return = ProductReturn::findOrFail($id);

        try {
            $return = $this->returnService->completeReturn($return);
            return response()->json(['success' => true, 'data' => $return, 'message' => 'Devolución procesada exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => [
                'code' => 'RETURN_COMPLETION_FAILED',
                'message' => $e->getMessage(),
            ]], 400);
        }
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $return = ProductReturn::findOrFail($id);

        $validator = Validator::make($request->all(), ['reason' => 'required|string|max:500']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Debe proporcionar un motivo de cancelación',
                'errors' => $validator->errors(),
            ]], 422);
        }

        try {
            $return = $this->returnService->cancelReturn($return, $request->reason);
            return response()->json(['success' => true, 'data' => $return, 'message' => 'Devolución cancelada exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => [
                'code' => 'RETURN_CANCELLATION_FAILED',
                'message' => $e->getMessage(),
            ]], 400);
        }
    }
}
