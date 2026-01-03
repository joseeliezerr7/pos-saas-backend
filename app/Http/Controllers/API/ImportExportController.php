<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ImportExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ImportExportController extends Controller
{
    protected $importExportService;

    public function __construct(ImportExportService $importExportService)
    {
        $this->importExportService = $importExportService;
    }

    /**
     * Import products from CSV
     */
    public function importProducts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240', // Max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();

            // Parse file based on extension
            if (in_array($extension, ['xlsx', 'xls'])) {
                $data = $this->importExportService->parseExcel($file->getRealPath());
            } else {
                $content = file_get_contents($file->getRealPath());
                $data = $this->importExportService->parseCsv($content);
            }

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron datos válidos en el archivo'
                ], 400);
            }

            // Import products
            $tenantId = auth()->user()->tenant_id;
            $result = $this->importExportService->importProducts($data, $tenantId);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import customers from CSV
     */
    public function importCustomers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();

            // Parse file based on extension
            if (in_array($extension, ['xlsx', 'xls'])) {
                $data = $this->importExportService->parseExcel($file->getRealPath());
            } else {
                $content = file_get_contents($file->getRealPath());
                $data = $this->importExportService->parseCsv($content);
            }

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron datos válidos en el archivo'
                ], 400);
            }

            $tenantId = auth()->user()->tenant_id;
            $result = $this->importExportService->importCustomers($data, $tenantId);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export products to Excel
     */
    public function exportProducts(Request $request)
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            $filters = $request->only(['category_id', 'is_active']);

            $tempFile = $this->importExportService->exportProductsExcel($tenantId, $filters);

            $filename = 'products_' . date('Y-m-d_His') . '.xlsx';

            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar productos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export customers to Excel
     */
    public function exportCustomers()
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            $tempFile = $this->importExportService->exportCustomersExcel($tenantId);

            $filename = 'customers_' . date('Y-m-d_His') . '.xlsx';

            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar clientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export sales report to Excel
     */
    public function exportSales(Request $request)
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            $userBranchId = auth()->user()->branch_id;

            $filters = $request->only(['start_date', 'end_date']);

            // Add branch filter if user has assigned branch
            if ($userBranchId) {
                $filters['branch_id'] = $userBranchId;
            } elseif ($request->has('branch_id')) {
                $filters['branch_id'] = $request->branch_id;
            }

            $tempFile = $this->importExportService->exportSalesExcel($tenantId, $filters);

            $filename = 'sales_report_' . date('Y-m-d_His') . '.xlsx';

            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar ventas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get CSV template for products
     */
    public function getProductTemplate()
    {
        try {
            $csv = $this->importExportService->getProductTemplate();

            return response($csv, 200)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="product_template.csv"');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar plantilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get CSV template for customers
     */
    public function getCustomerTemplate()
    {
        try {
            $csv = $this->importExportService->getCustomerTemplate();

            return response($csv, 200)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="customer_template.csv"');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar plantilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Excel template for products
     */
    public function getProductTemplateExcel()
    {
        try {
            $tempFile = $this->importExportService->getProductTemplateExcel();
            $filename = 'product_template.xlsx';

            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar plantilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Excel template for customers
     */
    public function getCustomerTemplateExcel()
    {
        try {
            $tempFile = $this->importExportService->getCustomerTemplateExcel();
            $filename = 'customer_template.xlsx';

            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar plantilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get CSV template for inventory
     */
    public function getInventoryTemplate()
    {
        try {
            $csv = $this->importExportService->getInventoryTemplate();

            return response($csv, 200)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="inventory_template.csv"');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar plantilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Excel template for inventory
     */
    public function getInventoryTemplateExcel()
    {
        try {
            $tempFile = $this->importExportService->getInventoryTemplateExcel();
            $filename = 'inventory_template.xlsx';

            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar plantilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import inventory from CSV/Excel
     */
    public function importInventory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
            'branch_id' => 'required|integer|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();

            // Parse file based on extension
            if (in_array($extension, ['xlsx', 'xls'])) {
                $data = $this->importExportService->parseExcel($file->getRealPath());
            } else {
                $content = file_get_contents($file->getRealPath());
                $data = $this->importExportService->parseCsv($content);
            }

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron datos válidos en el archivo'
                ], 400);
            }

            $tenantId = auth()->user()->tenant_id;
            $branchId = $request->input('branch_id');
            $result = $this->importExportService->importInventory($data, $tenantId, $branchId);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get CSV template for price update
     */
    public function getPriceUpdateTemplate()
    {
        try {
            $csv = $this->importExportService->getPriceUpdateTemplate();

            return response($csv, 200)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="price_update_template.csv"');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar plantilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Excel template for price update
     */
    public function getPriceUpdateTemplateExcel()
    {
        try {
            $tempFile = $this->importExportService->getPriceUpdateTemplateExcel();
            $filename = 'price_update_template.xlsx';

            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar plantilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update prices from CSV/Excel
     */
    public function bulkUpdatePrices(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();

            // Parse file based on extension
            if (in_array($extension, ['xlsx', 'xls'])) {
                $data = $this->importExportService->parseExcel($file->getRealPath());
            } else {
                $content = file_get_contents($file->getRealPath());
                $data = $this->importExportService->parseCsv($content);
            }

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron datos válidos en el archivo'
                ], 400);
            }

            $tenantId = auth()->user()->tenant_id;
            $result = $this->importExportService->bulkUpdatePrices($data, $tenantId);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview import data before processing
     */
    public function previewImport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();

            // Parse file based on extension
            if (in_array($extension, ['xlsx', 'xls'])) {
                $data = $this->importExportService->parseExcel($file->getRealPath());
            } else {
                $content = file_get_contents($file->getRealPath());
                $data = $this->importExportService->parseCsv($content);
            }

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron datos válidos en el archivo'
                ], 400);
            }

            // Return first 10 rows for preview
            $preview = array_slice($data, 0, 10);

            return response()->json([
                'success' => true,
                'preview' => $preview,
                'total_rows' => count($data),
                'columns' => !empty($data) ? array_keys($data[0]) : []
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }
}
