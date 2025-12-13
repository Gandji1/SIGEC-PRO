<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pour résoudre et injecter le contexte tenant
 * Assure l'isolation des données entre tenants
 */
class TenantResolver
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = null;
        $user = auth()->user();

        // 1. Si utilisateur authentifié, utiliser son tenant
        if ($user) {
            // SuperAdmin n'a pas de tenant par défaut
            if (in_array($user->role, ['superadmin', 'super_admin'])) {
                // SuperAdmin peut spécifier un tenant via header pour impersonation
                $impersonateTenantId = $request->header('X-Impersonate-Tenant');
                if ($impersonateTenantId) {
                    $tenant = Tenant::find($impersonateTenantId);
                    if ($tenant) {
                        // Logger l'impersonation
                        \Log::channel('audit')->info('SuperAdmin impersonation', [
                            'admin_id' => $user->id,
                            'admin_email' => $user->email,
                            'tenant_impersonated' => $tenant->id,
                            'tenant_name' => $tenant->name,
                            'timestamp' => now()->toIso8601String(),
                            'ip' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                        ]);
                    }
                }
                // Pas de tenant pour superadmin sans impersonation - c'est OK
            } else {
                // Utilisateur normal - doit avoir un tenant
                if (!$user->tenant_id) {
                    return response()->json([
                        'error' => 'Utilisateur non associé à un tenant',
                        'code' => 'NO_TENANT'
                    ], 403);
                }
                $tenant = Tenant::find($user->tenant_id);
                
                if (!$tenant) {
                    return response()->json([
                        'error' => 'Tenant introuvable',
                        'code' => 'TENANT_NOT_FOUND'
                    ], 403);
                }

                // Vérifier que le tenant est actif
                if ($tenant->status !== 'active' && $tenant->status !== 'trial') {
                    return response()->json([
                        'error' => 'Tenant inactif ou suspendu',
                        'code' => 'TENANT_INACTIVE'
                    ], 403);
                }
            }
        }

        // 2. Injecter le tenant dans le conteneur et la requête
        if ($tenant) {
            app()->instance('tenant', $tenant);
            $request->merge(['tenant' => $tenant]);
            $request->attributes->set('tenant_id', $tenant->id);

            // 3. Configurer PostgreSQL RLS si activé
            try {
                DB::statement("SET LOCAL app.tenant_id = '{$tenant->id}'");
            } catch (\Exception $e) {
                // RLS non configuré, on continue avec le scope Eloquent
            }
        }

        return $next($request);
    }
}
