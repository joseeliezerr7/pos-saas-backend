<?php

namespace App\Services;

// use App\Events\InvoiceGenerated;
use App\Models\Fiscal\Invoice;
use App\Models\Sale\Sale;
use App\Utils\NumberToWords;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        protected CorrelativeService $correlativeService,
        protected NumberToWords $numberToWords
    ) {}

    /**
     * Generate invoice from a sale
     */
    public function generateInvoice(Sale $sale, array $data = []): Invoice
    {
        return DB::transaction(function () use ($sale, $data) {
            // Validate sale doesn't already have an invoice
            if ($sale->hasInvoice()) {
                throw new \Exception('La venta ya tiene una factura generada');
            }

            // Get next correlative
            $correlative = $this->correlativeService->getNextCorrelative(
                $sale->branch_id,
                $data['document_type'] ?? 'FACTURA'
            );

            $cai = $correlative->cai;

            // Calculate amounts
            $subtotalExempt = $sale->details()
                ->whereHas('product', fn($q) => $q->where('tax_type', 'exempt'))
                ->sum(DB::raw('quantity * price'));

            $subtotalTaxed = $sale->subtotal - $subtotalExempt;

            // Customer data (required by SAR)
            $customerRtn = $data['customer_rtn'] ?? $sale->customer_rtn ?? config('fiscal.sar.rtn.consumer_final');
            // Remove dashes from RTN to fit in varchar(14)
            $customerRtn = str_replace('-', '', $customerRtn);
            $customerName = $data['customer_name'] ?? $sale->customer_name ?? 'CONSUMIDOR FINAL';

            // Create invoice
            $invoice = Invoice::create([
                'tenant_id' => $sale->tenant_id,
                'sale_id' => $sale->id,
                'cai_id' => $cai->id,
                'correlative_id' => $correlative->id,
                'invoice_number' => $correlative->formatted_number,
                'cai_number' => $cai->cai_number,
                'customer_rtn' => $customerRtn,
                'customer_name' => $customerName,
                'customer_address' => $data['customer_address'] ?? null,
                'subtotal_exempt' => $subtotalExempt,
                'subtotal_taxed' => $subtotalTaxed,
                'subtotal' => $sale->subtotal,
                'tax' => $sale->tax,
                'discount' => $sale->discount,
                'total' => $sale->total,
                'total_in_words' => $this->numberToWords->convert($sale->total),
                'issued_at' => now(),
                'cai_expiration_date' => $cai->expiration_date,
                'range_authorized' => $this->formatRangeAuthorized($cai),
            ]);

            // Dispatch event (commented out - event class needs to be created)
            // event(new InvoiceGenerated($invoice));

            return $invoice;
        });
    }

    /**
     * Void an invoice
     */
    public function voidInvoice(Invoice $invoice, string $reason, ?string $notes = null): bool
    {
        if (!$invoice->canBeVoided()) {
            throw new \Exception('Esta factura no puede ser anulada');
        }

        return DB::transaction(function () use ($invoice, $reason, $notes) {
            // Void invoice
            $result = $invoice->void($reason, $notes);

            if (!$result) {
                throw new \Exception('No se pudo anular la factura');
            }

            // Void correlative
            $this->correlativeService->voidCorrelative($invoice->correlative);

            return true;
        });
    }

    /**
     * Format authorized range for invoice
     */
    protected function formatRangeAuthorized($cai): string
    {
        return sprintf(
            'Del %s al %s',
            $cai->range_start,
            $cai->range_end
        );
    }

    /**
     * Validate invoice data according to SAR requirements
     */
    public function validateInvoiceData(array $data): array
    {
        $errors = [];

        // Required fields
        $requiredFields = config('fiscal.sar.invoice.required_fields', []);

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[$field] = "El campo {$field} es requerido por la SAR";
            }
        }

        // Validate RTN format
        if (isset($data['customer_rtn'])) {
            $rtnPattern = config('fiscal.sar.rtn.format');
            if (!preg_match($rtnPattern, $data['customer_rtn'])) {
                $errors['customer_rtn'] = 'El formato del RTN no es válido (debe ser XXXX-XXXX-XXXXX)';
            }
        }

        return $errors;
    }

    /**
     * Generate PDF invoice
     */
    public function generatePDF(Invoice $invoice): string
    {
        // This would integrate with DomPDF or similar
        // Return path to generated PDF
        return storage_path("app/invoices/invoice_{$invoice->id}.pdf");
    }

    /**
     * Send invoice by email
     */
    public function sendByEmail(Invoice $invoice, string $email): bool
    {
        // This would integrate with mail system
        // For now, just return success
        return true;
    }

    /**
     * Get invoice statistics for SAR reporting
     */
    public function getSARStatistics(int $tenantId, string $startDate, string $endDate): array
    {
        $invoices = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('issued_at', [$startDate, $endDate])
            ->get();

        $voided = $invoices->where('is_voided', true);
        $valid = $invoices->where('is_voided', false);

        return [
            'total_invoices' => $invoices->count(),
            'valid_invoices' => $valid->count(),
            'voided_invoices' => $voided->count(),
            'total_sales' => $valid->sum('total'),
            'total_tax' => $valid->sum('tax'),
            'subtotal_exempt' => $valid->sum('subtotal_exempt'),
            'subtotal_taxed' => $valid->sum('subtotal_taxed'),
        ];
    }
}
