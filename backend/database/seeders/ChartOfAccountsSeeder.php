<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $service = new ChartOfAccountsService();

        // Obtenir le tenant de démo
        $tenant = Tenant::where('name', 'Demo Company')->first();

        if ($tenant && !\App\Models\ChartOfAccounts::where('tenant_id', $tenant->id)->exists()) {
            $service->createChartOfAccounts($tenant, 'retail');
        }
    }
}
