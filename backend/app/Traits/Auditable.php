<?php

namespace App\Traits;

use App\Models\AuditLog;

/**
 * Trait pour l'audit automatique des modèles
 * Enregistre automatiquement les créations, modifications et suppressions
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        // Log création
        static::created(function ($model) {
            self::logAudit($model, 'create', $model->getAttributes());
        });

        // Log modification
        static::updated(function ($model) {
            $changes = $model->getChanges();
            $original = array_intersect_key($model->getOriginal(), $changes);
            
            // Ne pas logger si aucun changement significatif
            if (empty($changes) || self::shouldSkipAudit($changes)) {
                return;
            }
            
            self::logAudit($model, 'update', [
                'old' => $original,
                'new' => $changes,
            ]);
        });

        // Log suppression
        static::deleted(function ($model) {
            self::logAudit($model, 'delete', $model->getAttributes());
        });

        // Log restauration (si SoftDeletes)
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                self::logAudit($model, 'restore', $model->getAttributes());
            });
        }
    }

    /**
     * Enregistrer l'audit
     */
    protected static function logAudit($model, string $action, array $changes): void
    {
        try {
            $user = auth()->guard('sanctum')->user();
            $tenantId = $user?->tenant_id ?? $model->tenant_id ?? null;
            
            // Ne pas logger en mode console (migrations, seeders)
            if (app()->runningInConsole() && !app()->runningUnitTests()) {
                return;
            }

            AuditLog::create([
                'tenant_id' => $tenantId,
                'user_id' => $user?->id,
                'action' => $action,
                'resource_type' => class_basename($model),
                'model' => get_class($model),
                'model_id' => $model->getKey(),
                'changes' => self::sanitizeChanges($changes),
                'ip_address' => request()->ip(),
                'user_agent' => substr(request()->userAgent() ?? '', 0, 255),
                'description' => self::generateDescription($model, $action),
            ]);
        } catch (\Exception $e) {
            // Ne pas bloquer l'opération si l'audit échoue
            \Log::warning('Audit log failed: ' . $e->getMessage());
        }
    }

    /**
     * Générer une description lisible
     */
    protected static function generateDescription($model, string $action): string
    {
        $modelName = class_basename($model);
        $identifier = $model->name ?? $model->reference ?? $model->code ?? $model->getKey();
        
        return match($action) {
            'create' => "{$modelName} '{$identifier}' créé",
            'update' => "{$modelName} '{$identifier}' modifié",
            'delete' => "{$modelName} '{$identifier}' supprimé",
            'restore' => "{$modelName} '{$identifier}' restauré",
            default => "{$action} sur {$modelName} '{$identifier}'",
        };
    }

    /**
     * Nettoyer les données sensibles des changements
     */
    protected static function sanitizeChanges(array $changes): array
    {
        $sensitiveFields = [
            'password', 'password_hash', 'secret', 'api_key', 'secret_key',
            'token', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($changes[$field])) {
                $changes[$field] = '[REDACTED]';
            }
            if (isset($changes['old'][$field])) {
                $changes['old'][$field] = '[REDACTED]';
            }
            if (isset($changes['new'][$field])) {
                $changes['new'][$field] = '[REDACTED]';
            }
        }

        return $changes;
    }

    /**
     * Vérifier si on doit ignorer cet audit
     */
    protected static function shouldSkipAudit(array $changes): bool
    {
        // Ignorer les changements de timestamps uniquement
        $ignoredFields = ['updated_at', 'created_at', 'last_login_at'];
        $changedFields = array_keys($changes);
        
        return empty(array_diff($changedFields, $ignoredFields));
    }
}
