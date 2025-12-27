<?php

namespace App\Http\Controllers\API\Report;

use App\Http\Controllers\Controller;
use App\Models\Fiscal\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SARReportController extends Controller
{
    /**
     * Generate monthly SAR report (for tax purposes)
     */
    public function monthly(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'required|integer|min:1|max:12',
            'branch_id' => 'nullable|exists:branches,id',
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

        $tenantId = auth()->user()->tenant_id;

        // Date range for the month
        $dateFrom = sprintf('%04d-%02d-01', $request->year, $request->month);
        $dateTo = date('Y-m-t', strtotime($dateFrom));

        // Get invoices for the period
        $query = Invoice::with(['sale.details.product', 'cai'])
            ->where('tenant_id', $tenantId)
            ->whereBetween('issued_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($request->has('branch_id')) {
            $query->whereHas('sale', function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            });
        }

        $invoices = $query->get();

        // Group by status
        $summary = [
            'period' => sprintf('%s/%04d', str_pad($request->month, 2, '0', STR_PAD_LEFT), $request->year),
            'total_invoices' => $invoices->count(),
            'valid_invoices' => $invoices->where('status', 'valid')->count(),
            'voided_invoices' => $invoices->where('status', 'voided')->count(),
            'total_sales_taxed' => $invoices->where('status', 'valid')->sum('subtotal_taxed'),
            'total_sales_exempt' => $invoices->where('status', 'valid')->sum('subtotal_exempt'),
            'total_tax' => $invoices->where('status', 'valid')->sum('tax'),
            'total_revenue' => $invoices->where('status', 'valid')->sum('total'),
        ];

        // Group by CAI
        $byCai = $invoices->groupBy('cai_id')->map(function ($group) {
            $cai = $group->first()->cai;
            return [
                'cai_number' => $cai->cai_number,
                'total_invoices' => $group->count(),
                'valid_invoices' => $group->where('status', 'valid')->count(),
                'voided_invoices' => $group->where('status', 'voided')->count(),
                'total_revenue' => $group->where('status', 'valid')->sum('total'),
            ];
        })->values();

        // Invoice details
        $details = $invoices->map(function ($invoice) {
            return [
                'invoice_number' => $invoice->invoice_number,
                'cai_number' => $invoice->cai_number,
                'customer_rtn' => $invoice->customer_rtn,
                'customer_name' => $invoice->customer_name,
                'subtotal_taxed' => (float) $invoice->subtotal_taxed,
                'subtotal_exempt' => (float) $invoice->subtotal_exempt,
                'tax' => (float) $invoice->tax,
                'total' => (float) $invoice->total,
                'status' => $invoice->status,
                'issued_at' => $invoice->issued_at->format('Y-m-d H:i:s'),
                'voided_at' => $invoice->voided_at?->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'by_cai' => $byCai,
                'details' => $details,
            ],
        ]);
    }

    /**
     * Generate DEI report (Declaración Electrónica de Impuestos)
     */
    public function dei(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'required|integer|min:1|max:12',
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

        $tenantId = auth()->user()->tenant_id;

        // Date range for the month
        $dateFrom = sprintf('%04d-%02d-01', $request->year, $request->month);
        $dateTo = date('Y-m-t', strtotime($dateFrom));

        // Get valid invoices for the period
        $invoices = Invoice::where('tenant_id', $tenantId)
            ->where('status', 'valid')
            ->whereBetween('issued_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->get();

        // Calculate DEI data (simplified version)
        $deiData = [
            'period' => sprintf('%02d/%04d', $request->month, $request->year),
            'ventas_gravadas' => $invoices->sum('subtotal_taxed'),
            'ventas_exentas' => $invoices->sum('subtotal_exempt'),
            'ventas_totales' => $invoices->sum('subtotal'),
            'impuesto_ventas' => $invoices->sum('tax'), // 15% ISV
            'total_ingresos' => $invoices->sum('total'),
        ];

        return response()->json([
            'success' => true,
            'data' => $deiData,
            'message' => 'Datos preliminares para DEI. Consulte con su contador para valores finales.',
        ]);
    }
}
