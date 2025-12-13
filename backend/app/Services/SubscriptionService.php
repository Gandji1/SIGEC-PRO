<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\System\Subscription;
use App\Models\System\SubscriptionPlan;
use App\Models\System\TenantModule;
use App\Models\System\Module;
use App\Models\System\SystemLog;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Vérifier et mettre à jour les abonnements expirés
     * À exécuter via cron job quotidien
     */
    public function checkExpiredSubscriptions(): array
    {
        $expired = [];
        $suspended = [];

        // Trouver les abonnements expirés
        $expiredSubscriptions = Subscription::whereIn('status', ['active', 'trial'])
            ->where(function ($q) {
                $q->where('ends_at', '<', now())
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'trial')
                         ->where('trial_ends_at', '<', now());
                  });
            })
            ->with('tenant')
            ->get();

        foreach ($expiredSubscriptions as $subscription) {
            $subscription->update(['status' => 'expired']);
            $expired[] = $subscription->tenant->name ?? "Tenant #{$subscription->tenant_id}";

            SystemLog::log(
                "Abonnement expiré: {$subscription->tenant->name}",
                'warning',
                'subscription',
                null,
                ['tenant_id' => $subscription->tenant_id, 'subscription_id' => $subscription->id]
            );
        }

        // Suspendre les tenants avec abonnement expiré depuis plus de 7 jours (grâce)
        $toSuspend = Subscription::where('status', 'expired')
            ->where('ends_at', '<', now()->subDays(7))
            ->whereHas('tenant', fn($q) => $q->where('status', 'active'))
            ->with('tenant')
            ->get();

        foreach ($toSuspend as $subscription) {
            $subscription->tenant->update(['status' => 'suspended']);
            $suspended[] = $subscription->tenant->name;

            SystemLog::log(
                "Tenant suspendu (abonnement expiré): {$subscription->tenant->name}",
                'warning',
                'subscription',
                "Suspension automatique après 7 jours de grâce",
                ['tenant_id' => $subscription->tenant_id]
            );
        }

        return [
            'expired' => $expired,
            'suspended' => $suspended,
            'expired_count' => count($expired),
            'suspended_count' => count($suspended),
        ];
    }

    /**
     * Vérifier si un tenant a un abonnement valide
     */
    public function hasValidSubscription(int $tenantId): bool
    {
        return Subscription::where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'trial'])
            ->where(function ($q) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>', now());
            })
            ->exists();
    }

    /**
     * Récupérer l'abonnement actif d'un tenant
     */
    public function getActiveSubscription(int $tenantId): ?Subscription
    {
        return Subscription::with('plan')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'trial'])
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Récupérer les limites du plan d'un tenant
     */
    public function getPlanLimits(int $tenantId): array
    {
        $subscription = $this->getActiveSubscription($tenantId);
        
        if (!$subscription || !$subscription->plan) {
            // Limites par défaut (plan gratuit)
            return [
                'max_users' => 2,
                'max_products' => 50,
                'max_warehouses' => 1,
                'max_pos' => 1,
                'storage_limit_mb' => 100,
            ];
        }

        return [
            'max_users' => $subscription->plan->max_users,
            'max_products' => $subscription->plan->max_products,
            'max_warehouses' => $subscription->plan->max_warehouses,
            'max_pos' => $subscription->plan->max_pos,
            'storage_limit_mb' => $subscription->plan->storage_limit_mb,
        ];
    }

    /**
     * Vérifier si une limite est atteinte
     */
    public function isLimitReached(int $tenantId, string $limitType): array
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return ['reached' => false, 'current' => 0, 'limit' => 0];
        }

        $limits = $this->getPlanLimits($tenantId);
        $current = 0;
        $limit = 0;

        switch ($limitType) {
            case 'users':
                $current = $tenant->users()->count();
                $limit = $limits['max_users'];
                break;
            case 'products':
                $current = $tenant->products()->count();
                $limit = $limits['max_products'];
                break;
            case 'warehouses':
                $current = $tenant->warehouses()->count();
                $limit = $limits['max_warehouses'];
                break;
            case 'storage':
                $current = $tenant->storage_used_mb ?? 0;
                $limit = $limits['storage_limit_mb'];
                break;
        }

        return [
            'reached' => $current >= $limit,
            'current' => $current,
            'limit' => $limit,
            'remaining' => max(0, $limit - $current),
        ];
    }

    /**
     * Synchroniser les modules d'un tenant selon son plan
     */
    public function syncTenantModules(int $tenantId, int $planId = null): void
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) return;

        // Récupérer le plan
        $plan = $planId ? SubscriptionPlan::find($planId) : null;
        if (!$plan) {
            $subscription = $this->getActiveSubscription($tenantId);
            $plan = $subscription?->plan;
        }

        // Activer les modules core pour tous
        $coreModules = Module::where('is_core', true)->get();
        foreach ($coreModules as $module) {
            TenantModule::updateOrCreate(
                ['tenant_id' => $tenantId, 'module_id' => $module->id],
                ['is_enabled' => true, 'enabled_at' => now()]
            );
        }

        // Si plan avec features, activer les modules correspondants
        if ($plan && $plan->features) {
            $features = is_array($plan->features) ? $plan->features : json_decode($plan->features, true);
            
            if (isset($features['modules']) && is_array($features['modules'])) {
                foreach ($features['modules'] as $moduleCode) {
                    $module = Module::where('code', $moduleCode)->first();
                    if ($module) {
                        TenantModule::updateOrCreate(
                            ['tenant_id' => $tenantId, 'module_id' => $module->id],
                            ['is_enabled' => true, 'enabled_at' => now()]
                        );
                    }
                }
            }
        }
    }

    /**
     * Vérifier si un tenant a accès à un module
     */
    public function hasModuleAccess(int $tenantId, string $moduleCode): bool
    {
        $module = Module::where('code', $moduleCode)->first();
        
        if (!$module) return true; // Module inconnu = accès autorisé
        if ($module->is_core) return true; // Module core = toujours accessible

        return TenantModule::where('tenant_id', $tenantId)
            ->where('module_id', $module->id)
            ->where('is_enabled', true)
            ->exists();
    }

    /**
     * Obtenir les jours restants avant expiration
     */
    public function getDaysRemaining(int $tenantId): int
    {
        $subscription = $this->getActiveSubscription($tenantId);
        
        if (!$subscription || !$subscription->ends_at) {
            return 999; // Pas de limite
        }

        return max(0, now()->diffInDays($subscription->ends_at, false));
    }

    /**
     * Envoyer des alertes pour les abonnements qui expirent bientôt
     */
    public function getExpiringSubscriptions(int $daysThreshold = 7): array
    {
        return Subscription::whereIn('status', ['active', 'trial'])
            ->whereBetween('ends_at', [now(), now()->addDays($daysThreshold)])
            ->with(['tenant:id,name,email', 'plan:id,name,display_name'])
            ->get()
            ->map(fn($s) => [
                'tenant' => $s->tenant->name,
                'email' => $s->tenant->email,
                'plan' => $s->plan->display_name ?? $s->plan->name,
                'expires_at' => $s->ends_at->toDateString(),
                'days_remaining' => now()->diffInDays($s->ends_at),
            ])
            ->toArray();
    }
}
