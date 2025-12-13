<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log des webhooks reçus pour traçabilité et idempotence
 */
class WebhookLog extends Model
{
    protected $table = 'webhook_logs';

    protected $fillable = [
        'tenant_id',
        'provider',
        'event_type',
        'reference',
        'idempotency_key',
        'payload',
        'signature',
        'signature_valid',
        'status',           // received, processed, failed, duplicate
        'error_message',
        'processed_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'payload' => 'array',
        'signature_valid' => 'boolean',
        'processed_at' => 'datetime',
    ];

    // ========================================
    // RELATIONS
    // ========================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ========================================
    // MÉTHODES STATIQUES
    // ========================================

    /**
     * Vérifier si un webhook a déjà été traité (idempotence)
     */
    public static function isDuplicate(string $provider, string $reference): bool
    {
        return self::where('provider', $provider)
            ->where('reference', $reference)
            ->where('status', 'processed')
            ->exists();
    }

    /**
     * Enregistrer un webhook reçu
     */
    public static function logReceived(
        string $provider,
        string $reference,
        array $payload,
        ?int $tenantId = null,
        ?string $signature = null,
        ?string $ipAddress = null
    ): self {
        return self::create([
            'tenant_id' => $tenantId,
            'provider' => $provider,
            'event_type' => $payload['event'] ?? $payload['type'] ?? 'unknown',
            'reference' => $reference,
            'idempotency_key' => "{$provider}_{$reference}",
            'payload' => $payload,
            'signature' => $signature,
            'status' => 'received',
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Marquer comme traité
     */
    public function markProcessed(): void
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Marquer comme dupliqué
     */
    public function markDuplicate(): void
    {
        $this->update(['status' => 'duplicate']);
    }

    /**
     * Marquer comme échoué
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }

    /**
     * Marquer signature valide/invalide
     */
    public function setSignatureValid(bool $valid): void
    {
        $this->update(['signature_valid' => $valid]);
    }
}
