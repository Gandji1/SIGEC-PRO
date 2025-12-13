<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\VirtualRoleAutomationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunVirtualRoleAutomations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tenantId;

    public function __construct($tenantId = null)
    {
        $this->tenantId = $tenantId;
    }

    public function handle(): void
    {
        $service = new VirtualRoleAutomationService();

        // Si un tenant spécifique est fourni
        if ($this->tenantId) {
            $this->runForTenant($service, $this->tenantId);
            return;
        }

        // Sinon, exécuter pour tous les tenants actifs
        $tenants = Tenant::where('status', 'active')->get();

        foreach ($tenants as $tenant) {
            try {
                $this->runForTenant($service, $tenant->id);
            } catch (\Exception $e) {
                Log::error("Automation failed for tenant {$tenant->id}: " . $e->getMessage());
            }
        }
    }

    protected function runForTenant(VirtualRoleAutomationService $service, $tenantId): void
    {
        $service->setTenantId($tenantId);
        $results = $service->runAllAutomations();

        Log::info("Automations completed for tenant {$tenantId}", [
            'urgent_approved' => count($results['urgent_requests_approved'] ?? []),
            'transfers_created' => count($results['transfers_created'] ?? []),
            'low_stock_alerts' => count($results['low_stock_alerts'] ?? []),
            'sales_entries' => count($results['sales_entries_generated'] ?? []),
            'purchase_entries' => count($results['purchase_entries_generated'] ?? []),
            'sessions_closed' => count($results['stale_sessions_closed'] ?? []),
        ]);
    }
}
