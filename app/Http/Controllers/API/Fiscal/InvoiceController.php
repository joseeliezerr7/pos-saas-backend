<?php

namespace App\Http\Controllers\API\Fiscal;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Fiscal\Invoice;
use App\Models\Sale\Sale;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Get all invoices
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['sale.details.product', 'sale.customer', 'cai'])
            ->where('tenant_id', auth()->user()->tenant_id);

        // Filter by user's branch (if user has assigned branch)
        $userBranchId = auth()->user()->branch_id;
        if ($userBranchId) {
            $query->whereHas('sale', fn($q) => $q->where('branch_id', $userBranchId));
        }

        // Filters
        if ($request->has('cai_id')) {
            $query->where('cai_id', $request->cai_id);
        }

        if ($request->filled('status')) {
            $isVoided = $request->status === 'voided' ? 1 : 0;
            $query->where('is_voided', $isVoided);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('issued_at', [$request->date_from, $request->date_to]);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_rtn', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'issued_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 20);
        $invoices = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => InvoiceResource::collection($invoices),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    /**
     * Create a new invoice from a sale
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sale_id' => 'required|exists:sales,id',
            'customer_rtn' => 'nullable|string|max:14',
            'customer_name' => 'nullable|string|max:255',
            'customer_address' => 'nullable|string|max:500',
            'document_type' => 'nullable|string|in:FACTURA,RECIBO',
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
            ->findOrFail($request->sale_id);

        try {
            $invoice = $this->invoiceService->generateInvoice($sale, $request->all());

            // Send email notification if enabled
            $company = auth()->user()->company;
            $notificationSettings = $company->notification_settings ?? [];

            if (isset($notificationSettings['send_invoice_email']) && $notificationSettings['send_invoice_email']) {
                // Send email to customer if they have an email
                if ($invoice->customer && $invoice->customer->email) {
                    \Mail::to($invoice->customer->email)->send(new \App\Mail\InvoiceSent($invoice));
                }
            }

            return response()->json([
                'success' => true,
                'data' => new InvoiceResource($invoice),
                'message' => 'Factura generada exitosamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVOICE_GENERATION_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get a single invoice
     */
    public function show(int $id): JsonResponse
    {
        $invoice = Invoice::with(['sale.details.product', 'sale.customer', 'cai'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new InvoiceResource($invoice),
        ]);
    }

    /**
     * Void an invoice
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

        $invoice = Invoice::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        if ($invoice->status === 'voided') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVOICE_ALREADY_VOIDED',
                    'message' => 'La factura ya está anulada',
                ],
            ], 400);
        }

        try {
            // Pass text as notes, and use 'OTRO' as the enum reason
            $this->invoiceService->voidInvoice($invoice, 'OTRO', $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Factura anulada exitosamente',
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
     * Download invoice PDF
     */
    public function downloadPDF(int $id): mixed
    {
        $invoice = Invoice::with(['sale.details.product', 'sale.customer', 'sale.branch', 'cai'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        $pdf = \PDF::loadView('invoices.pdf', compact('invoice'));

        return $pdf->download("factura-{$invoice->invoice_number}.pdf");
    }

    /**
     * Send invoice via email
     */
    public function sendEmail(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
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

        $invoice = Invoice::with(['sale.details.product', 'sale.customer', 'cai'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        try {
            $this->invoiceService->sendEmail($invoice, $request->email);

            return response()->json([
                'success' => true,
                'message' => 'Factura enviada por correo exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'EMAIL_SEND_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Validate invoice number format
     */
    public function validateInvoiceNumber(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'number' => 'required|string',
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

        $isValid = $this->invoiceService->validateInvoiceNumber($request->number);

        return response()->json([
            'success' => true,
            'data' => [
                'is_valid' => $isValid,
            ],
        ]);
    }
}
