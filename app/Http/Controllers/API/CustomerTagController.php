<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerTag;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerTagController extends Controller
{
    /**
     * Listar todos los tags
     */
    public function index(Request $request)
    {
        $query = CustomerTag::forCompany(auth()->user()->tenant_id)
            ->withCount('customers');

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $tags = $request->has('per_page')
            ? $query->latest()->paginate($request->per_page)
            : $query->latest()->get();

        return response()->json($tags);
    }

    /**
     * Crear nuevo tag
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|size:7',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tag = CustomerTag::create([
            'company_id' => auth()->user()->tenant_id,
            'name' => $request->name,
            'color' => $request->color ?? '#10B981',
        ]);

        return response()->json([
            'message' => 'Tag creado exitosamente',
            'data' => $tag
        ], 201);
    }

    /**
     * Mostrar un tag específico
     */
    public function show($id)
    {
        $tag = CustomerTag::forCompany(auth()->user()->tenant_id)
            ->withCount('customers')
            ->with(['customers' => function ($query) {
                $query->select('customers.id', 'name', 'email', 'phone')
                    ->limit(20);
            }])
            ->findOrFail($id);

        return response()->json($tag);
    }

    /**
     * Actualizar tag
     */
    public function update(Request $request, $id)
    {
        $tag = CustomerTag::forCompany(auth()->user()->tenant_id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'color' => 'nullable|string|size:7',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tag->update($request->only(['name', 'color']));

        return response()->json([
            'message' => 'Tag actualizado exitosamente',
            'data' => $tag
        ]);
    }

    /**
     * Eliminar tag
     */
    public function destroy($id)
    {
        $tag = CustomerTag::forCompany(auth()->user()->tenant_id)->findOrFail($id);

        // Desvincular de clientes antes de eliminar
        $tag->customers()->detach();

        $tag->delete();

        return response()->json([
            'message' => 'Tag eliminado exitosamente'
        ]);
    }

    /**
     * Asignar tag a clientes
     */
    public function assignToCustomers(Request $request, $id)
    {
        $tag = CustomerTag::forCompany(auth()->user()->tenant_id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'customer_ids' => 'required|array',
            'customer_ids.*' => 'exists:customers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verificar que los clientes pertenezcan a la empresa
        $customers = Customer::whereIn('id', $request->customer_ids)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->pluck('id');

        $tag->customers()->syncWithoutDetaching($customers);

        return response()->json([
            'message' => 'Tag asignado a clientes exitosamente',
            'assigned_count' => $customers->count()
        ]);
    }

    /**
     * Remover tag de clientes
     */
    public function removeFromCustomers(Request $request, $id)
    {
        $tag = CustomerTag::forCompany(auth()->user()->tenant_id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'customer_ids' => 'required|array',
            'customer_ids.*' => 'exists:customers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tag->customers()->detach($request->customer_ids);

        return response()->json([
            'message' => 'Tag removido de clientes exitosamente'
        ]);
    }
}
