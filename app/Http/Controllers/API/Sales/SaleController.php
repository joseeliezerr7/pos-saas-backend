<?php

namespace App\Http\Controllers\API\Sales;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\Sale\Sale;
use App\Services\SaleService;
use App\Services\LoyaltyService;
use App\Traits\ValidatesPlanLimits;
use App\Traits\FiltersByBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SaleController extends Controller
{
    use ValidatesPlanLimits, FiltersByBranch;
    public function __construct(
        protected SaleService $saleService,
        protected LoyaltyService $loyaltyService
    ) {}

    /**
     * Get all sales
     */
    public function index(Request $request): JsonResponse
    {
        \Log::info('Sales Index - User:', [
            'user_id' => auth()->user()->id,
            'tenant_id' => auth()->user()->tenant_id,
            'email' => auth()->user()->email
        ]);
        \Log::info('Sales Index - Filters:', $request->all());

        $query = Sale::with(['details.product', 'user', 'customer'])
            ->where('tenant_id', auth()->user()->tenant_id);

        // Apply branch filter based on user's assigned branch
        $query = $this->applyBranchFilter($query, $request->branch_id);

        $totalBeforeFilters = $query->count();
        \Log::info('Sales before filters:', [
            'count' => $totalBeforeFilters,
            'user_branch_id' => auth()->user()->branch_id,
            'request_branch_id' => $request->branch_id
        ]);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            \Log::info('Applying date filter:', [
                'from' => $request->date_from . ' 00:00:00',
                'to' => $request->date_to . ' 23:59:59'
            ]);
            $query->whereBetween('sold_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ]);
        }

        $totalAfterFilters = $query->count();
        \Log::info('Sales after filters:', ['count' => $totalAfterFilters]);

        // Sorting
        $sortBy = $request->get('sort_by', 'sold_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 20);
        $sales = $query->paginate($perPage);

        \Log::info('Final sales count:', ['count' => $sales->count()]);

        return response()->json([
            'success' => true,
            'data' => SaleResource::collection($sales),
            'meta' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ],
        ]);
    }

    /**
     * Create a new sale
     */
    public function store(Request $request): JsonResponse
    {
        // Validate monthly transaction limits before creating sale
        $limitError = $this->validateMonthlyTransactionLimit();
        if ($limitError) {
            return $limitError;
        }

        $validator = Validator::make($request->all(), [
            'branch_id' => 'nullable|exists:branches,id',
            'cash_opening_id' => 'required|exists:cash_openings,id',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_rtn' => 'nullable|string|max:14',
            'customer_name' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:cash,card,transfer,credit,qr,mixed',
            'transaction_reference' => 'nullable|string|max:100',
            'payment_details' => 'nullable|array',
            'amount_paid' => 'nullable|numeric|min:0',
            'amount_change' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
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

        // Determine branch_id: use user's branch if assigned, otherwise use provided or default
        $branchId = $this->getBranchIdForCreate($request->branch_id);

        // If user has assigned branch and tries to create in different branch, deny
        if (auth()->user()->branch_id && $request->filled('branch_id') && $request->branch_id != auth()->user()->branch_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED_BRANCH',
                    'message' => 'No tienes permiso para crear ventas en esta sucursal',
                ],
            ], 403);
        }

        // Add branch_id to request data
        $request->merge(['branch_id' => $branchId]);

        // Verify cash opening is open
        $cashOpening = \App\Models\CashRegister\CashOpening::find($request->cash_opening_id);
        if (!$cashOpening || $cashOpening->status !== 'open') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CASH_NOT_OPEN',
                    'message' => 'No hay una caja abierta. Debe abrir una caja antes de realizar ventas.',
                ],
            ], 400);
        }

        try {
            $sale = $this->saleService->createSale($request->all());

            // Send email notification if enabled
            $company = auth()->user()->company;
            $notificationSettings = $company->notification_settings ?? [];

            if (isset($notificationSettings['send_sale_confirmation']) && $notificationSettings['send_sale_confirmation']) {
                // Send email to customer if they have an email
                if ($sale->customer && $sale->customer->email) {
                    \Mail::to($sale->customer->email)->send(new \App\Mail\SaleConfirmation($sale));
                }
            }

            // Award loyalty points if customer is enrolled
            $loyaltyTransaction = null;
            if ($sale->customer_id) {
                try {
                    $loyaltyTransaction = $this->loyaltyService->awardPointsForSale($sale);
                } catch (\Exception $e) {
                    \Log::warning('Failed to award loyalty points', [
                        'sale_id' => $sale->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => new SaleResource($sale),
                'message' => 'Venta creada exitosamente',
                'loyalty' => $loyaltyTransaction ? [
                    'points_earned' => $loyaltyTransaction->points,
                    'new_balance' => $loyaltyTransaction->balance_after,
                ] : null,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SALE_CREATION_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get a single sale
     */
    public function show(int $id): JsonResponse
    {
        $query = Sale::with(['details.product', 'user', 'customer', 'invoice'])
            ->where('tenant_id', auth()->user()->tenant_id);

        // Apply branch filter
        $query = $this->applyBranchFilter($query);

        $sale = $query->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new SaleResource($sale),
        ]);
    }

    /**
     * Void a sale
     */
    public function void(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
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

        $sale = Sale::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        try {
            $this->saleService->voidSale($sale, $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Venta anulada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VOID_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get sales statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
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

        $statistics = $this->saleService->getSalesStatistics(
            $request->branch_id,
            $request->date_from,
            $request->date_to
        );

        return response()->json([
            'success' => true,
            'data' => $statistics,
        ]);
    }
}
