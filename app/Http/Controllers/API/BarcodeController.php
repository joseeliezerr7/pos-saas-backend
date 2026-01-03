<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\BarcodeService;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class BarcodeController extends Controller
{
    protected $barcodeService;

    public function __construct(BarcodeService $barcodeService)
    {
        $this->barcodeService = $barcodeService;
    }

    /**
     * Generate a unique EAN-13 barcode
     */
    public function generateUnique(Request $request)
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            $barcode = $this->barcodeService->generateUniqueEAN13($tenantId);

            return response()->json([
                'success' => true,
                'barcode' => $barcode
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar código de barras: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate barcode SVG
     */
    public function generateSVG(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'type' => 'nullable|in:ean13,code128'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $code = $request->input('code');
            $type = $request->input('type', 'ean13');

            $svg = $this->barcodeService->generateBarcodeSVG($code, $type);

            return response()->json([
                'success' => true,
                'svg' => $svg
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar código de barras: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate barcode
     */
    public function validate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $barcode = $request->input('barcode');
            $isValid = $this->barcodeService->validateEAN13($barcode);

            return response()->json([
                'success' => true,
                'valid' => $isValid
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al validar código de barras: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate labels for products
     */
    public function generateLabels(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1|max:100',
            'label_size' => 'nullable|in:small,medium,large',
            'show_price' => 'nullable|boolean',
            'show_sku' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tenantId = auth()->user()->tenant_id;
            $productsData = $request->input('products');
            $labelSize = $request->input('label_size', 'medium');
            $showPrice = $request->input('show_price', true);
            $showSku = $request->input('show_sku', true);

            // Get products
            $productIds = array_column($productsData, 'product_id');
            $products = Product::where('tenant_id', $tenantId)
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            // Prepare products with quantities
            $productsWithQuantities = [];
            foreach ($productsData as $item) {
                $product = $products[$item['product_id']] ?? null;
                if ($product) {
                    $productsWithQuantities[] = [
                        'product' => $product->toArray(),
                        'quantity' => $item['quantity']
                    ];
                }
            }

            // Generate labels
            $labels = $this->barcodeService->generateLabels($productsWithQuantities);

            // Return labels data (for preview or PDF generation)
            return response()->json([
                'success' => true,
                'labels' => $labels,
                'options' => [
                    'size' => $labelSize,
                    'show_price' => $showPrice,
                    'show_sku' => $showSku
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar etiquetas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate labels PDF
     */
    public function generateLabelsPDF(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1|max:100',
            'label_size' => 'nullable|in:small,medium,large',
            'show_price' => 'nullable|boolean',
            'show_sku' => 'nullable|boolean',
            'columns' => 'nullable|integer|min:1|max:4'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tenantId = auth()->user()->tenant_id;
            $productsData = $request->input('products');
            $labelSize = $request->input('label_size', 'medium');
            $showPrice = $request->input('show_price', true);
            $showSku = $request->input('show_sku', true);
            $columns = $request->input('columns', 3);

            // Get products
            $productIds = array_column($productsData, 'product_id');
            $products = Product::where('tenant_id', $tenantId)
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            // Prepare products with quantities
            $productsWithQuantities = [];
            foreach ($productsData as $item) {
                $product = $products[$item['product_id']] ?? null;
                if ($product) {
                    $productsWithQuantities[] = [
                        'product' => $product->toArray(),
                        'quantity' => $item['quantity']
                    ];
                }
            }

            // Generate labels
            $labels = $this->barcodeService->generateLabels($productsWithQuantities);

            // Generate PDF
            $pdf = Pdf::loadView('labels.barcode', [
                'labels' => $labels,
                'options' => [
                    'size' => $labelSize,
                    'show_price' => $showPrice,
                    'show_sku' => $showSku,
                    'columns' => $columns
                ]
            ]);

            $pdf->setPaper('letter', 'portrait');

            $filename = 'labels_' . date('Y-m-d_His') . '.pdf';

            return $pdf->download($filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar PDF de etiquetas: ' . $e->getMessage()
            ], 500);
        }
    }
}
