<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::with(['customerGroup', 'loyalty.currentTier']);

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $customers = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * Search customers (for POS quick search)
     */
    public function search(Request $request): JsonResponse
    {
        $term = $request->get('q', '');

        $customers = Customer::active()
            ->search($term)
            ->limit(10)
            ->get([
                'id',
                'name',
                'rtn',
                'phone',
                'email',
                'customer_group_id',
                'credit_limit',
                'current_balance',
                'credit_days'
            ]);

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * Store a newly created customer
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'rtn' => 'nullable|string|size:14',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'customer_group_id' => 'nullable|exists:customer_groups,id',
            'credit_limit' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $customer = Customer::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $request->name,
            'rtn' => $request->rtn,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'customer_group_id' => $request->customer_group_id,
            'credit_limit' => $request->credit_limit ?? 0,
            'current_balance' => 0,
            'loyalty_points' => 0,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cliente creado exitosamente',
            'data' => $customer,
        ], 201);
    }

    /**
     * Display the specified customer
     */
    public function show(string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    /**
     * Update the specified customer
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'rtn' => 'nullable|string|size:14',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'customer_group_id' => 'nullable|exists:customer_groups,id',
            'credit_limit' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $customer = Customer::findOrFail($id);

        $customer->update([
            'name' => $request->name,
            'rtn' => $request->rtn,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'customer_group_id' => $request->customer_group_id,
            'credit_limit' => $request->credit_limit ?? 0,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cliente actualizado exitosamente',
            'data' => $customer,
        ]);
    }

    /**
     * Remove the specified customer
     */
    public function destroy(string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        // Check if customer has sales
        if ($customer->sales()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un cliente con ventas asociadas'
            ], 422);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cliente eliminado exitosamente',
        ]);
    }
}
