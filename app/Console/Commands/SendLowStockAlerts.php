<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Product;
use App\Mail\LowStockAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendLowStockAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:send-low-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email alerts for products with low stock';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for low stock products...');

        // Get all active companies
        $companies = Company::where('is_active', true)->get();

        $totalAlertsSent = 0;

        foreach ($companies as $company) {
            // Check if low stock alerts are enabled
            $notificationSettings = $company->notification_settings ?? [];

            if (!isset($notificationSettings['send_low_stock_alerts']) || !$notificationSettings['send_low_stock_alerts']) {
                continue;
            }

            // Get products with low stock for this company
            $lowStockProducts = Product::where('tenant_id', $company->id)
                ->whereRaw('stock <= COALESCE(min_stock, 10)')
                ->where('stock', '>=', 0)
                ->get();

            if ($lowStockProducts->isEmpty()) {
                continue;
            }

            // Get email to send alert to
            $emailTo = $notificationSettings['low_stock_alert_email'] ?? $company->email;

            if (empty($emailTo)) {
                $this->warn("No email configured for low stock alerts for company: {$company->name}");
                continue;
            }

            // Send email
            try {
                Mail::to($emailTo)->send(new LowStockAlert($lowStockProducts, $company));
                $totalAlertsSent++;
                $this->info("Low stock alert sent to {$emailTo} for {$company->name} ({$lowStockProducts->count()} products)");
            } catch (\Exception $e) {
                $this->error("Failed to send alert to {$emailTo}: " . $e->getMessage());
            }
        }

        $this->info("Process completed. Total alerts sent: {$totalAlertsSent}");

        return 0;
    }
}
