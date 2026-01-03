<?php

namespace App\Http\Controllers\API\Credit;

use App\Http\Controllers\Controller;
use App\Models\Credit\CustomerPayment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerPaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    /**
     * List customer payments
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomerPayment::with(['customer', 'user', 'allocations.creditSale.sale'])
            ->where('tenant_id', auth()->user()->tenant_id);

        // Filter by user's branch (if user has assigned branch)
        $userBranchId = auth()->user()->branch_id;
        if ($userBranchId) {
            $query->where('branch_id', $userBranchId);
        } elseif ($request->filled('branch_id')) {
            // Admin can filter by specific branch
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('payment_date', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ]);
        }

        $payments = $query->orderBy('payment_date', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Record a new payment
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'branch_id' => 'required|exists:branches,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,card,transfer,check,qr',
            'payment_details' => 'nullable|array',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
            'allocation_type' => 'nullable|in:fifo,specific',
            'specific_allocations' => 'required_if:allocation_type,specific|array',
            'specific_allocations.*.credit_sale_id' => 'required|exists:credit_sales,id',
            'specific_allocations.*.amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $payment = $this->paymentService->recordPayment($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Pago registrado exitosamente',
                'data' => $payment,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYMENT_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Show payment details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $payment = CustomerPayment::with(['customer', 'user', 'branch', 'allocations.creditSale.sale'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $payment,
        ]);
    }

    /**
     * Generate payment receipt PDF
     *
     * @param int $id
     * @return mixed
     */
    public function downloadReceipt(int $id)
    {
        $payment = CustomerPayment::with([
            'customer',
            'user',
            'branch.company',
            'allocations.creditSale.sale'
        ])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        try {
            $pdfPath = $this->paymentService->generateReceipt($payment);

            return response()->download($pdfPath, "recibo-{$payment->payment_number}.pdf");
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RECEIPT_GENERATION_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
