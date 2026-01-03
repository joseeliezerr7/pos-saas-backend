<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Update overdue credit sales status daily
Schedule::call(function () {
    $tenants = \App\Models\Tenant\Company::all();
    $creditService = app(\App\Services\CreditService::class);

    foreach ($tenants as $tenant) {
        $updated = $creditService->updateOverdueStatus($tenant->id);
        \Illuminate\Support\Facades\Log::info("Updated {$updated} overdue credit sales for tenant {$tenant->id}");
    }
})->daily()->name('update-overdue-credit-sales')
  ->description('Update overdue status for all credit sales');
