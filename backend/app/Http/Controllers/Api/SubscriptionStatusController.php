<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use App\Models\System\TenantModule;
use App\Models\System\Module;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use FedaPay\FedaPay;
use FedaPay\Transaction;

class SubscriptionStatusController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Statut de l'abonnement du tenant courant
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'No tenant'], 400);
        }

        $tenantId = $user->tenant_id;
        $subscription = $this->subscriptionService->getActiveSubscription($tenantId);
        $limits = $this->subscriptionService->getPlanLimits($tenantId);
        $daysRemaining = $this->subscriptionService->getDaysRemaining($tenantId);

        // Usage actuel
        $tenant = $user->tenant;
        $usage = [
            'users' => $this->subscriptionService->isLimitReached($tenantId, 'users'),
            'products' => $this->subscriptionService->isLimitReached($tenantId, 'products'),
            'warehouses' => $this->subscriptionService->isLimitReached($tenantId, 'warehouses'),
            'storage' => $this->subscriptionService->isLimitReached($tenantId, 'storage'),
        ];

        return response()->json([
            'data' => [
                'has_subscription' => $subscription !== null,
                'status' => $subscription?->status ?? 'none',
                'plan' => $subscription?->plan ? [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'display_name' => $subscription->plan->display_name,
                ] : null,
                'starts_at' => $subscription?->starts_at?->toISOString(),
                'ends_at' => $subscription?->ends_at?->toISOString(),
                'trial_ends_at' => $subscription?->trial_ends_at?->toISOString(),
                'days_remaining' => $daysRemaining,
                'is_trial' => $subscription?->status === 'trial',
                'is_expiring_soon' => $daysRemaining <= 7 && $daysRemaining > 0,
                'limits' => $limits,
                'usage' => $usage,
            ],
        ]);
    }

    /**
     * Modules accessibles pour le tenant
     */
    public function modules(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'No tenant'], 400);
        }

        $tenantId = $user->tenant_id;

        // Tous les modules
        $allModules = Module::where('is_active', true)->orderBy('sort_order')->get();
        
        // Modules activés pour ce tenant
        $enabledModuleIds = TenantModule::where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->pluck('module_id')
            ->toArray();

        $modules = $allModules->map(function ($module) use ($enabledModuleIds) {
            $isEnabled = $module->is_core || in_array($module->id, $enabledModuleIds);
            return [
                'code' => $module->code,
                'name' => $module->name,
                'description' => $module->description,
                'icon' => $module->icon,
                'is_core' => $module->is_core,
                'is_enabled' => $isEnabled,
                'requires_upgrade' => !$isEnabled && !$module->is_core,
            ];
        });

        return response()->json(['data' => $modules]);
    }

    /**
     * Vérifier l'accès à un module spécifique
     */
    public function checkModule(Request $request, string $moduleCode): JsonResponse
    {
        $user = $request->user();
        
        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'No tenant'], 400);
        }

        $hasAccess = $this->subscriptionService->hasModuleAccess($user->tenant_id, $moduleCode);

        return response()->json([
            'data' => [
                'module' => $moduleCode,
                'has_access' => $hasAccess,
            ],
        ]);
    }

    /**
     * Vérifier une limite spécifique
     */
    public function checkLimit(Request $request, string $limitType): JsonResponse
    {
        $user = $request->user();
        
        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'No tenant'], 400);
        }

        $result = $this->subscriptionService->isLimitReached($user->tenant_id, $limitType);

        return response()->json(['data' => $result]);
    }

    /**
     * Liste des plans disponibles pour souscription
     */
    public function plans(Request $request): JsonResponse
    {
        $plans = \App\Models\System\SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price_monthly')
            ->get();

        return response()->json(['data' => $plans]);
    }

    /**
     * Souscrire à un plan
     */
    public function subscribe(Request $request): JsonResponse
    {
        $user = $request->user();
         
        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'No tenant'], 400);
        }

        $validated = $request->validate([
            'transaction_id' => 'required|string',
            'plan_id' => 'required|exists:system_subscription_plans,id',
            'payment_method' => 'required|string',
            'duration_months' => 'nullable|integer|min:1|max:12',
        ]);

        

        $tenant = $user->tenant;
        $plan = \App\Models\System\SubscriptionPlan::findOrFail($validated['plan_id']);
        $durationMonths = $validated['duration_months'] ?? 1;

        \DB::beginTransaction();
        try {
            // Initialize FedaPay with your API key
            FedaPay::setApiKey(config('payment.feda_secret_key'));
            FedaPay::setEnvironment(config('payment.fedapay_environment', 'sandbox'));

            // Retrieve transaction from FedaPay
            $fedapayTransaction = Transaction::retrieve($validated['transaction_id']);
            $status = strtolower($fedapayTransaction->status);

            
            if ($status === 'approved') {
                
                
            }else{
                 $message = match($status) {
                'pending' => 'Votre paiement est en cours de traitement',
                'failed' => 'Le paiement a échoué. Veuillez réessayer',
                'canceled' => 'Le paiement a été annulé',
                default => 'Le paiement est en attente ou a échoué'
            };

            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $message,
            ], 500);

            }

           
            // Annuler les anciens abonnements
            \App\Models\System\Subscription::where('tenant_id', $tenant->id)
                ->whereIn('status', ['active', 'trial', 'expired'])
                ->update(['status' => 'cancelled']);

            // Déterminer si c'est un essai gratuit ou un abonnement payant
            $isTrialEligible = $plan->trial_days > 0 && 
                !\App\Models\System\Subscription::where('tenant_id', $tenant->id)->exists();

            $startsAt = now();
            $endsAt = $isTrialEligible 
                ? now()->addDays($plan->trial_days) 
                : now()->addMonths($durationMonths);

            // Créer l'abonnement
            $subscription = \App\Models\System\Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => $isTrialEligible ? 'trial' : 'active',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'trial_ends_at' => $isTrialEligible ? $endsAt : null,
                'payment_method' => $validated['payment_method'],
            ]);

            // Mettre à jour le tenant
            $tenant->update([
                'plan_id' => $plan->id,
                'status' => 'active',
                'subscription_expires_at' => $endsAt,
            ]);

            // Synchroniser les modules
            $this->subscriptionService->syncTenantModules($tenant->id, $plan->id);

            // Log
            \App\Models\System\SystemLog::log(
                "Abonnement souscrit: {$tenant->name} -> {$plan->display_name}",
                'info',
                'subscription',
                $isTrialEligible ? "Essai gratuit de {$plan->trial_days} jours" : "Abonnement {$durationMonths} mois",
                ['tenant_id' => $tenant->id, 'plan_id' => $plan->id]
            );

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => $isTrialEligible 
                    ? "Essai gratuit de {$plan->trial_days} jours activé !" 
                    : "Abonnement activé avec succès !",
                'data' => [
                    'subscription' => $subscription,
                    'is_trial' => $isTrialEligible,
                    'expires_at' => $endsAt->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }
}
