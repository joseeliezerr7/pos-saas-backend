<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$program = App\Models\Loyalty\LoyaltyProgram::find(2);
echo "Programa ID 2 - Total de tiers: " . $program->tiers()->count() . "\n\n";

$tiers = $program->tiers()->orderBy('order')->get();
foreach ($tiers as $tier) {
    echo "ID={$tier->id} | {$tier->name} | order={$tier->order} | min={$tier->min_points}\n";
}
