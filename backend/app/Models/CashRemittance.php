<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashRemittance extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'session_id',
        'pos_id',
        'from_user_id',
        'to_user_id',
        'amount',
        'status',
        'reference',
        'notes',
        'remitted_at',
        'received_at',
        'validated_at',
        'validated_by',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'remitted_at' => 'datetime',
        'received_at' => 'datetime',
        'validated_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class, 'session_id');
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function validatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isReceived(): bool
    {
        return in_array($this->status, ['received', 'validated']);
    }

    public function receive(int $userId): void
    {
        $this->update([
            'to_user_id' => $userId,
            'received_at' => now(),
            'status' => 'received',
        ]);

        // Créer le mouvement d'entrée pour le gérant
        CashMovement::record(
            $this->tenant_id,
            $userId,
            'in',
            'transfer_in',
            $this->amount,
            "Remise reçue de " . $this->fromUser->name,
            null,
            null,
            'cash',
            $this->id,
            'cash_remittance'
        );
    }

    public function validate(int $userId): void
    {
        $this->update([
            'validated_by' => $userId,
            'validated_at' => now(),
            'status' => 'validated',
        ]);
    }

    public function reject(int $userId, string $reason): void
    {
        $this->update([
            'validated_by' => $userId,
            'validated_at' => now(),
            'status' => 'rejected',
            'notes' => $reason,
        ]);
    }

    public static function generateReference(int $tenantId): string
    {
        $today = now()->format('Ymd');
        $count = self::where('tenant_id', $tenantId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return "REM-$today-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Créer une remise de fonds
     */
    public static function createRemittance(
        int $tenantId,
        int $fromUserId,
        float $amount,
        ?int $sessionId = null,
        ?int $posId = null,
        ?string $notes = null
    ): self {
        $remittance = self::create([
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'pos_id' => $posId,
            'from_user_id' => $fromUserId,
            'amount' => $amount,
            'reference' => self::generateReference($tenantId),
            'notes' => $notes,
            'remitted_at' => now(),
            'status' => 'pending',
        ]);

        // Créer le mouvement de sortie pour le caissier
        CashMovement::record(
            $tenantId,
            $fromUserId,
            'out',
            'transfer_out',
            $amount,
            "Remise au gérant",
            $sessionId,
            $posId,
            'cash',
            $remittance->id,
            'cash_remittance'
        );

        return $remittance;
    }
}
