<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

// Create a mock request
$today = date('Y-m-d');
$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));

$request = \Illuminate\Http\Request::create('/api/sales', 'GET', [
    'date_from' => $thirtyDaysAgo,
    'date_to' => $today,
    'per_page' => 20,
    'sort_by' => 'sold_at',
    'sort_order' => 'desc'
]);

// Set authenticated user
$user = \App\Models\User\User::first();
\Illuminate\Support\Facades\Auth::setUser($user);
$request->setUserResolver(function() use ($user) {
    return $user;
});

echo "=== API Request Simulation ===\n";
echo "User: {$user->name} (tenant_id: {$user->tenant_id})\n";
echo "Filters: date_from={$thirtyDaysAgo}, date_to={$today}\n\n";

// Call the controller
try {
    $saleService = app(\App\Services\SaleService::class);
    $controller = new \App\Http\Controllers\API\Sales\SaleController($saleService);

    $response = $controller->index($request);
    $content = json_decode($response->getContent(), true);

    echo "Response Status: " . $response->getStatusCode() . "\n";
    echo "Success: " . ($content['success'] ? 'true' : 'false') . "\n";
    echo "Total sales: " . ($content['meta']['total'] ?? 0) . "\n";
    echo "Sales count: " . count($content['data'] ?? []) . "\n\n";

    if (!empty($content['data'])) {
        echo "First sale:\n";
        $firstSale = $content['data'][0];
        echo json_encode($firstSale, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "No sales in response!\n";
        echo "Full response:\n";
        echo json_encode($content, JSON_PRETTY_PRINT) . "\n";
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}
