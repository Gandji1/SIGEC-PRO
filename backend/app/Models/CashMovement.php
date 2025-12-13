<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'session_id',
        'pos_id',
        'user_id',
        'type',
        'category',
        'reference',
        'description',
        'amount',
        'payment_method',
        'related_id',
        'related_type',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isIncome(): bool
    {
        return $this->type === 'in';
    }

    public function isExpense(): bool
    {
        return $this->type === 'out';
    }

    /**
     * Enregistrer un mouvement de caisse
     */
    public static function record(
        int $tenantId,
        int $userId,
        string $type,
        string $category,
        float $amount,
        string $description,
        ?int $sessionId = null,
        ?int $posId = null,
        ?string $paymentMethod = null,
        ?int $relatedId = null,
        ?string $relatedType = null
    ): self {
        $movement = self::create([
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'pos_id' => $posId,
            'user_id' => $userId,
            'type' => $type,
            'category' => $category,
            'reference' => self::generateReference($tenantId),
            'description' => $description,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'related_id' => $relatedId,
            'related_type' => $relatedType,
        ]);

        // Mettre à jour la session si elle existe
        if ($sessionId) {
            $session = CashRegisterSession::find($sessionId);
            if ($session && $session->isOpen()) {
                if ($type === 'in') {
                    if ($paymentMethod === 'cash' || $category === 'deposit') {
                        $session->increment('cash_in', $amount);
                    }
                    if ($category === 'sale') {
                        match ($paymentMethod) {
                            'cash' => $session->increment('cash_sales', $amount),
                            'card' => $session->increment('card_sales', $amount),
                            'momo', 'fedapay', 'kkiapay' => $session->increment('mobile_sales', $amount),
                            default => $session->increment('other_sales', $amount),
                        };
                        $session->increment('transactions_count');
                    }
                } else {
                    $session->increment('cash_out', $amount);
                }
            }
        }

        return $movement;
    }

    public static function generateReference(int $tenantId): string
    {
        $today = now()->format('Ymd');
        $count = self::where('tenant_id', $tenantId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return "MV-$today-" . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    }
}
