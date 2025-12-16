<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mouvement de stock serveur (Option B)
 * Historique détaillé des opérations sur le stock délégué
 */
class ServerStockMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'server_stock_id',
        'server_id',
        'product_id',
        'pos_order_id',
        'performed_by',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'unit_price',
        'total_amount',
        'reference',
        'notes',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    // Relations
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function serverStock(): BelongsTo
    {
        return $this->belongsTo(ServerStock::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(User::class, 'server_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function posOrder(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class);
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Libellé du type de mouvement
     */
    public function getTypeLabel(): string
    {
        return match($this->type) {
            'delegation' => 'Délégation',
            'sale' => 'Vente',
            'return' => 'Retour',
            'loss' => 'Perte',
            'adjustment' => 'Ajustement',
            'transfer' => 'Transfert',
            default => $this->type,
        };
    }

    /**
     * Scope: Mouvements d'un serveur
     */
    public function scopeForServer($query, int $serverId)
    {
        return $query->where('server_id', $serverId);
    }

    /**
     * Scope: Mouvements par type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
