<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\CustomerGroupPrice;
use App\Services\CustomerGroupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerGroupController extends Controller
{
    protected $customerGroupService;

    public function __construct(CustomerGroupService $customerGroupService)
    {
        $this->customerGroupService = $customerGroupService;
    }

    /**
     * Listar todos los grupos de clientes
     */
    public function index(Request $request)
    {
        $query = CustomerGroup::forCompany(auth()->user()->tenant_id)
            ->withCount('customers');

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $groups = $request->has('per_page')
            ? $query->byPriority()->paginate($request->per_page)
            : $query->byPriority()->get();

        return response()->json($groups);
    }

    /**
     * Crear nuevo grupo de clientes
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'color' => 'nullable|string|size:7',
            'priority' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $group = CustomerGroup::create([
            'company_id' => auth()->user()->tenant_id,
            'name' => $request->name,
            'description' => $request->description,
            'discount_percentage' => $request->discount_percentage ?? 0,
            'color' => $request->color ?? '#3B82F6',
            'priority' => $request->priority ?? 0,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'message' => 'Grupo creado exitosamente',
            'data' => $group
        ], 201);
    }

    /**
     * Mostrar un grupo específico
     */
    public function show($id)
    {
        $group = CustomerGroup::forCompany(auth()->user()->tenant_id)
            ->withCount('customers')
            ->with(['customers' => function ($query) {
                $query->select('id', 'name', 'email', 'phone', 'customer_group_id', 'lifetime_value', 'total_purchases')
                    ->limit(10);
            }])
            ->findOrFail($id);

        return response()->json($group);
    }

    /**
     * Actualizar grupo
     */
    public function update(Request $request, $id)
    {
        $group = CustomerGroup::forCompany(auth()->user()->tenant_id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'color' => 'nullable|string|size:7',
            'priority' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $group->update($request->only([
            'name', 'description', 'discount_percentage', 'color', 'priority', 'is_active'
        ]));

        return response()->json([
            'message' => 'Grupo actualizado exitosamente',
            'data' => $group
        ]);
    }

    /**
     * Eliminar grupo
     */
    public function destroy($id)
    {
        $group = CustomerGroup::forCompany(auth()->user()->tenant_id)->findOrFail($id);

        // Verificar si tiene clientes asignados
        if ($group->customers()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el grupo porque tiene clientes asignados'
            ], 422);
        }

        $group->delete();

        return response()->json([
            'message' => 'Grupo eliminado exitosamente'
        ]);
    }

    /**
     * Obtener precios especiales de un grupo
     */
    public function prices($id)
    {
        $group = CustomerGroup::forCompany(auth()->user()->tenant_id)->findOrFail($id);

        $prices = CustomerGroupPrice::where('customer_group_id', $id)
            ->with('product:id,name,sku,price')
            ->get();

        return response()->json($prices);
    }

    /**
     * Establecer precio especial para un producto en el grupo
     */
    public function setPrice(Request $request, $id)
    {
        $group = CustomerGroup::forCompany(auth()->user()->tenant_id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'price' => 'required|numeric|min:0',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $price = $this->customerGroupService->setGroupPrice(
            $id,
            $request->product_id,
            $request->price,
            $request->valid_from,
            $request->valid_until
        );

        return response()->json([
            'message' => 'Precio especial configurado',
            'data' => $price->load('product:id,name,sku,price')
        ]);
    }

    /**
     * Eliminar precio especial
     */
    public function removePrice($id, $priceId)
    {
        $group = CustomerGroup::forCompany(auth()->user()->tenant_id)->findOrFail($id);

        $price = CustomerGroupPrice::where('customer_group_id', $id)
            ->where('id', $priceId)
            ->firstOrFail();

        $price->delete();

        return response()->json([
            'message' => 'Precio especial eliminado'
        ]);
    }

    /**
     * Calcular análisis RFM para todos los clientes
     */
    public function calculateRFM()
    {
        $this->customerGroupService->calculateRFMForCompany(auth()->user()->tenant_id);

        return response()->json([
            'message' => 'Análisis RFM calculado exitosamente'
        ]);
    }

    /**
     * Obtener estadísticas de segmentación
     */
    public function stats()
    {
        $stats = $this->customerGroupService->getSegmentationStats(auth()->user()->tenant_id);

        return response()->json($stats);
    }

    /**
     * Asignar múltiples clientes a un grupo
     */
    public function assignCustomers(Request $request, $id)
    {
        $group = CustomerGroup::forCompany(auth()->user()->tenant_id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'customer_ids' => 'required|array',
            'customer_ids.*' => 'exists:customers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        \App\Models\Customer::whereIn('id', $request->customer_ids)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->update(['customer_group_id' => $id]);

        return response()->json([
            'message' => 'Clientes asignados al grupo exitosamente'
        ]);
    }
}
