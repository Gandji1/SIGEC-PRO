<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegisterSession extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'pos_id',
        'opened_by',
        'closed_by',
        'opening_balance',
        'closing_balance',
        'expected_balance',
        'difference',
        'cash_sales',
        'card_sales',
        'mobile_sales',
        'other_sales',
        'cash_out',
        'cash_in',
        'transactions_count',
        'status',
        'opened_at',
        'closed_at',
        'validated_at',
        'validated_by',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'expected_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'cash_sales' => 'decimal:2',
        'card_sales' => 'decimal:2',
        'mobile_sales' => 'decimal:2',
        'other_sales' => 'decimal:2',
        'cash_out' => 'decimal:2',
        'cash_in' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'validated_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }

    public function openedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function validatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'session_id');
    }

    public function remittances(): HasMany
    {
        return $this->hasMany(CashRemittance::class, 'session_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['closed', 'validated']);
    }

    public function calculateExpectedBalance(): float
    {
        return $this->opening_balance 
            + $this->cash_sales 
            + $this->cash_in 
            - $this->cash_out;
    }

    public function close(float $closingBalance, int $userId, ?string $notes = null): void
    {
        $expected = $this->calculateExpectedBalance();
        
        $this->update([
            'closing_balance' => $closingBalance,
            'expected_balance' => $expected,
            'difference' => $closingBalance - $expected,
            'closed_by' => $userId,
            'closed_at' => now(),
            'status' => 'closed',
            'notes' => $notes,
        ]);
    }

    public function validate(int $userId): void
    {
        $this->update([
            'validated_by' => $userId,
            'validated_at' => now(),
            'status' => 'validated',
        ]);
    }

    public static function getOpenSession(int $tenantId, ?int $posId = null): ?self
    {
        $query = self::where('tenant_id', $tenantId)
            ->where('status', 'open');

        if ($posId) {
            $query->where('pos_id', $posId);
        }

        return $query->latest('opened_at')->first();
    }

    public static function openSession(int $tenantId, int $userId, float $openingBalance, ?int $posId = null): self
    {
        return self::create([
            'tenant_id' => $tenantId,
            'pos_id' => $posId,
            'opened_by' => $userId,
            'opening_balance' => $openingBalance,
            'opened_at' => now(),
            'status' => 'open',
        ]);
    }
}
