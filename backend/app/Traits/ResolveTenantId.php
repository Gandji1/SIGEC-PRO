<?php

namespace App\Traits;

use Illuminate\Http\Request;

/**
 * Trait pour résoudre le tenant_id de manière sécurisée et uniforme
 * À utiliser dans tous les contrôleurs qui ont besoin du tenant_id
 */
trait ResolveTenantId
{
    /**
     * Résoudre le tenant_id de manière sécurisée
     * 
     * Priorité:
     * 1. Utilisateur authentifié avec tenant_id
     * 2. SuperAdmin avec header X-Tenant-ID (impersonation)
     * 3. null (pas de tenant)
     */
    protected function resolveTenantId(Request $request): ?int
    {
        $user = auth()->guard('sanctum')->user();
        
        // Utilisateur normal avec tenant_id
        if ($user && $user->tenant_id) {
            return (int) $user->tenant_id;
        }
        
        // SuperAdmin peut utiliser le header X-Tenant-ID pour impersonation
        if ($user && in_array($user->role, ['super_admin', 'superadmin'])) {
            $tenantId = $request->header('X-Tenant-ID');
            if ($tenantId) {
                return (int) $tenantId;
            }
            // SuperAdmin sans impersonation - retourne null (accès global)
            return null;
        }
        
        // Pas de tenant résolu
        return null;
    }

    /**
     * Résoudre le tenant_id avec obligation (retourne erreur si pas de tenant)
     */
    protected function requireTenantId(Request $request): int
    {
        $tenantId = $this->resolveTenantId($request);
        
        if (!$tenantId) {
            abort(400, 'Tenant non trouvé');
        }
        
        return $tenantId;
    }
}
