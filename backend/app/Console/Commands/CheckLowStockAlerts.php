<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\StockAlertService;
use Illuminate\Console\Command;

class CheckLowStockAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:check-alerts {--tenant_id= : Check alerts for specific tenant}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all products and create low stock alerts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $alertService = new StockAlertService();
        
        if ($this->option('tenant_id')) {
            $tenantId = $this->option('tenant_id');
            $alertsCreated = $alertService->checkAndCreateAlerts($tenantId);
            $this->info("Alerts checked for tenant {$tenantId}: {$alertsCreated} new alerts created");
        } else {
            $tenants = Tenant::active()->get();
            $totalAlertsCreated = 0;

            foreach ($tenants as $tenant) {
                $alertsCreated = $alertService->checkAndCreateAlerts($tenant->id);
                $totalAlertsCreated += $alertsCreated;
                $this->info("Tenant {$tenant->name}: {$alertsCreated} alerts created");
            }

            $this->info("\nTotal alerts created: {$totalAlertsCreated}");
        }

        return self::SUCCESS;
    }
}
