<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Customer;
use App\Models\Inventory\Stock;
use App\Models\Sale\Sale;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsReader;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ImportExportService
{
    /**
     * Import products from CSV data
     *
     * @param array $data CSV data as array
     * @param int $tenantId
     * @return array
     */
    public function importProducts(array $data, int $tenantId): array
    {
        $imported = 0;
        $errors = [];
        $updated = 0;

        DB::beginTransaction();

        try {
            foreach ($data as $index => $row) {
                $rowNumber = $index + 2; // +2 because index starts at 0 and first row is header

                // Validate row data
                $validator = Validator::make($row, [
                    'name' => 'required|string|max:255',
                    'sku' => 'nullable|string|max:100',
                    'barcode' => 'nullable|string|max:100',
                    'category_id' => 'nullable|integer|exists:categories,id',
                    'brand_id' => 'nullable|integer|exists:brands,id',
                    'price' => 'required|numeric|min:0',
                    'cost' => 'nullable|numeric|min:0',
                    'stock_min' => 'nullable|integer|min:0',
                    'stock_max' => 'nullable|integer|min:0',
                    'is_active' => 'nullable|boolean',
                    'tax_rate' => 'nullable|numeric|min:0|max:100',
                    'tax_type' => 'nullable|in:inclusive,exclusive,exempt,taxed',
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => $validator->errors()->toArray()
                    ];
                    continue;
                }

                // Check if product exists by SKU or barcode
                $existingProduct = null;
                if (!empty($row['sku'])) {
                    $existingProduct = Product::where('tenant_id', $tenantId)
                        ->where('sku', $row['sku'])
                        ->first();
                }

                if (!$existingProduct && !empty($row['barcode'])) {
                    $existingProduct = Product::where('tenant_id', $tenantId)
                        ->where('barcode', $row['barcode'])
                        ->first();
                }

                $productData = array_merge($row, ['tenant_id' => $tenantId]);

                if ($existingProduct) {
                    // Update existing product
                    $existingProduct->update($productData);
                    $updated++;
                } else {
                    // Create new product
                    Product::create($productData);
                    $imported++;
                }
            }

            DB::commit();

            return [
                'success' => true,
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors,
                'total_rows' => count($data)
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Error during import: ' . $e->getMessage(),
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors
            ];
        }
    }

    /**
     * Import customers from CSV data
     *
     * @param array $data
     * @param int $tenantId
     * @return array
     */
    public function importCustomers(array $data, int $tenantId): array
    {
        $imported = 0;
        $errors = [];
        $updated = 0;

        DB::beginTransaction();

        try {
            foreach ($data as $index => $row) {
                $rowNumber = $index + 2;

                $validator = Validator::make($row, [
                    'name' => 'required|string|max:255',
                    'email' => 'nullable|email|max:255',
                    'phone' => 'nullable|string|max:20',
                    'document_number' => 'nullable|string|max:50',
                    'document_type' => 'nullable|in:ID,PASSPORT,RUC,RTN',
                    'address' => 'nullable|string',
                    'city' => 'nullable|string|max:100',
                    'state' => 'nullable|string|max:100',
                    'postal_code' => 'nullable|string|max:20',
                    'tax_exempt' => 'nullable|boolean',
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => $validator->errors()->toArray()
                    ];
                    continue;
                }

                // Check if customer exists by email or document
                $existingCustomer = null;
                if (!empty($row['email'])) {
                    $existingCustomer = Customer::where('tenant_id', $tenantId)
                        ->where('email', $row['email'])
                        ->first();
                }

                if (!$existingCustomer && !empty($row['document_number'])) {
                    $existingCustomer = Customer::where('tenant_id', $tenantId)
                        ->where('document_number', $row['document_number'])
                        ->first();
                }

                $customerData = array_merge($row, ['tenant_id' => $tenantId]);

                if ($existingCustomer) {
                    $existingCustomer->update($customerData);
                    $updated++;
                } else {
                    Customer::create($customerData);
                    $imported++;
                }
            }

            DB::commit();

            return [
                'success' => true,
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors,
                'total_rows' => count($data)
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Error during import: ' . $e->getMessage(),
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors
            ];
        }
    }

    /**
     * Export products to CSV format
     *
     * @param int $tenantId
     * @param array $filters
     * @return string CSV content
     */
    public function exportProducts(int $tenantId, array $filters = []): string
    {
        $query = Product::where('tenant_id', $tenantId)
            ->with(['category', 'brand']);

        // Apply filters
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        $products = $query->get();

        // Create CSV
        $csv = [];

        // Header row
        $csv[] = [
            'ID',
            'Name',
            'SKU',
            'Barcode',
            'Category',
            'Brand',
            'Price',
            'Cost',
            'Stock',
            'Stock Min',
            'Stock Max',
            'Tax Rate',
            'Tax Type',
            'Active'
        ];

        // Data rows
        foreach ($products as $product) {
            $csv[] = [
                $product->id,
                $product->name,
                $product->sku ?? '',
                $product->barcode ?? '',
                $product->category->name ?? '',
                $product->brand->name ?? '',
                $product->price,
                $product->cost ?? '',
                $product->stock ?? 0,
                $product->stock_min ?? '',
                $product->stock_max ?? '',
                $product->tax_rate ?? '',
                $product->tax_type ?? 'inclusive',
                $product->is_active ? 'Yes' : 'No'
            ];
        }

        return $this->arrayToCsv($csv);
    }

    /**
     * Export customers to CSV format
     *
     * @param int $tenantId
     * @return string CSV content
     */
    public function exportCustomers(int $tenantId): string
    {
        $customers = Customer::where('tenant_id', $tenantId)->get();

        $csv = [];

        // Header row
        $csv[] = [
            'ID',
            'Name',
            'Email',
            'Phone',
            'Document Type',
            'Document Number',
            'Address',
            'City',
            'State',
            'Postal Code',
            'Tax Exempt'
        ];

        // Data rows
        foreach ($customers as $customer) {
            $csv[] = [
                $customer->id,
                $customer->name,
                $customer->email ?? '',
                $customer->phone ?? '',
                $customer->document_type ?? '',
                $customer->document_number ?? '',
                $customer->address ?? '',
                $customer->city ?? '',
                $customer->state ?? '',
                $customer->postal_code ?? '',
                $customer->tax_exempt ? 'Yes' : 'No'
            ];
        }

        return $this->arrayToCsv($csv);
    }

    /**
     * Export sales report to CSV
     *
     * @param int $tenantId
     * @param array $filters
     * @return string
     */
    public function exportSales(int $tenantId, array $filters = []): string
    {
        $query = Sale::where('tenant_id', $tenantId)
            ->with(['customer', 'user', 'branch']);

        // Apply date filters
        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $sales = $query->orderBy('created_at', 'desc')->get();

        $csv = [];

        // Header row
        $csv[] = [
            'Sale Number',
            'Date',
            'Customer',
            'Branch',
            'User',
            'Subtotal',
            'Discount',
            'Tax',
            'Total',
            'Payment Method',
            'Status'
        ];

        // Data rows
        foreach ($sales as $sale) {
            $csv[] = [
                $sale->sale_number,
                $sale->created_at->format('Y-m-d H:i:s'),
                $sale->customer->name ?? $sale->customer_name ?? 'General',
                $sale->branch->name ?? '',
                $sale->user->name ?? '',
                number_format($sale->subtotal, 2),
                number_format($sale->discount, 2),
                number_format($sale->tax, 2),
                number_format($sale->total, 2),
                $sale->payment_method,
                $sale->status
            ];
        }

        return $this->arrayToCsv($csv);
    }

    /**
     * Convert array to CSV string
     *
     * @param array $data
     * @return string
     */
    private function arrayToCsv(array $data): string
    {
        $output = fopen('php://temp', 'r+');

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Parse CSV file content to array
     *
     * @param string $content
     * @return array
     */
    public function parseCsv(string $content): array
    {
        $lines = explode("\n", $content);
        $data = [];
        $headers = null;

        foreach ($lines as $lineIndex => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $row = str_getcsv($line);

            if ($lineIndex === 0) {
                // First row is headers
                $headers = array_map(function($header) {
                    return strtolower(str_replace(' ', '_', trim($header)));
                }, $row);
                continue;
            }

            if ($headers && count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }

        return $data;
    }

    /**
     * Parse Excel file to array
     *
     * @param string $filePath Path to Excel file
     * @return array
     */
    public function parseExcel(string $filePath): array
    {
        try {
            // Determine reader based on file extension
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            if ($extension === 'xlsx') {
                $reader = new XlsxReader();
            } else {
                $reader = new XlsReader();
            }

            // Load spreadsheet
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows)) {
                return [];
            }

            $data = [];
            $headers = null;

            foreach ($rows as $index => $row) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                if ($index === 0) {
                    // First row is headers
                    $headers = array_map(function($header) {
                        return strtolower(str_replace(' ', '_', trim($header)));
                    }, $row);
                    continue;
                }

                if ($headers && count($row) > 0) {
                    // Combine headers with row data
                    $rowData = [];
                    foreach ($headers as $colIndex => $header) {
                        $rowData[$header] = isset($row[$colIndex]) ? trim($row[$colIndex]) : '';
                    }
                    $data[] = $rowData;
                }
            }

            return $data;

        } catch (\Exception $e) {
            throw new \Exception('Error parsing Excel file: ' . $e->getMessage());
        }
    }

    /**
     * Import inventory/stock from CSV data
     *
     * @param array $data CSV data as array
     * @param int $tenantId
     * @param int $branchId
     * @return array
     */
    public function importInventory(array $data, int $tenantId, int $branchId): array
    {
        $imported = 0;
        $errors = [];
        $updated = 0;

        DB::beginTransaction();

        try {
            foreach ($data as $index => $row) {
                $rowNumber = $index + 2;

                // Validate row data
                $validator = Validator::make($row, [
                    'sku' => 'required|string',
                    'quantity' => 'required|integer|min:0',
                    'location' => 'nullable|string|max:100',
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => $validator->errors()->toArray()
                    ];
                    continue;
                }

                // Find product by SKU or barcode
                $product = Product::where('tenant_id', $tenantId)
                    ->where(function($query) use ($row) {
                        $query->where('sku', $row['sku'])
                              ->orWhere('barcode', $row['sku']);
                    })
                    ->first();

                if (!$product) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => ['sku' => ["Producto no encontrado con SKU/Barcode: {$row['sku']}"]]
                    ];
                    continue;
                }

                // Check if stock exists
                $stock = Stock::where('product_id', $product->id)
                    ->where('branch_id', $branchId)
                    ->first();

                if ($stock) {
                    // Update existing stock
                    $oldQuantity = $stock->quantity;
                    $stock->quantity = $row['quantity'];
                    if (!empty($row['location'])) {
                        $stock->location = $row['location'];
                    }
                    $stock->save();
                    $updated++;

                    // Create stock movement
                    \App\Models\Inventory\StockMovement::create([
                        'tenant_id' => $tenantId,
                        'branch_id' => $branchId,
                        'product_id' => $product->id,
                        'type' => 'adjustment',
                        'quantity' => $row['quantity'] - $oldQuantity,
                        'previous_quantity' => $oldQuantity,
                        'new_quantity' => $row['quantity'],
                        'notes' => 'Importación de inventario inicial',
                        'user_id' => auth()->id(),
                    ]);
                } else {
                    // Create new stock
                    Stock::create([
                        'tenant_id' => $tenantId,
                        'branch_id' => $branchId,
                        'product_id' => $product->id,
                        'quantity' => $row['quantity'],
                        'location' => $row['location'] ?? null,
                    ]);
                    $imported++;

                    // Create stock movement
                    \App\Models\Inventory\StockMovement::create([
                        'tenant_id' => $tenantId,
                        'branch_id' => $branchId,
                        'product_id' => $product->id,
                        'type' => 'adjustment',
                        'quantity' => $row['quantity'],
                        'previous_quantity' => 0,
                        'new_quantity' => $row['quantity'],
                        'notes' => 'Importación de inventario inicial',
                        'user_id' => auth()->id(),
                    ]);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors,
                'total_rows' => count($data)
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Error during import: ' . $e->getMessage(),
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors
            ];
        }
    }

    /**
     * Get CSV template for products
     *
     * @return string
     */
    public function getProductTemplate(): string
    {
        $csv = [];
        $csv[] = ['name', 'sku', 'barcode', 'category_id', 'brand_id', 'price', 'cost', 'stock_min', 'stock_max', 'tax_rate', 'tax_type', 'is_active'];
        $csv[] = ['Example Product', 'SKU001', '1234567890', '1', '1', '100.00', '50.00', '10', '100', '15', 'inclusive', 'true'];

        return $this->arrayToCsv($csv);
    }

    /**
     * Get CSV template for customers
     *
     * @return string
     */
    public function getCustomerTemplate(): string
    {
        $csv = [];
        $csv[] = ['name', 'email', 'phone', 'document_type', 'document_number', 'address', 'city', 'state', 'postal_code', 'tax_exempt'];
        $csv[] = ['John Doe', 'john@example.com', '+504 1234-5678', 'ID', '0801199012345', '123 Main St', 'Tegucigalpa', 'FM', '11101', 'false'];

        return $this->arrayToCsv($csv);
    }

    /**
     * Update prices in bulk from CSV data
     *
     * @param array $data CSV data as array
     * @param int $tenantId
     * @return array
     */
    public function bulkUpdatePrices(array $data, int $tenantId): array
    {
        $updated = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($data as $index => $row) {
                $rowNumber = $index + 2;

                // Validate row data
                $validator = Validator::make($row, [
                    'sku' => 'required|string',
                    'price' => 'nullable|numeric|min:0',
                    'cost' => 'nullable|numeric|min:0',
                    'margin_percentage' => 'nullable|numeric|min:0|max:100',
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => $validator->errors()->toArray()
                    ];
                    continue;
                }

                // Find product by SKU or barcode
                $product = Product::where('tenant_id', $tenantId)
                    ->where(function($query) use ($row) {
                        $query->where('sku', $row['sku'])
                              ->orWhere('barcode', $row['sku']);
                    })
                    ->first();

                if (!$product) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => ['sku' => ["Producto no encontrado con SKU/Barcode: {$row['sku']}"]]
                    ];
                    continue;
                }

                // Update prices
                $hasChanges = false;

                if (!empty($row['price'])) {
                    $product->price = $row['price'];
                    $hasChanges = true;
                }

                if (!empty($row['cost'])) {
                    $product->cost = $row['cost'];
                    $hasChanges = true;
                }

                // Calculate price from margin if provided
                if (!empty($row['margin_percentage']) && !empty($product->cost)) {
                    $margin = floatval($row['margin_percentage']);
                    $cost = floatval($product->cost);
                    $product->price = $cost * (1 + ($margin / 100));
                    $hasChanges = true;
                }

                if ($hasChanges) {
                    $product->save();
                    $updated++;
                }
            }

            DB::commit();

            return [
                'success' => true,
                'updated' => $updated,
                'errors' => $errors,
                'total_rows' => count($data)
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Error during price update: ' . $e->getMessage(),
                'updated' => $updated,
                'errors' => $errors
            ];
        }
    }

    /**
     * Get CSV template for inventory
     *
     * @return string
     */
    public function getInventoryTemplate(): string
    {
        $csv = [];
        $csv[] = ['sku', 'quantity', 'location'];
        $csv[] = ['SKU001', '100', 'A-01-01'];
        $csv[] = ['1234567890', '50', 'A-01-02'];

        return $this->arrayToCsv($csv);
    }

    /**
     * Get CSV template for price update
     *
     * @return string
     */
    public function getPriceUpdateTemplate(): string
    {
        $csv = [];
        $csv[] = ['sku', 'price', 'cost', 'margin_percentage'];
        $csv[] = ['SKU001', '150.00', '100.00', ''];
        $csv[] = ['SKU002', '', '80.00', '50'];

        return $this->arrayToCsv($csv);
    }

    /**
     * Get Excel template for products
     *
     * @return string Path to temporary file
     */
    public function getProductTemplateExcel(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products Template');

        // Header row
        $headers = ['name', 'sku', 'barcode', 'category_id', 'brand_id', 'price', 'cost', 'stock_min', 'stock_max', 'tax_rate', 'tax_type', 'is_active'];
        $sheet->fromArray($headers, null, 'A1');

        // Example row
        $example = ['Example Product', 'SKU001', '1234567890', '1', '1', '100.00', '50.00', '10', '100', '15', 'inclusive', 'true'];
        $sheet->fromArray($example, null, 'A2');

        // Style header row
        $headerStyle = $sheet->getStyle('A1:L1');
        $headerStyle->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4CAF50');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Auto-size columns
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add instructions in a separate sheet
        $instructionSheet = $spreadsheet->createSheet();
        $instructionSheet->setTitle('Instructions');
        $instructionSheet->setCellValue('A1', 'INSTRUCCIONES PARA IMPORTAR PRODUCTOS');
        $instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $instructions = [
            ['Campo', 'Descripción', 'Requerido', 'Ejemplo'],
            ['name', 'Nombre del producto', 'Sí', 'Laptop Dell Inspiron'],
            ['sku', 'Código único del producto', 'No', 'DELL-INS-001'],
            ['barcode', 'Código de barras', 'No', '1234567890123'],
            ['category_id', 'ID de la categoría', 'No', '1'],
            ['brand_id', 'ID de la marca', 'No', '1'],
            ['price', 'Precio de venta', 'Sí', '150.00'],
            ['cost', 'Costo del producto', 'No', '100.00'],
            ['stock_min', 'Stock mínimo', 'No', '5'],
            ['stock_max', 'Stock máximo', 'No', '100'],
            ['tax_rate', 'Tasa de impuesto (%)', 'No', '15'],
            ['tax_type', 'Tipo de impuesto', 'No', 'inclusive'],
            ['is_active', 'Estado (true/false)', 'No', 'true'],
        ];

        $instructionSheet->fromArray($instructions, null, 'A3');
        $instructionSheet->getStyle('A3:D3')->getFont()->setBold(true);

        foreach (range('A', 'D') as $col) {
            $instructionSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Set active sheet back to products
        $spreadsheet->setActiveSheetIndex(0);

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'product_template_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * Get Excel template for customers
     *
     * @return string Path to temporary file
     */
    public function getCustomerTemplateExcel(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Customers Template');

        // Header row
        $headers = ['name', 'email', 'phone', 'document_type', 'document_number', 'address', 'city', 'state', 'postal_code', 'tax_exempt'];
        $sheet->fromArray($headers, null, 'A1');

        // Example row
        $example = ['John Doe', 'john@example.com', '+504 1234-5678', 'ID', '0801199012345', '123 Main St', 'Tegucigalpa', 'FM', '11101', 'false'];
        $sheet->fromArray($example, null, 'A2');

        // Style header row
        $headerStyle = $sheet->getStyle('A1:J1');
        $headerStyle->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2196F3');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Auto-size columns
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add instructions in a separate sheet
        $instructionSheet = $spreadsheet->createSheet();
        $instructionSheet->setTitle('Instructions');
        $instructionSheet->setCellValue('A1', 'INSTRUCCIONES PARA IMPORTAR CLIENTES');
        $instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $instructions = [
            ['Campo', 'Descripción', 'Requerido', 'Ejemplo'],
            ['name', 'Nombre del cliente', 'Sí', 'John Doe'],
            ['email', 'Correo electrónico', 'No', 'john@example.com'],
            ['phone', 'Teléfono', 'No', '+504 1234-5678'],
            ['document_type', 'Tipo de documento (ID/PASSPORT/RUC/RTN)', 'No', 'ID'],
            ['document_number', 'Número de documento', 'No', '0801199012345'],
            ['address', 'Dirección', 'No', '123 Main St'],
            ['city', 'Ciudad', 'No', 'Tegucigalpa'],
            ['state', 'Departamento', 'No', 'FM'],
            ['postal_code', 'Código postal', 'No', '11101'],
            ['tax_exempt', 'Exento de impuestos (true/false)', 'No', 'false'],
        ];

        $instructionSheet->fromArray($instructions, null, 'A3');
        $instructionSheet->getStyle('A3:D3')->getFont()->setBold(true);

        foreach (range('A', 'D') as $col) {
            $instructionSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Set active sheet back to customers
        $spreadsheet->setActiveSheetIndex(0);

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'customer_template_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * Get Excel template for inventory
     *
     * @return string Path to temporary file
     */
    public function getInventoryTemplateExcel(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventory Template');

        // Header row
        $headers = ['sku', 'quantity', 'location'];
        $sheet->fromArray($headers, null, 'A1');

        // Example rows
        $sheet->fromArray(['SKU001', '100', 'A-01-01'], null, 'A2');
        $sheet->fromArray(['1234567890', '50', 'A-01-02'], null, 'A3');

        // Style header row
        $headerStyle = $sheet->getStyle('A1:C1');
        $headerStyle->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF9800');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Auto-size columns
        foreach (range('A', 'C') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add instructions in a separate sheet
        $instructionSheet = $spreadsheet->createSheet();
        $instructionSheet->setTitle('Instructions');
        $instructionSheet->setCellValue('A1', 'INSTRUCCIONES PARA IMPORTAR INVENTARIO');
        $instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $instructions = [
            ['Campo', 'Descripción', 'Requerido', 'Ejemplo'],
            ['sku', 'SKU o Código de barras del producto', 'Sí', 'SKU001 o 1234567890'],
            ['quantity', 'Cantidad en stock', 'Sí', '100'],
            ['location', 'Ubicación física (estante, pasillo, etc)', 'No', 'A-01-01'],
        ];

        $instructionSheet->fromArray($instructions, null, 'A3');
        $instructionSheet->getStyle('A3:D3')->getFont()->setBold(true);

        foreach (range('A', 'D') as $col) {
            $instructionSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add notes
        $instructionSheet->setCellValue('A8', 'NOTAS IMPORTANTES:');
        $instructionSheet->getStyle('A8')->getFont()->setBold(true);
        $instructionSheet->setCellValue('A9', '• El SKU o código de barras debe existir en el sistema');
        $instructionSheet->setCellValue('A10', '• La cantidad debe ser un número entero mayor o igual a cero');
        $instructionSheet->setCellValue('A11', '• Si el producto ya tiene stock, se actualizará la cantidad');
        $instructionSheet->setCellValue('A12', '• Se creará un movimiento de inventario automáticamente');

        // Set active sheet back to inventory
        $spreadsheet->setActiveSheetIndex(0);

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'inventory_template_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * Get Excel template for price update
     *
     * @return string Path to temporary file
     */
    public function getPriceUpdateTemplateExcel(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Price Update Template');

        // Header row
        $headers = ['sku', 'price', 'cost', 'margin_percentage'];
        $sheet->fromArray($headers, null, 'A1');

        // Example rows
        $sheet->fromArray(['SKU001', '150.00', '100.00', ''], null, 'A2');
        $sheet->fromArray(['SKU002', '', '80.00', '50'], null, 'A3');
        $sheet->fromArray(['1234567890', '200.00', '', ''], null, 'A4');

        // Style header row
        $headerStyle = $sheet->getStyle('A1:D1');
        $headerStyle->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF9C27B0');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Auto-size columns
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add instructions in a separate sheet
        $instructionSheet = $spreadsheet->createSheet();
        $instructionSheet->setTitle('Instructions');
        $instructionSheet->setCellValue('A1', 'INSTRUCCIONES PARA ACTUALIZACIÓN DE PRECIOS');
        $instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $instructions = [
            ['Campo', 'Descripción', 'Requerido', 'Ejemplo'],
            ['sku', 'SKU o Código de barras del producto', 'Sí', 'SKU001 o 1234567890'],
            ['price', 'Nuevo precio de venta', 'No*', '150.00'],
            ['cost', 'Nuevo costo del producto', 'No*', '100.00'],
            ['margin_percentage', 'Porcentaje de margen sobre el costo', 'No*', '50'],
        ];

        $instructionSheet->fromArray($instructions, null, 'A3');
        $instructionSheet->getStyle('A3:D3')->getFont()->setBold(true);

        foreach (range('A', 'D') as $col) {
            $instructionSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add notes
        $instructionSheet->setCellValue('A9', 'NOTAS IMPORTANTES:');
        $instructionSheet->getStyle('A9')->getFont()->setBold(true);
        $instructionSheet->setCellValue('A10', '• El SKU o código de barras debe existir en el sistema');
        $instructionSheet->setCellValue('A11', '• Puedes actualizar solo precio, solo costo, o ambos');
        $instructionSheet->setCellValue('A12', '• Si usas margin_percentage, el precio se calculará automáticamente:');
        $instructionSheet->setCellValue('A13', '  Precio = Costo × (1 + Margen/100)');
        $instructionSheet->setCellValue('A14', '• Ejemplo: Costo=100, Margen=50% → Precio=150');
        $instructionSheet->setCellValue('A15', '• Deja vacío el campo que no quieras actualizar');

        // Add example
        $instructionSheet->setCellValue('A17', 'EJEMPLOS:');
        $instructionSheet->getStyle('A17')->getFont()->setBold(true);
        $instructionSheet->setCellValue('A18', '1. SKU001, 150.00, 100.00, "" → Actualiza precio y costo');
        $instructionSheet->setCellValue('A19', '2. SKU002, "", 80.00, 50 → Actualiza costo y calcula precio con 50% margen');
        $instructionSheet->setCellValue('A20', '3. SKU003, 200.00, "", "" → Solo actualiza el precio');

        // Set active sheet back to price update
        $spreadsheet->setActiveSheetIndex(0);

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'price_update_template_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * Export products to Excel format
     *
     * @param int $tenantId
     * @param array $filters
     * @return string Path to temporary file
     */
    public function exportProductsExcel(int $tenantId, array $filters = []): string
    {
        $query = Product::where('tenant_id', $tenantId)
            ->with(['category', 'brand']);

        // Apply filters
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        $products = $query->get();

        // Create new Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products');

        // Header row
        $headers = ['ID', 'Name', 'SKU', 'Barcode', 'Category', 'Brand', 'Price', 'Cost', 'Stock', 'Stock Min', 'Stock Max', 'Tax Rate', 'Tax Type', 'Active'];
        $sheet->fromArray($headers, null, 'A1');

        // Style header row
        $headerStyle = $sheet->getStyle('A1:N1');
        $headerStyle->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4CAF50');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Data rows
        $row = 2;
        foreach ($products as $product) {
            $sheet->fromArray([
                $product->id,
                $product->name,
                $product->sku ?? '',
                $product->barcode ?? '',
                $product->category->name ?? '',
                $product->brand->name ?? '',
                $product->price,
                $product->cost ?? '',
                $product->stock ?? 0,
                $product->stock_min ?? '',
                $product->stock_max ?? '',
                $product->tax_rate ?? '',
                $product->tax_type ?? 'taxed',
                $product->is_active ? 'Yes' : 'No'
            ], null, 'A' . $row);
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'products_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * Export customers to Excel format
     *
     * @param int $tenantId
     * @return string Path to temporary file
     */
    public function exportCustomersExcel(int $tenantId): string
    {
        $customers = Customer::where('tenant_id', $tenantId)->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Customers');

        // Header row
        $headers = ['ID', 'Name', 'Email', 'Phone', 'Document Type', 'Document Number', 'Address', 'City', 'State', 'Postal Code', 'Tax Exempt'];
        $sheet->fromArray($headers, null, 'A1');

        // Style header row
        $headerStyle = $sheet->getStyle('A1:K1');
        $headerStyle->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2196F3');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Data rows
        $row = 2;
        foreach ($customers as $customer) {
            $sheet->fromArray([
                $customer->id,
                $customer->name,
                $customer->email ?? '',
                $customer->phone ?? '',
                $customer->document_type ?? '',
                $customer->document_number ?? '',
                $customer->address ?? '',
                $customer->city ?? '',
                $customer->state ?? '',
                $customer->postal_code ?? '',
                $customer->tax_exempt ? 'Yes' : 'No'
            ], null, 'A' . $row);
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'customers_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * Export sales report to Excel
     *
     * @param int $tenantId
     * @param array $filters
     * @return string Path to temporary file
     */
    public function exportSalesExcel(int $tenantId, array $filters = []): string
    {
        $query = Sale::where('tenant_id', $tenantId)
            ->with(['customer', 'user', 'branch']);

        // Apply date filters
        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $sales = $query->orderBy('created_at', 'desc')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sales Report');

        // Header row
        $headers = ['Sale Number', 'Date', 'Customer', 'Branch', 'User', 'Subtotal', 'Discount', 'Tax', 'Total', 'Payment Method', 'Status'];
        $sheet->fromArray($headers, null, 'A1');

        // Style header row
        $headerStyle = $sheet->getStyle('A1:K1');
        $headerStyle->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF9800');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Data rows
        $row = 2;
        foreach ($sales as $sale) {
            $sheet->fromArray([
                $sale->sale_number,
                $sale->created_at->format('Y-m-d H:i:s'),
                $sale->customer->name ?? $sale->customer_name ?? 'General',
                $sale->branch->name ?? '',
                $sale->user->name ?? '',
                $sale->subtotal,
                $sale->discount,
                $sale->tax,
                $sale->total,
                $sale->payment_method,
                $sale->status
            ], null, 'A' . $row);
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Format currency columns
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('F2:I' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'sales_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }
}
