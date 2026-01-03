<?php

namespace App\Http\Controllers\API\Credit;

use App\Http\Controllers\Controller;
use App\Models\Credit\CreditSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditSaleController extends Controller
{
    /**
     * List credit sales (accounts receivable)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = CreditSale::with(['customer', 'sale', 'allocations.customerPayment'])
            ->where('tenant_id', auth()->user()->tenant_id);

        // Filter by user's branch (if user has assigned branch)
        $userBranchId = auth()->user()->branch_id;
        if ($userBranchId) {
            $query->whereHas('sale', fn($q) => $q->where('branch_id', $userBranchId));
        } elseif ($request->filled('branch_id')) {
            // Admin can filter by specific branch
            $query->whereHas('sale', fn($q) => $q->where('branch_id', $request->branch_id));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->get('overdue_only')) {
            $query->overdue();
        }

        $sortBy = $request->get('sort_by', 'due_date');
        $sortOrder = $request->get('sort_order', 'asc');

        $creditSales = $query->orderBy($sortBy, $sortOrder)
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $creditSales,
        ]);
    }

    /**
     * Get pending credit sales for a customer
     *
     * @param int $customerId
     * @return JsonResponse
     */
    public function customerPending(int $customerId): JsonResponse
    {
        $pendingSales = CreditSale::where('customer_id', $customerId)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->pending()
            ->with('sale')
            ->orderBy('due_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pendingSales,
        ]);
    }

    /**
     * Show credit sale details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $creditSale = CreditSale::with(['customer', 'sale.details', 'allocations.customerPayment'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $creditSale,
        ]);
    }
}
