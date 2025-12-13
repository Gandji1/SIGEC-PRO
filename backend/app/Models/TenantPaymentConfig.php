<?php

namespace App\Models;

use App\Services\EncryptionService;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuration de paiement par tenant
 * Stocke les clés PSP de manière chiffrée et isolée
 */
class TenantPaymentConfig extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_payment_configs';

    protected $fillable = [
        'tenant_id',
        'provider',           // fedapay, kkiapay, momo, bank
        'environment',        // sandbox, production
        'is_enabled',
        'public_key',
        'secret_key_encrypted',
        'api_user_encrypted',
        'extra_config',       // JSON pour config additionnelle
        'webhook_secret_encrypted',
        'last_webhook_at',
        'last_test_at',
        'test_status',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'extra_config' => 'array',
        'last_webhook_at' => 'datetime',
        'last_test_at' => 'datetime',
    ];

    protected $hidden = [
        'secret_key_encrypted',
        'api_user_encrypted',
        'webhook_secret_encrypted',
    ];

    // ========================================
    // ACCESSORS - Déchiffrement automatique
    // ========================================

    public function getSecretKeyAttribute(): ?string
    {
        return EncryptionService::decrypt($this->secret_key_encrypted);
    }

    public function getApiUserAttribute(): ?string
    {
        return EncryptionService::decrypt($this->api_user_encrypted);
    }

    public function getWebhookSecretAttribute(): ?string
    {
        return EncryptionService::decrypt($this->webhook_secret_encrypted);
    }

    // ========================================
    // MUTATORS - Chiffrement automatique
    // ========================================

    public function setSecretKeyAttribute(?string $value): void
    {
        $this->attributes['secret_key_encrypted'] = EncryptionService::encrypt($value);
    }

    public function setApiUserAttribute(?string $value): void
    {
        $this->attributes['api_user_encrypted'] = EncryptionService::encrypt($value);
    }

    public function setWebhookSecretAttribute(?string $value): void
    {
        $this->attributes['webhook_secret_encrypted'] = EncryptionService::encrypt($value);
    }

    // ========================================
    // MÉTHODES UTILITAIRES
    // ========================================

    /**
     * Obtenir la config pour affichage (clés masquées)
     */
    public function toSafeArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'environment' => $this->environment,
            'is_enabled' => $this->is_enabled,
            'public_key' => $this->public_key,
            'secret_key_masked' => EncryptionService::mask($this->secret_key),
            'has_secret_key' => !empty($this->secret_key_encrypted),
            'has_webhook_secret' => !empty($this->webhook_secret_encrypted),
            'last_webhook_at' => $this->last_webhook_at?->toIso8601String(),
            'last_test_at' => $this->last_test_at?->toIso8601String(),
            'test_status' => $this->test_status,
        ];
    }

    /**
     * Obtenir la config d'un provider pour un tenant
     */
    public static function getForProvider(int $tenantId, string $provider): ?self
    {
        return self::where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->first();
    }

    /**
     * Obtenir toutes les configs actives d'un tenant
     */
    public static function getActiveForTenant(int $tenantId): array
    {
        return self::where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->get()
            ->map(fn($c) => $c->toSafeArray())
            ->toArray();
    }

    /**
     * Vérifier si un provider est configuré et actif
     */
    public static function isProviderEnabled(int $tenantId, string $provider): bool
    {
        return self::where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->where('is_enabled', true)
            ->whereNotNull('secret_key_encrypted')
            ->exists();
    }
}
