<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\System\Subscription;
use App\Models\System\SubscriptionPlan;
use App\Models\System\TenantModule;
use App\Models\System\Module;
use App\Models\System\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantManagementController extends Controller
{
    /**
     * Liste des tenants avec pagination et filtres
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::with(['users:id,tenant_id,name,email,role'])
            ->withCount('users');

        // Filtres
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%");
            });
        }

        // Tri
        $sortField = $request->query('sort', '-created_at');
        $sortDir = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $sortField = ltrim($sortField, '-');
        $query->orderBy($sortField, $sortDir);

        $perPage = min($request->query('per_page', 20), 100);
        $tenants = $query->paginate($perPage);

        return response()->json($tenants);
    }

    /**
     * Détails d'un tenant
     */
    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->load([
            'users:id,tenant_id,name,email,role,created_at',
        ]);

        // Statistiques du tenant
        $stats = [
            'users_count' => $tenant->users->count(),
            'products_count' => $tenant->products()->count(),
            'sales_count' => $tenant->sales()->count(),
            'total_revenue' => $tenant->sales()->whereIn('status', ['completed', 'paid'])->sum('total'),
            'storage_used_mb' => $tenant->storage_used_mb ?? 0,
        ];

        // Abonnement actuel
        $subscription = Subscription::with('plan')
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->first();

        // Modules activés
        $modules = TenantModule::with('module')
            ->where('tenant_id', $tenant->id)
            ->where('is_enabled', true)
            ->get();

        return response()->json([
            'data' => [
                'tenant' => $tenant,
                'stats' => $stats,
                'subscription' => $subscription,
                'modules' => $modules,
            ],
        ]);
    }

    /**
     * Créer un nouveau tenant
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email',
            'phone' => 'nullable|string|max:50',
            'business_type' => 'required|string',
            'country' => 'nullable|string|size:2',
            'currency' => 'nullable|string|size:3',
            'plan_id' => 'nullable|exists:system_subscription_plans,id',
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|unique:users,email',
            'owner_password' => 'nullable|string|min:6',
        ]);

        DB::beginTransaction();
        try {
            // Créer le tenant
            $tenant = Tenant::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'domain' => Str::slug($validated['name']) . '.sigec.local',
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'business_type' => $validated['business_type'],
                'country' => $validated['country'] ?? 'BJ',
                'currency' => $validated['currency'] ?? 'XOF',
                'status' => 'active',
            ]);

            // Créer l'utilisateur propriétaire
            $password = $validated['owner_password'] ?? Str::random(10);
            $owner = User::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['owner_name'],
                'email' => $validated['owner_email'],
                'password' => Hash::make($password),
                'role' => 'owner',
            ]);

            // Créer l'abonnement si un plan est spécifié ou existe
            $plan = null;
            if (!empty($validated['plan_id'])) {
                $plan = SubscriptionPlan::find($validated['plan_id']);
            }
            if (!$plan) {
                $plan = SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->first();
            }

            if ($plan) {
                Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'status' => 'trial',
                    'starts_at' => now(),
                    'trial_ends_at' => now()->addDays($plan->trial_days ?? 14),
                    'ends_at' => now()->addDays($plan->trial_days ?? 14),
                ]);

                $tenant->update(['plan_id' => $plan->id]);
            }
            // Si aucun plan n'existe, le tenant est créé sans abonnement
            // Le SuperAdmin devra d'abord créer un plan puis l'assigner

            // Activer les modules de base
            $coreModules = Module::where('is_core', true)->get();
            foreach ($coreModules as $module) {
                TenantModule::create([
                    'tenant_id' => $tenant->id,
                    'module_id' => $module->id,
                    'is_enabled' => true,
                    'enabled_at' => now(),
                ]);
            }

            // Log
            SystemLog::log(
                "Tenant créé: {$tenant->name}",
                'info',
                'tenant',
                "Nouveau tenant créé avec propriétaire {$owner->email}",
                ['tenant_id' => $tenant->id, 'owner_id' => $owner->id]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'tenant' => $tenant,
                    'owner' => $owner,
                    'temporary_password' => $validated['owner_password'] ? null : $password,
                ],
                'message' => 'Tenant créé avec succès',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour un tenant
     */
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:tenants,email,' . $tenant->id,
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'business_type' => 'nullable|string',
            'currency' => 'nullable|string|size:3',
            'tva_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $tenant->update($validated);

        SystemLog::log(
            "Tenant modifié: {$tenant->name}",
            'info',
            'tenant',
            null,
            ['tenant_id' => $tenant->id, 'changes' => $validated]
        );

        return response()->json([
            'success' => true,
            'data' => $tenant,
        ]);
    }

    /**
     * Suspendre un tenant
     */
    public function suspend(Request $request, Tenant $tenant): JsonResponse
    {
        $reason = $request->input('reason', 'Suspension manuelle');

        $tenant->update(['status' => 'suspended']);

        // Suspendre l'abonnement
        Subscription::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->update(['status' => 'suspended']);

        SystemLog::log(
            "Tenant suspendu: {$tenant->name}",
            'warning',
            'tenant',
            $reason,
            ['tenant_id' => $tenant->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Tenant suspendu',
            'data' => $tenant,
        ]);
    }

    /**
     * Réactiver un tenant
     */
    public function activate(Tenant $tenant): JsonResponse
    {
        $tenant->update(['status' => 'active']);

        // Réactiver l'abonnement si valide
        $subscription = Subscription::where('tenant_id', $tenant->id)
            ->where('status', 'suspended')
            ->first();

        if ($subscription && (!$subscription->ends_at || $subscription->ends_at->isFuture())) {
            $subscription->update(['status' => 'active']);
        }

        SystemLog::log(
            "Tenant réactivé: {$tenant->name}",
            'info',
            'tenant',
            null,
            ['tenant_id' => $tenant->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Tenant réactivé',
            'data' => $tenant,
        ]);
    }

    /**
     * Supprimer un tenant (soft delete)
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        // Archiver avant suppression
        SystemLog::log(
            "Tenant supprimé: {$tenant->name}",
            'warning',
            'tenant',
            "Tenant archivé et marqué pour suppression",
            ['tenant_id' => $tenant->id, 'tenant_data' => $tenant->toArray()]
        );

        $tenant->update(['status' => 'deleted']);
        $tenant->delete(); // Soft delete

        return response()->json([
            'success' => true,
            'message' => 'Tenant supprimé',
        ]);
    }

    /**
     * Impersonate - Accéder au backoffice d'un tenant
     */
    public function impersonate(Tenant $tenant): JsonResponse
    {
        // Trouver le propriétaire du tenant
        $owner = User::where('tenant_id', $tenant->id)
            ->where('role', 'owner')
            ->first();

        if (!$owner) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun propriétaire trouvé pour ce tenant',
            ], 404);
        }

        // Créer un token temporaire
        $token = $owner->createToken('impersonate', ['*'], now()->addHours(2))->plainTextToken;

        SystemLog::log(
            "Impersonation: {$tenant->name}",
            'info',
            'auth',
            "Super Admin a accédé au tenant",
            ['tenant_id' => $tenant->id, 'impersonated_user' => $owner->id]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => $owner,
                'tenant' => $tenant,
            ],
        ]);
    }

    /**
     * Statistiques d'un tenant
     */
    public function stats(Tenant $tenant): JsonResponse
    {
        $stats = [
            'users' => $tenant->users()->count(),
            'products' => $tenant->products()->count(),
            'sales' => [
                'count' => $tenant->sales()->count(),
                'total' => $tenant->sales()->whereIn('status', ['completed', 'paid'])->sum('total'),
                'this_month' => $tenant->sales()
                    ->whereIn('status', ['completed', 'paid'])
                    ->whereMonth('created_at', now()->month)
                    ->sum('total'),
            ],
            'purchases' => [
                'count' => $tenant->purchases()->count(),
                'total' => $tenant->purchases()->sum('total'),
            ],
            'storage_used_mb' => $tenant->storage_used_mb ?? 0,
            'last_activity' => $tenant->last_activity_at,
        ];

        return response()->json(['data' => $stats]);
    }
}
