<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Simulate today and 30 days ago
$today = date('Y-m-d');
$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));

echo "=== Testing with date filters ===\n";
echo "Date from: {$thirtyDaysAgo}\n";
echo "Date to: {$today}\n\n";

// Simulate the controller query
$query = \App\Models\Sale\Sale::with(['details.product', 'user', 'customer'])
    ->where('tenant_id', 1);

echo "Before date filter: " . $query->count() . " sales\n\n";

// Apply date filter as in controller
$query->whereBetween('sold_at', [
    $thirtyDaysAgo . ' 00:00:00',
    $today . ' 23:59:59'
]);

echo "After date filter: " . $query->count() . " sales\n\n";

// Get the sales
try {
    $sales = $query->get();
    echo "Success! Got " . $sales->count() . " sales\n\n";

    foreach ($sales as $sale) {
        echo "Sale #{$sale->sale_number}\n";
        echo "Sold at: {$sale->sold_at}\n";
        echo "Total: L {$sale->total}\n";
        echo "Customer: {$sale->customer_name}\n";
        echo "Details count: " . $sale->details->count() . "\n";
        echo "---\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
