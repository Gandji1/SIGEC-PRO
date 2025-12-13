<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\System\Subscription;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimits
{
    /**
     * Vérifie les limites du plan (utilisateurs, produits, etc.)
     * Usage: middleware('plan_limit:users') ou middleware('plan_limit:products')
     */
    public function handle(Request $request, Closure $next, string $limitType): Response
    {
        $user = $request->user();
        
        // Super Admin bypass
        if ($user && $user->role === 'super_admin') {
            return $next($request);
        }

        // Pas d'utilisateur ou pas de tenant
        if (!$user || !$user->tenant_id) {
            return $next($request);
        }

        $tenant = $user->tenant;
        
        // Récupérer l'abonnement et le plan
        $subscription = Subscription::with('plan')
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['active', 'trial'])
            ->first();

        if (!$subscription || !$subscription->plan) {
            return $next($request); // Pas de plan, pas de limite
        }

        $plan = $subscription->plan;
        $exceeded = false;
        $limit = 0;
        $current = 0;

        switch ($limitType) {
            case 'users':
                $current = $tenant->users()->count();
                $limit = $plan->max_users;
                $exceeded = $current >= $limit;
                break;

            case 'products':
                $current = $tenant->products()->count();
                $limit = $plan->max_products;
                $exceeded = $current >= $limit;
                break;

            case 'warehouses':
                $current = $tenant->warehouses()->count();
                $limit = $plan->max_warehouses;
                $exceeded = $current >= $limit;
                break;

            case 'pos':
                // Compter les sessions POS actives ou les caisses
                $current = $tenant->users()->where('role', 'caissier')->count();
                $limit = $plan->max_pos;
                $exceeded = $current >= $limit;
                break;

            case 'storage':
                $current = $tenant->storage_used_mb ?? 0;
                $limit = $plan->storage_limit_mb;
                $exceeded = $current >= $limit;
                break;
        }

        if ($exceeded) {
            return response()->json([
                'error' => 'plan_limit_exceeded',
                'message' => "Limite du plan atteinte pour '{$limitType}'. Passez à un plan supérieur.",
                'limit_type' => $limitType,
                'current' => $current,
                'limit' => $limit,
            ], 403);
        }

        return $next($request);
    }
}
