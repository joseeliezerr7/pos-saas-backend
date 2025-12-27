<?php

namespace App\Http\Controllers\API\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseController extends Controller
{
    /**
     * Display a listing of purchases
     */
    public function index(Request $request): JsonResponse
    {
        $query = Purchase::with(['supplier', 'user', 'branch'])
            ->withCount('details');

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('purchase_number', 'like', "%{$search}%")
                  ->orWhere('supplier_invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment status
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Filter by supplier
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $purchases = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $purchases,
        ]);
    }

    /**
     * Store a newly created purchase
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|exists:suppliers,id',
            'expected_date' => 'nullable|date',
            'supplier_invoice_number' => 'nullable|string|max:255',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.product_id' => 'required|exists:products,id',
            'details.*.quantity' => 'required|numeric|min:0.01',
            'details.*.cost' => 'required|numeric|min:0',
            'details.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'details.*.discount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create purchase
            $purchase = Purchase::create([
                'tenant_id' => auth()->user()->tenant_id,
                'branch_id' => auth()->user()->branch_id ?? 1,
                'supplier_id' => $request->supplier_id,
                'user_id' => auth()->id(),
                'supplier_invoice_number' => $request->supplier_invoice_number,
                'discount' => $request->discount ?? 0,
                'notes' => $request->notes,
                'expected_date' => $request->expected_date,
                'status' => 'draft',
                'payment_status' => 'pending',
                'subtotal' => 0,
                'tax' => 0,
                'total' => 0,
            ]);

            // Create purchase details
            foreach ($request->details as $detail) {
                $product = \App\Models\Product::find($detail['product_id']);

                PurchaseDetail::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $detail['product_id'],
                    'product_name' => $product->name,
                    'quantity' => $detail['quantity'],
                    'cost' => $detail['cost'],
                    'tax_rate' => $detail['tax_rate'] ?? 0,
                    'discount' => $detail['discount'] ?? 0,
                    'subtotal' => 0, // Will be calculated by model event
                ]);
            }

            // Calculate totals
            $purchase->calculateTotals();
            $purchase->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Compra creada exitosamente',
                'data' => $purchase->load(['details.product', 'supplier']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la compra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified purchase
     */
    public function show(string $id): JsonResponse
    {
        $purchase = Purchase::with(['details.product', 'supplier', 'user', 'approvedBy', 'branch'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $purchase,
        ]);
    }

    /**
     * Update the specified purchase
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $purchase = Purchase::findOrFail($id);

        // Only draft purchases can be edited
        if ($purchase->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden editar compras en estado borrador'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|exists:suppliers,id',
            'expected_date' => 'nullable|date',
            'supplier_invoice_number' => 'nullable|string|max:255',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'details' => 'required|array|min:1',
            'details.*.product_id' => 'required|exists:products,id',
            'details.*.quantity' => 'required|numeric|min:0.01',
            'details.*.cost' => 'required|numeric|min:0',
            'details.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'details.*.discount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Update purchase
            $purchase->update([
                'supplier_id' => $request->supplier_id,
                'supplier_invoice_number' => $request->supplier_invoice_number,
                'discount' => $request->discount ?? 0,
                'notes' => $request->notes,
                'expected_date' => $request->expected_date,
            ]);

            // Delete old details
            $purchase->details()->delete();

            // Create new details
            foreach ($request->details as $detail) {
                $product = \App\Models\Product::find($detail['product_id']);

                PurchaseDetail::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $detail['product_id'],
                    'product_name' => $product->name,
                    'quantity' => $detail['quantity'],
                    'cost' => $detail['cost'],
                    'tax_rate' => $detail['tax_rate'] ?? 0,
                    'discount' => $detail['discount'] ?? 0,
                    'subtotal' => 0,
                ]);
            }

            // Recalculate totals
            $purchase->calculateTotals();
            $purchase->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Compra actualizada exitosamente',
                'data' => $purchase->load(['details.product', 'supplier']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la compra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified purchase
     */
    public function destroy(string $id): JsonResponse
    {
        $purchase = Purchase::findOrFail($id);

        // Only draft purchases can be deleted
        if ($purchase->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden eliminar compras en estado borrador'
            ], 400);
        }

        $purchase->delete();

        return response()->json([
            'success' => true,
            'message' => 'Compra eliminada exitosamente',
        ]);
    }

    /**
     * Receive a purchase (update stock)
     */
    public function receive(Request $request, string $id): JsonResponse
    {
        $purchase = Purchase::with('details')->findOrFail($id);

        if (!$purchase->canBeReceived()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta compra no puede ser recibida'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'details' => 'required|array',
            'details.*.detail_id' => 'required|exists:purchase_details,id',
            'details.*.quantity_received' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            foreach ($request->details as $detailData) {
                $detail = PurchaseDetail::findOrFail($detailData['detail_id']);

                // Update quantity received
                $newQuantityReceived = $detail->quantity_received + $detailData['quantity_received'];

                if ($newQuantityReceived > $detail->quantity) {
                    throw new \Exception("La cantidad recibida no puede exceder la cantidad ordenada para {$detail->product_name}");
                }

                $detail->quantity_received = $newQuantityReceived;
                $detail->save();

                // Update stock
                $stock = Stock::firstOrCreate([
                    'tenant_id' => $purchase->tenant_id,
                    'branch_id' => $purchase->branch_id,
                    'product_id' => $detail->product_id,
                ], [
                    'quantity' => 0,
                ]);

                $stock->quantity += $detailData['quantity_received'];
                $stock->save();

                // Update product cost
                $product = $detail->product;
                $product->cost = $detail->cost;
                $product->save();
            }

            // Update purchase status
            $allReceived = $purchase->details->every(function ($detail) {
                return $detail->isFullyReceived();
            });

            if ($allReceived) {
                $purchase->status = 'received';
                $purchase->received_at = now();
            } else {
                $purchase->status = 'partial';
            }

            if (!$purchase->ordered_at) {
                $purchase->status = 'ordered';
                $purchase->ordered_at = now();
            }

            $purchase->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Compra recibida exitosamente',
                'data' => $purchase->fresh(['details.product', 'supplier']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al recibir la compra: ' . $e->getMessage()
            ], 500);
        }
    }
}
