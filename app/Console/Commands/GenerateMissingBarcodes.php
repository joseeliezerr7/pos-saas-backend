<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\BarcodeService;
use Illuminate\Console\Command;

class GenerateMissingBarcodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'barcodes:generate-missing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate barcodes for products that don\'t have one';

    protected $barcodeService;

    public function __construct(BarcodeService $barcodeService)
    {
        parent::__construct();
        $this->barcodeService = $barcodeService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating missing barcodes...');

        // Get all products without barcodes
        $products = Product::whereNull('barcode')
            ->orWhere('barcode', '')
            ->get();

        if ($products->isEmpty()) {
            $this->info('No products without barcodes found.');
            return 0;
        }

        $this->info('Found ' . $products->count() . ' products without barcodes.');

        $bar = $this->output->createProgressBar($products->count());
        $bar->start();

        $updated = 0;
        foreach ($products as $product) {
            try {
                // Generate unique barcode for this product
                $barcode = $this->barcodeService->generateUniqueEAN13($product->tenant_id);
                $product->barcode = $barcode;
                $product->save();
                $updated++;
            } catch (\Exception $e) {
                $this->error("\nError generating barcode for product ID {$product->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully generated {$updated} barcodes!");

        return 0;
    }
}
