<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait pour les modèles appartenant à un tenant
 * Applique automatiquement le TenantScope et gère l'assignation du tenant_id
 */
trait BelongsToTenant
{
    /**
     * Boot du trait - applique le scope global
     */
    protected static function bootBelongsToTenant(): void
    {
        // Appliquer le scope global pour filtrer par tenant
        static::addGlobalScope(new TenantScope);

        // Auto-assigner le tenant_id lors de la création
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                // Priorité 1: tenant résolu via middleware
                if (app()->bound('tenant') && app('tenant')) {
                    $model->tenant_id = app('tenant')->id;
                }
                // Priorité 2: tenant de l'utilisateur authentifié
                elseif (auth()->check() && auth()->user()->tenant_id) {
                    $model->tenant_id = auth()->user()->tenant_id;
                }
            }
        });

        // Vérifier que le tenant_id ne change pas lors de la mise à jour
        static::updating(function ($model) {
            if ($model->isDirty('tenant_id') && $model->getOriginal('tenant_id') !== null) {
                throw new \Exception('Modification du tenant_id interdite');
            }
        });
    }

    /**
     * Relation avec le tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope pour filtrer par tenant spécifique (utile pour superadmin)
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->withoutGlobalScope(TenantScope::class)
                     ->where('tenant_id', $tenantId);
    }

    /**
     * Scope pour ignorer le filtre tenant (superadmin only)
     */
    public function scopeWithoutTenantScope($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
