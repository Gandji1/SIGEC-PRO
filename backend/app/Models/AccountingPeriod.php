<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AccountingPeriod extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'start_date',
        'end_date',
        'status',
        'summary',
        'closed_by',
        'closed_at',
        'closing_notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'summary' => 'array',
        'closed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public static function isDateLocked(int $tenantId, string $date): bool
    {
        return self::where('tenant_id', $tenantId)
            ->where('status', 'closed')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
    }

    public static function getCurrentPeriod(int $tenantId, string $type = 'monthly'): ?self
    {
        $now = Carbon::now();
        return self::where('tenant_id', $tenantId)
            ->where('type', $type)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->first();
    }

    public static function createMonthlyPeriod(int $tenantId, int $year, int $month): self
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return self::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'type' => 'monthly',
                'start_date' => $start->toDateString(),
            ],
            [
                'name' => $start->translatedFormat('F Y'),
                'end_date' => $end->toDateString(),
                'status' => 'open',
            ]
        );
    }

    public function close(int $userId, array $summary = [], ?string $notes = null): void
    {
        if ($this->status === 'closed') {
            throw new \Exception('Cette période est déjà clôturée');
        }

        $this->update([
            'status' => 'closed',
            'summary' => $summary,
            'closed_by' => $userId,
            'closed_at' => now(),
            'closing_notes' => $notes,
        ]);
    }

    public function reopen(int $userId): void
    {
        if ($this->status !== 'closed') {
            throw new \Exception('Cette période n\'est pas clôturée');
        }

        // Vérifier qu'aucune période postérieure n'est clôturée
        $laterClosed = self::where('tenant_id', $this->tenant_id)
            ->where('type', $this->type)
            ->where('start_date', '>', $this->end_date)
            ->where('status', 'closed')
            ->exists();

        if ($laterClosed) {
            throw new \Exception('Impossible de réouvrir: des périodes postérieures sont clôturées');
        }

        $this->update([
            'status' => 'open',
            'closed_by' => null,
            'closed_at' => null,
        ]);
    }
}
