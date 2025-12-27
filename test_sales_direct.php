<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get all sales without filters
echo "=== All Sales ===\n";
$allSales = \App\Models\Sale\Sale::all();
echo "Total sales in database: " . $allSales->count() . "\n\n";

foreach ($allSales as $sale) {
    echo "ID: {$sale->id}\n";
    echo "Number: {$sale->sale_number}\n";
    echo "Tenant ID: {$sale->tenant_id}\n";
    echo "Total: {$sale->total}\n";
    echo "Status: {$sale->status}\n";
    echo "Sold At: {$sale->sold_at}\n";
    echo "---\n";
}

// Try with tenant filter
echo "\n=== With Tenant Filter (tenant_id = 1) ===\n";
$tenantSales = \App\Models\Sale\Sale::where('tenant_id', 1)->get();
echo "Sales for tenant 1: " . $tenantSales->count() . "\n\n";

// Try loading with relationships
echo "\n=== Try Loading with Relationships ===\n";
try {
    $salesWithRelations = \App\Models\Sale\Sale::with(['details.product', 'user', 'customer'])
        ->where('tenant_id', 1)
        ->get();
    echo "Success! Sales with relations: " . $salesWithRelations->count() . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
