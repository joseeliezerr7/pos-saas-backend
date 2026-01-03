<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Traits\FiltersByBranch;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ExpenseController extends Controller
{
    use FiltersByBranch;
    /**
     * Display a listing of expenses
     */
    public function index(Request $request): JsonResponse
    {
        $query = Expense::with(['branch', 'user', 'supplier'])
            ->where('tenant_id', auth()->user()->tenant_id);

        // Apply branch filter automatically
        $query = $this->applyBranchFilter($query, $request->branch_id);

        // Filter by category
        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        // Filter by payment method
        if ($request->filled('payment_method')) {
            $query->byPaymentMethod($request->payment_method);
        }

        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('receipt_number', 'like', "%{$search}%")
                  ->orWhere('invoice_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'expense_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $expenses = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $expenses,
        ]);
    }

    /**
     * Get expense categories
     */
    public function categories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Expense::getCategories(),
        ]);
    }

    /**
     * Get payment methods
     */
    public function paymentMethods(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Expense::getPaymentMethods(),
        ]);
    }

    /**
     * Get expense statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $query = Expense::where('tenant_id', auth()->user()->tenant_id);

        // Filter by branch if provided
        if ($request->filled('branch_id')) {
            $query->byBranch($request->branch_id);
        }

        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        $totalExpenses = $query->sum('amount');
        $expensesByCategory = Expense::where('tenant_id', auth()->user()->tenant_id)
            ->when($request->filled('branch_id'), fn($q) => $q->byBranch($request->branch_id))
            ->when($request->filled('start_date') && $request->filled('end_date'),
                fn($q) => $q->byDateRange($request->start_date, $request->end_date))
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->category => [
                    'label' => Expense::getCategories()[$item->category] ?? $item->category,
                    'total' => $item->total
                ]];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'total_expenses' => $totalExpenses,
                'expenses_by_category' => $expensesByCategory,
            ],
        ]);
    }

    /**
     * Store a newly created expense
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'category' => 'required|in:' . implode(',', array_keys(Expense::getCategories())),
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'payment_method' => 'required|in:' . implode(',', array_keys(Expense::getPaymentMethods())),
            'receipt_number' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'attachment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $expense = Expense::create([
            'tenant_id' => auth()->user()->tenant_id,
            'branch_id' => $request->branch_id,
            'user_id' => auth()->id(),
            'supplier_id' => $request->supplier_id,
            'category' => $request->category,
            'description' => $request->description,
            'amount' => $request->amount,
            'expense_date' => $request->expense_date,
            'payment_method' => $request->payment_method,
            'receipt_number' => $request->receipt_number,
            'invoice_number' => $request->invoice_number,
            'notes' => $request->notes,
            'attachment' => $request->attachment,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gasto registrado exitosamente',
            'data' => $expense->load(['branch', 'user', 'supplier']),
        ], 201);
    }

    /**
     * Display the specified expense
     */
    public function show(string $id): JsonResponse
    {
        $expense = Expense::with(['branch', 'user', 'supplier'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $expense,
        ]);
    }

    /**
     * Update the specified expense
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $expense = Expense::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'category' => 'required|in:' . implode(',', array_keys(Expense::getCategories())),
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'payment_method' => 'required|in:' . implode(',', array_keys(Expense::getPaymentMethods())),
            'receipt_number' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'attachment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $expense->update([
            'branch_id' => $request->branch_id,
            'supplier_id' => $request->supplier_id,
            'category' => $request->category,
            'description' => $request->description,
            'amount' => $request->amount,
            'expense_date' => $request->expense_date,
            'payment_method' => $request->payment_method,
            'receipt_number' => $request->receipt_number,
            'invoice_number' => $request->invoice_number,
            'notes' => $request->notes,
            'attachment' => $request->attachment,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gasto actualizado exitosamente',
            'data' => $expense->load(['branch', 'user', 'supplier']),
        ]);
    }

    /**
     * Remove the specified expense
     */
    public function destroy(string $id): JsonResponse
    {
        $expense = Expense::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Gasto eliminado exitosamente',
        ]);
    }
}
