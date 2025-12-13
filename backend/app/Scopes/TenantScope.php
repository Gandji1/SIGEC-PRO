<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global Scope pour l'isolation multi-tenant
 * Applique automatiquement un filtre tenant_id sur toutes les requêtes
 */
class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Récupérer le tenant actuel depuis le conteneur
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        
        // Si tenant résolu via middleware, l'utiliser
        if ($tenant) {
            $builder->where($model->getTable() . '.tenant_id', $tenant->id);
            return;
        }
        
        // Si utilisateur authentifié, utiliser son tenant_id
        if (auth()->check()) {
            $user = auth()->user();
            
            // SuperAdmin SANS tenant_id - peut voir tout (pour agrégation)
            // SuperAdmin AVEC tenant_id - doit être filtré comme un utilisateur normal
            if (in_array($user->role, ['superadmin', 'super_admin']) && !$user->tenant_id) {
                // SuperAdmin global voit tout sans filtre
                return;
            }
            
            // Utilisateur normal OU superadmin avec tenant_id
            if ($user->tenant_id) {
                $builder->where($model->getTable() . '.tenant_id', $user->tenant_id);
                return;
            }
        }

        // Mode fail-closed: si pas de tenant résolu, bloquer tout accès
        // Sauf en mode console pour migrations/seeders
        if (!app()->runningInConsole()) {
            $builder->whereRaw('1 = 0');
        }
    }
}
