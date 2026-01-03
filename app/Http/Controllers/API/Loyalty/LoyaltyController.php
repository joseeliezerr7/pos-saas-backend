<?php

namespace App\Http\Controllers\API\Loyalty;

use App\Http\Controllers\Controller;
use App\Models\Loyalty\LoyaltyProgram;
use App\Models\Loyalty\LoyaltyTier;
use App\Models\Loyalty\CustomerLoyalty;
use App\Models\Customer;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LoyaltyController extends Controller
{
    protected $loyaltyService;

    public function __construct(LoyaltyService $loyaltyService)
    {
        $this->loyaltyService = $loyaltyService;
    }

    /**
     * Obtener el programa de lealtad activo
     */
    public function getProgram(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $program = LoyaltyProgram::where('tenant_id', $tenantId)
            ->with('tiers')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $program,
            'message' => $program ? null : 'No hay programa de lealtad configurado',
        ]);
    }

    /**
     * Crear o actualizar programa de lealtad
     */
    public function saveProgram(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'points_per_currency' => 'required|numeric|min:0',
            'min_purchase_amount' => 'required|integer|min:0',
            'point_value' => 'required|numeric|min:0',
            'points_expire' => 'boolean',
            'expiration_days' => 'nullable|integer|min:1',
            'special_dates' => 'nullable|array',
            'birthday_multiplier' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = auth()->user()->tenant_id;

        $program = LoyaltyProgram::where('tenant_id', $tenantId)->first();

        if ($program) {
            $program->update($request->all());
        } else {
            $program = LoyaltyProgram::create([
                'tenant_id' => $tenantId,
                ...$request->all(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Programa de lealtad guardado correctamente',
            'data' => $program->load('tiers'),
        ]);
    }

    /**
     * Obtener todos los tiers
     */
    public function getTiers(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $program = LoyaltyProgram::where('tenant_id', $tenantId)->first();

        $tiers = $program ? $program->tiers : [];

        return response()->json([
            'success' => true,
            'data' => $tiers,
            'message' => $program ? null : 'No hay programa de lealtad configurado',
        ]);
    }

    /**
     * Crear tier
     */
    public function createTier(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:50',
            'min_points' => 'required|integer|min:0',
            'order' => 'required|integer|min:0',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'points_multiplier' => 'required|numeric|min:1',
            'benefits' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = auth()->user()->tenant_id;

        $program = LoyaltyProgram::where('tenant_id', $tenantId)->first();

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'No hay programa de lealtad configurado',
            ], 404);
        }

        $tier = LoyaltyTier::create([
            'loyalty_program_id' => $program->id,
            ...$request->all(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tier creado correctamente',
            'data' => $tier,
        ], 201);
    }

    /**
     * Actualizar tier
     */
    public function updateTier(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:50',
            'min_points' => 'required|integer|min:0',
            'order' => 'required|integer|min:0',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'points_multiplier' => 'required|numeric|min:1',
            'benefits' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = auth()->user()->tenant_id;

        $tier = LoyaltyTier::whereHas('loyaltyProgram', function ($query) use ($tenantId) {
            $query->where('tenant_id', $tenantId);
        })->findOrFail($id);

        $tier->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Tier actualizado correctamente',
            'data' => $tier,
        ]);
    }

    /**
     * Eliminar tier
     */
    public function deleteTier($id)
    {
        $tenantId = auth()->user()->tenant_id;

        $tier = LoyaltyTier::whereHas('loyaltyProgram', function ($query) use ($tenantId) {
            $query->where('tenant_id', $tenantId);
        })->findOrFail($id);

        $tier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tier eliminado correctamente',
        ]);
    }

    /**
     * Obtener resumen de lealtad de un cliente
     */
    public function getCustomerSummary(Request $request, $customerId)
    {
        $tenantId = auth()->user()->tenant_id;

        $customer = Customer::where('id', $customerId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $summary = $this->loyaltyService->getCustomerLoyaltySummary($customer, $tenantId);

        if (!$summary) {
            return response()->json([
                'success' => false,
                'message' => 'No hay programa de lealtad configurado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Inscribir cliente en programa de lealtad
     */
    public function enrollCustomer(Request $request, $customerId)
    {
        $tenantId = auth()->user()->tenant_id;

        $customer = Customer::where('id', $customerId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $program = LoyaltyProgram::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'No hay programa de lealtad activo',
            ], 404);
        }

        $customerLoyalty = $this->loyaltyService->enrollCustomer($customer, $program);

        return response()->json([
            'success' => true,
            'message' => 'Cliente inscrito en programa de lealtad',
            'data' => $customerLoyalty->load(['currentTier', 'loyaltyProgram']),
        ]);
    }

    /**
     * Canjear puntos de un cliente
     */
    public function redeemPoints(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'points' => 'required|integer|min:1',
            'sale_id' => 'nullable|exists:sales,id',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = auth()->user()->tenant_id;

        $customer = Customer::where('id', $request->customer_id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $program = LoyaltyProgram::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'No hay programa de lealtad activo',
            ], 404);
        }

        $customerLoyalty = CustomerLoyalty::where('customer_id', $customer->id)
            ->where('loyalty_program_id', $program->id)
            ->first();

        if (!$customerLoyalty) {
            return response()->json([
                'success' => false,
                'message' => 'El cliente no está inscrito en el programa de lealtad',
            ], 404);
        }

        try {
            $transaction = $this->loyaltyService->redeemPoints(
                $customerLoyalty,
                $request->points,
                $request->sale_id,
                $request->description
            );

            $pointsValue = $this->loyaltyService->calculatePointsValue($program, $request->points);

            return response()->json([
                'success' => true,
                'message' => 'Puntos canjeados correctamente',
                'data' => [
                    'transaction' => $transaction,
                    'points_value' => $pointsValue,
                    'remaining_points' => $customerLoyalty->fresh()->points,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Obtener historial de transacciones de un cliente
     */
    public function getCustomerTransactions(Request $request, $customerId)
    {
        $tenantId = auth()->user()->tenant_id;

        $customer = Customer::where('id', $customerId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $program = LoyaltyProgram::where('tenant_id', $tenantId)->first();

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'No hay programa de lealtad configurado',
            ], 404);
        }

        $customerLoyalty = CustomerLoyalty::where('customer_id', $customer->id)
            ->where('loyalty_program_id', $program->id)
            ->first();

        if (!$customerLoyalty) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $transactions = $customerLoyalty->transactions()
            ->with('sale')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Ajustar puntos manualmente (admin)
     */
    public function adjustPoints(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'points' => 'required|integer',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = auth()->user()->tenant_id;

        $customer = Customer::where('id', $request->customer_id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $program = LoyaltyProgram::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'No hay programa de lealtad activo',
            ], 404);
        }

        $customerLoyalty = CustomerLoyalty::where('customer_id', $customer->id)
            ->where('loyalty_program_id', $program->id)
            ->first();

        if (!$customerLoyalty) {
            return response()->json([
                'success' => false,
                'message' => 'El cliente no está inscrito en el programa de lealtad',
            ], 404);
        }

        $transaction = $this->loyaltyService->recordTransaction(
            $customerLoyalty,
            'adjust',
            $request->points,
            null,
            $request->description
        );

        return response()->json([
            'success' => true,
            'message' => 'Puntos ajustados correctamente',
            'data' => [
                'transaction' => $transaction,
                'new_balance' => $customerLoyalty->fresh()->points,
            ],
        ]);
    }
}
