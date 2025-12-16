<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Session de réconciliation serveur (Option B)
 * Point de caisse entre le serveur et le gérant
 */
class ServerReconciliation extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'server_id',
        'manager_id',
        'pos_id',
        'reference',
        'session_start',
        'session_end',
        'total_delegated_value',
        'total_sales',
        'total_returned_value',
        'total_losses_value',
        'cash_expected',
        'cash_collected',
        'cash_difference',
        'status',
        'server_notes',
        'manager_notes',
    ];

    protected $casts = [
        'session_start' => 'datetime',
        'session_end' => 'datetime',
        'total_delegated_value' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'total_returned_value' => 'decimal:2',
        'total_losses_value' => 'decimal:2',
        'cash_expected' => 'decimal:2',
        'cash_collected' => 'decimal:2',
        'cash_difference' => 'decimal:2',
    ];

    protected $attributes = [
        'status' => 'open',
        'total_delegated_value' => 0,
        'total_sales' => 0,
        'total_returned_value' => 0,
        'total_losses_value' => 0,
        'cash_expected' => 0,
        'cash_collected' => 0,
        'cash_difference' => 0,
    ];

    // Relations
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(User::class, 'server_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }

    /**
     * Générer une référence unique
     */
    public static function generateReference(int $tenantId): string
    {
        $today = now()->format('Ymd');
        $count = self::where('tenant_id', $tenantId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return "REC-{$today}-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calculer les totaux depuis les stocks délégués
     */
    public function calculateTotals(): void
    {
        $serverStocks = ServerStock::where('tenant_id', $this->tenant_id)
            ->where('server_id', $this->server_id)
            ->whereIn('status', ['active', 'reconciling'])
            ->whereBetween('delegated_at', [$this->session_start, $this->session_end ?? now()])
            ->get();

        $this->total_delegated_value = $serverStocks->sum(fn($s) => $s->quantity_delegated * $s->unit_price);
        $this->total_sales = $serverStocks->sum('total_sales_amount');
        $this->total_returned_value = $serverStocks->sum(fn($s) => $s->quantity_returned * $s->unit_price);
        $this->total_losses_value = $serverStocks->sum(fn($s) => $s->quantity_lost * $s->unit_price);
        $this->cash_expected = $this->total_sales;
        $this->cash_difference = $this->cash_collected - $this->cash_expected;
        
        $this->save();
    }

    /**
     * Soumettre pour validation par le gérant
     */
    public function submitForValidation(float $cashCollected, ?string $notes = null): void
    {
        $this->cash_collected = $cashCollected;
        $this->server_notes = $notes;
        $this->session_end = now();
        $this->status = 'pending';
        
        $this->calculateTotals();

        // Marquer les stocks comme en réconciliation
        ServerStock::where('tenant_id', $this->tenant_id)
            ->where('server_id', $this->server_id)
            ->where('status', 'active')
            ->update(['status' => 'reconciling', 'reconciled_at' => now()]);
    }

    /**
     * Valider par le gérant
     */
    public function validate(int $managerId, ?string $notes = null): void
    {
        $this->manager_id = $managerId;
        $this->manager_notes = $notes;
        $this->status = 'validated';
        $this->save();

        // Clôturer les stocks délégués
        ServerStock::where('tenant_id', $this->tenant_id)
            ->where('server_id', $this->server_id)
            ->where('status', 'reconciling')
            ->update(['status' => 'closed', 'closed_at' => now()]);
    }

    /**
     * Contester (écart important)
     */
    public function dispute(int $managerId, string $reason): void
    {
        $this->manager_id = $managerId;
        $this->manager_notes = $reason;
        $this->status = 'disputed';
        $this->save();
    }

    /**
     * Vérifier si l'écart est acceptable (seuil configurable)
     */
    public function isAcceptableDifference(float $threshold = 1000): bool
    {
        return abs($this->cash_difference) <= $threshold;
    }

    /**
     * Scope: Sessions ouvertes d'un serveur
     */
    public function scopeOpenForServer($query, int $serverId)
    {
        return $query->where('server_id', $serverId)
                     ->where('status', 'open');
    }

    /**
     * Scope: Sessions en attente de validation
     */
    public function scopePendingValidation($query)
    {
        return $query->where('status', 'pending');
    }
}
