<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\System\TenantModule;
use App\Models\System\Module;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantModule
{
    /**
     * Vérifie que le tenant a accès au module demandé
     * Usage: middleware('module:pos') ou middleware('module:accounting')
     */
    public function handle(Request $request, Closure $next, string $moduleCode): Response
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

        // Vérifier si le module existe
        $module = Module::where('code', $moduleCode)->first();
        
        if (!$module) {
            // Module inconnu, laisser passer (backward compatibility)
            return $next($request);
        }

        // Les modules core sont toujours accessibles
        if ($module->is_core) {
            return $next($request);
        }

        // Vérifier si le module est activé pour ce tenant
        $tenantModule = TenantModule::where('tenant_id', $user->tenant_id)
            ->where('module_id', $module->id)
            ->where('is_enabled', true)
            ->first();

        if (!$tenantModule) {
            return response()->json([
                'error' => 'module_disabled',
                'message' => "Le module '{$module->name}' n'est pas activé pour votre compte.",
                'module' => $moduleCode,
            ], 403);
        }

        return $next($request);
    }
}
