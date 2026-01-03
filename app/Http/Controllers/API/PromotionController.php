<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PromotionController extends Controller
{
    protected $promotionService;

    public function __construct(PromotionService $promotionService)
    {
        $this->promotionService = $promotionService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Promotion::with('products');
        $userBranchId = auth()->user()->branch_id;

        // Filter by user's branch (if user has assigned branch)
        if ($userBranchId) {
            $query->where(function ($q) use ($userBranchId) {
                // Promotions that apply to all branches (branch_ids is null)
                $q->whereNull('branch_ids')
                  // Or promotions that include user's branch
                  ->orWhereJsonContains('branch_ids', $userBranchId);
            });
        }

        // Filtros
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('code', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Filtrar por estado (activas ahora, próximas, expiradas)
        if ($request->filled('status')) {
            $now = now();
            switch ($request->status) {
                case 'active':
                    $query->where('is_active', true)
                        ->where('start_date', '<=', $now)
                        ->where('end_date', '>=', $now);
                    break;
                case 'upcoming':
                    $query->where('start_date', '>', $now);
                    break;
                case 'expired':
                    $query->where('end_date', '<', $now);
                    break;
            }
        }

        // Ordenar
        $sortField = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        $promotions = $query->paginate($request->get('per_page', 15));

        return response()->json($promotions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'code' => 'nullable|string|unique:promotions,code',
            'type' => 'required|in:percentage,fixed_amount,bogo,volume,bundle,free_shipping',
            'discount_value' => 'required|numeric|min:0',
            'buy_quantity' => 'nullable|integer|min:1',
            'get_quantity' => 'nullable|integer|min:1',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_per_customer' => 'nullable|integer|min:1',
            'applicable_to' => 'required|in:all,products,categories,brands',
            'applicable_ids' => 'nullable|array',
            'applicable_ids.*' => 'integer',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'integer',
            'customer_group_ids' => 'nullable|array',
            'customer_group_ids.*' => 'integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|min:0|max:6',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'is_active' => 'boolean',
            'auto_apply' => 'boolean',
            'priority' => 'nullable|integer',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->all();
            $data['tenant_id'] = auth()->user()->tenant_id;

            $promotion = Promotion::create($data);

            // Asociar productos específicos si se proporcionaron
            if ($request->filled('product_ids')) {
                $promotion->products()->sync($request->product_ids);
            }

            $promotion->load('products');

            return response()->json([
                'success' => true,
                'message' => 'Promoción creada exitosamente',
                'data' => $promotion
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la promoción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $promotion = Promotion::with(['products', 'usages.sale', 'usages.customer'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $promotion
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $promotion = Promotion::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'code' => 'nullable|string|unique:promotions,code,' . $id,
            'type' => 'in:percentage,fixed_amount,bogo,volume,bundle,free_shipping',
            'discount_value' => 'numeric|min:0',
            'buy_quantity' => 'nullable|integer|min:1',
            'get_quantity' => 'nullable|integer|min:1',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_per_customer' => 'nullable|integer|min:1',
            'applicable_to' => 'in:all,products,categories,brands',
            'applicable_ids' => 'nullable|array',
            'applicable_ids.*' => 'integer',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'integer',
            'customer_group_ids' => 'nullable|array',
            'customer_group_ids.*' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date|after:start_date',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|min:0|max:6',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'is_active' => 'boolean',
            'auto_apply' => 'boolean',
            'priority' => 'nullable|integer',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $promotion->update($request->all());

            // Actualizar productos asociados si se proporcionaron
            if ($request->has('product_ids')) {
                $promotion->products()->sync($request->product_ids);
            }

            $promotion->load('products');

            return response()->json([
                'success' => true,
                'message' => 'Promoción actualizada exitosamente',
                'data' => $promotion
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la promoción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $promotion = Promotion::findOrFail($id);
            $promotion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Promoción eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la promoción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar un código de cupón
     */
    public function validateCoupon(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'branch_id' => 'required|integer',
            'customer_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $promotion = $this->promotionService->validateCouponCode(
            $request->code,
            $request->branch_id,
            $request->customer_id
        );

        if (!$promotion) {
            return response()->json([
                'success' => false,
                'message' => 'Cupón inválido, expirado o no disponible'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $promotion
        ]);
    }

    /**
     * Obtener promociones aplicables a un carrito
     */
    public function getApplicablePromotions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_items' => 'required|array',
            'cart_items.*.product_id' => 'required|integer',
            'cart_items.*.variant_id' => 'nullable|integer',
            'cart_items.*.quantity' => 'required|integer|min:1',
            'cart_items.*.price' => 'required|numeric|min:0',
            'branch_id' => 'required|integer',
            'customer_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $promotions = $this->promotionService->getApplicablePromotions(
            $request->cart_items,
            $request->branch_id,
            $request->customer_id
        );

        return response()->json([
            'success' => true,
            'data' => $promotions
        ]);
    }

    /**
     * Aplicar promoción a un carrito
     */
    public function applyPromotion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'promotion_id' => 'required|integer|exists:promotions,id',
            'cart_items' => 'required|array',
            'cart_items.*.product_id' => 'required|integer',
            'cart_items.*.variant_id' => 'nullable|integer',
            'cart_items.*.quantity' => 'required|integer|min:1',
            'cart_items.*.price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $promotion = Promotion::findOrFail($request->promotion_id);
        $updatedCart = $this->promotionService->applyPromotionToCart($promotion, $request->cart_items);

        // Calculate total discount from updated cart
        $totalDiscount = 0;
        foreach ($updatedCart as $item) {
            if (isset($item['discount'])) {
                $totalDiscount += $item['discount'];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'promotion' => $promotion,
                'cart' => $updatedCart,
                'discount_amount' => round($totalDiscount, 2)
            ]
        ]);
    }

    /**
     * Activar/Desactivar promoción
     */
    public function toggleActive($id)
    {
        try {
            $promotion = Promotion::findOrFail($id);
            $promotion->is_active = !$promotion->is_active;
            $promotion->save();

            return response()->json([
                'success' => true,
                'message' => $promotion->is_active ? 'Promoción activada' : 'Promoción desactivada',
                'data' => $promotion
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de uso de una promoción
     */
    public function stats($id)
    {
        $promotion = Promotion::with('usages')->findOrFail($id);

        $totalUsed = $promotion->times_used;
        $totalDiscount = $promotion->usages()->sum('discount_amount');
        $totalSales = $promotion->usages()->count();
        $averageDiscount = $totalSales > 0 ? $totalDiscount / $totalSales : 0;

        // Top clientes que usaron la promoción
        $topCustomers = $promotion->usages()
            ->with('customer')
            ->selectRaw('customer_id, COUNT(*) as usage_count, SUM(discount_amount) as total_discount')
            ->groupBy('customer_id')
            ->orderByDesc('usage_count')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'promotion' => $promotion,
                'stats' => [
                    'total_used' => $totalUsed,
                    'total_discount' => round($totalDiscount, 2),
                    'total_sales' => $totalSales,
                    'average_discount' => round($averageDiscount, 2),
                    'remaining_uses' => $promotion->usage_limit ? $promotion->usage_limit - $totalUsed : null,
                ],
                'top_customers' => $topCustomers
            ]
        ]);
    }
}
