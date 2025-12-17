<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\System\SubscriptionPlan;
use App\Models\System\Subscription;
use App\Models\System\Payment;
use App\Models\System\SystemLog;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    /**
     * Liste des plans d'abonnement
     */
    public function plans(Request $request): JsonResponse
    {
        $plans = SubscriptionPlan::orderBy('sort_order')
            ->withCount(['subscriptions' => fn($q) => $q->whereIn('status', ['active', 'trial'])])
            ->get();

        return response()->json(['data' => $plans]);
    }

    /**
     * Créer un plan
     */
    public function createPlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:system_subscription_plans,name',
            'display_name' => 'required|string',
            'description' => 'nullable|string',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'max_users' => 'required|integer|min:1',
            'max_pos' => 'required|integer|min:1',
            'max_products' => 'required|integer|min:1',
            'max_warehouses' => 'required|integer|min:1',
            'storage_limit_mb' => 'required|integer|min:100',
            'features' => 'nullable|array',
            'trial_days' => 'nullable|integer|min:0',
        ]);

        $plan = SubscriptionPlan::create($validated);

        SystemLog::log("Plan créé: {$plan->display_name}", 'info', 'system');

        return response()->json([
            'success' => true,
            'data' => $plan,
        ], 201);
    }

    /**
     * Modifier un plan
     */
    public function updatePlan(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => 'nullable|string',
            'description' => 'nullable|string',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'max_users' => 'nullable|integer|min:1',
            'max_pos' => 'nullable|integer|min:1',
            'max_products' => 'nullable|integer|min:1',
            'max_warehouses' => 'nullable|integer|min:1',
            'storage_limit_mb' => 'nullable|integer|min:100',
            'features' => 'nullable|array',
            'trial_days' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $plan->update($validated);

        return response()->json([
            'success' => true,
            'data' => $plan,
        ]);
    }

    /**
     * Supprimer un plan
     */
    public function deletePlan(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $subscriptionsCount = Subscription::where('plan_id', $plan->id)->count();
        if ($subscriptionsCount > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Données invalides',
                'errors' => [
                    'plan' => ["Ce plan ne peut pas être supprimé car il est utilisé par {$subscriptionsCount} abonnement(s)."],
                ],
            ], 422);
        }

        $planName = $plan->display_name;
        $plan->delete();
        SystemLog::log("Plan supprimé: {$planName}", 'info', 'system');

        return response()->json([
            'success' => true,
            'message' => 'Plan supprimé',
        ]);
    }

    /**
     * Liste des abonnements
     */
    public function subscriptions(Request $request): JsonResponse
    {
        $query = Subscription::with(['tenant:id,name,email', 'plan:id,name,display_name']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        $subscriptions = $query->orderBy('created_at', 'desc')
            ->paginate($request->query('per_page', 20));

        return response()->json($subscriptions);
    }

    /**
     * Assigner un plan à un tenant
     */
    public function assignPlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'plan_id' => 'required|exists:system_subscription_plans,id',
            'duration_months' => 'required|integer|min:1|max:24',
            'amount_paid' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string',
            'payment_reference' => 'nullable|string',
        ]);

        $tenant = Tenant::findOrFail($validated['tenant_id']);
        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);

        DB::beginTransaction();
        try {
            // Désactiver l'ancien abonnement
            Subscription::where('tenant_id', $tenant->id)
                ->whereIn('status', ['active', 'trial'])
                ->update(['status' => 'cancelled', 'ends_at' => now()]);

            // Créer le nouvel abonnement
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addMonths($validated['duration_months']),
                'amount_paid' => $validated['amount_paid'] ?? 0,
                'payment_method' => $validated['payment_method'],
                'payment_reference' => $validated['payment_reference'],
            ]);

            // Mettre à jour le tenant
            $tenant->update([
                'plan_id' => $plan->id,
                'status' => 'active',
                'subscription_expires_at' => $subscription->ends_at,
            ]);

            // Synchroniser les modules selon le plan
            $subscriptionService = new SubscriptionService();
            $subscriptionService->syncTenantModules($tenant->id, $plan->id);

            // Enregistrer le paiement si montant > 0
            if (($validated['amount_paid'] ?? 0) > 0) {
                Payment::create([
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'amount' => $validated['amount_paid'],
                    'currency' => $plan->currency,
                    'status' => 'completed',
                    'payment_method' => $validated['payment_method'] ?? 'manual',
                    'payment_reference' => $validated['payment_reference'],
                    'description' => "Abonnement {$plan->display_name} - {$validated['duration_months']} mois",
                    'paid_at' => now(),
                ]);
            }

            SystemLog::log(
                "Abonnement assigné: {$tenant->name} -> {$plan->display_name}",
                'info',
                'payment',
                null,
                ['tenant_id' => $tenant->id, 'plan_id' => $plan->id]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $subscription->load(['tenant', 'plan']),
                'message' => 'Abonnement assigné avec succès',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Liste des paiements
     */
    public function payments(Request $request): JsonResponse
    {
        $query = Payment::with(['tenant:id,name', 'subscription.plan:id,name,display_name']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        $payments = $query->orderBy('created_at', 'desc')
            ->paginate($request->query('per_page', 20));

        return response()->json($payments);
    }

    /**
     * Enregistrer un paiement manuel
     */
    public function recordPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string',
            'description' => 'nullable|string',
            'extend_subscription_months' => 'nullable|integer|min:1',
        ]);

        $tenant = Tenant::findOrFail($validated['tenant_id']);

        DB::beginTransaction();
        try {
            $subscription = Subscription::where('tenant_id', $tenant->id)
                ->whereIn('status', ['active', 'trial', 'expired'])
                ->orderBy('created_at', 'desc')
                ->first();

            $payment = Payment::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription?->id,
                'amount' => $validated['amount'],
                'currency' => $tenant->currency ?? 'XOF',
                'status' => 'completed',
                'payment_method' => $validated['payment_method'],
                'payment_reference' => $validated['payment_reference'],
                'description' => $validated['description'],
                'paid_at' => now(),
            ]);

            // Prolonger l'abonnement si demandé
            if ($subscription && ($validated['extend_subscription_months'] ?? 0) > 0) {
                $newEndDate = ($subscription->ends_at ?? now())->addMonths($validated['extend_subscription_months']);
                $subscription->update([
                    'status' => 'active',
                    'ends_at' => $newEndDate,
                ]);
                $tenant->update([
                    'status' => 'active',
                    'subscription_expires_at' => $newEndDate,
                ]);
            }

            SystemLog::log(
                "Paiement enregistré: {$tenant->name} - {$validated['amount']} {$tenant->currency}",
                'info',
                'payment',
                null,
                ['tenant_id' => $tenant->id, 'payment_id' => $payment->id]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $payment,
                'message' => 'Paiement enregistré',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Statistiques des revenus
     */
    public function revenueStats(Request $request): JsonResponse
    {
        $year = $request->query('year', now()->year);

        $monthlyRevenue = Payment::where('status', 'completed')
            ->whereYear('paid_at', $year)
            ->selectRaw('MONTH(paid_at) as month, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $totalRevenue = Payment::where('status', 'completed')
            ->whereYear('paid_at', $year)
            ->sum('amount');

        $byPaymentMethod = Payment::where('status', 'completed')
            ->whereYear('paid_at', $year)
            ->selectRaw('payment_method, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('payment_method')
            ->get();

        return response()->json([
            'data' => [
                'year' => $year,
                'total' => $totalRevenue,
                'monthly' => $monthlyRevenue,
                'by_payment_method' => $byPaymentMethod,
            ],
        ]);
    }
}
