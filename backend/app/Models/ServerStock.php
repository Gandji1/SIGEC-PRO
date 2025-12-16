<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stock délégué à un serveur (Option B)
 * 
 * Le gérant délègue du stock à un serveur qui le vend
 * et fait le point à la fin de son service.
 */
class ServerStock extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'server_id',
        'product_id',
        'pos_id',
        'delegated_by',
        'quantity_delegated',
        'quantity_sold',
        'quantity_remaining',
        'quantity_returned',
        'quantity_lost',
        'unit_price',
        'unit_cost',
        'total_sales_amount',
        'amount_collected',
        'status',
        'delegated_at',
        'reconciled_at',
        'closed_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_sales_amount' => 'decimal:2',
        'amount_collected' => 'decimal:2',
        'delegated_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'closed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'quantity_delegated' => 0,
        'quantity_sold' => 0,
        'quantity_remaining' => 0,
        'quantity_returned' => 0,
        'quantity_lost' => 0,
        'status' => 'active',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }

    public function delegatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ServerStockMovement::class);
    }

    // Méthodes métier

    /**
     * Vérifier si le stock est actif
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Quantité disponible pour la vente
     */
    public function getAvailableQuantity(): int
    {
        return $this->quantity_remaining;
    }

    /**
     * Enregistrer une vente depuis ce stock
     */
    public function recordSale(int $quantity, float $unitPrice, ?int $posOrderId = null): bool
    {
        if ($this->quantity_remaining < $quantity) {
            return false;
        }

        $previousQty = $this->quantity_remaining;
        
        $this->quantity_sold += $quantity;
        $this->quantity_remaining -= $quantity;
        $this->total_sales_amount += ($quantity * $unitPrice);
        $this->save();

        // Enregistrer le mouvement
        ServerStockMovement::create([
            'tenant_id' => $this->tenant_id,
            'server_stock_id' => $this->id,
            'server_id' => $this->server_id,
            'product_id' => $this->product_id,
            'pos_order_id' => $posOrderId,
            'performed_by' => auth()->id() ?? $this->server_id,
            'type' => 'sale',
            'quantity' => -$quantity,
            'quantity_before' => $previousQty,
            'quantity_after' => $this->quantity_remaining,
            'unit_price' => $unitPrice,
            'total_amount' => $quantity * $unitPrice,
            'reference' => $posOrderId ? "POS-ORDER-{$posOrderId}" : null,
        ]);

        return true;
    }

    /**
     * Retourner du stock au gérant
     */
    public function returnStock(int $quantity, ?string $notes = null): bool
    {
        if ($this->quantity_remaining < $quantity) {
            return false;
        }

        $previousQty = $this->quantity_remaining;
        
        $this->quantity_returned += $quantity;
        $this->quantity_remaining -= $quantity;
        $this->save();

        ServerStockMovement::create([
            'tenant_id' => $this->tenant_id,
            'server_stock_id' => $this->id,
            'server_id' => $this->server_id,
            'product_id' => $this->product_id,
            'performed_by' => auth()->id(),
            'type' => 'return',
            'quantity' => -$quantity,
            'quantity_before' => $previousQty,
            'quantity_after' => $this->quantity_remaining,
            'unit_price' => $this->unit_price,
            'total_amount' => $quantity * $this->unit_price,
            'notes' => $notes,
        ]);

        return true;
    }

    /**
     * Déclarer une perte
     */
    public function declareLoss(int $quantity, string $reason): bool
    {
        if ($this->quantity_remaining < $quantity) {
            return false;
        }

        $previousQty = $this->quantity_remaining;
        
        $this->quantity_lost += $quantity;
        $this->quantity_remaining -= $quantity;
        $this->save();

        ServerStockMovement::create([
            'tenant_id' => $this->tenant_id,
            'server_stock_id' => $this->id,
            'server_id' => $this->server_id,
            'product_id' => $this->product_id,
            'performed_by' => auth()->id(),
            'type' => 'loss',
            'quantity' => -$quantity,
            'quantity_before' => $previousQty,
            'quantity_after' => $this->quantity_remaining,
            'unit_price' => $this->unit_price,
            'total_amount' => $quantity * $this->unit_price,
            'notes' => $reason,
        ]);

        return true;
    }

    /**
     * Calculer le montant attendu (ventes)
     */
    public function getExpectedAmount(): float
    {
        return (float) ($this->quantity_sold * $this->unit_price);
    }

    /**
     * Calculer l'écart de caisse
     */
    public function getCashDifference(): float
    {
        return (float) ($this->amount_collected - $this->getExpectedAmount());
    }

    /**
     * Calculer le bénéfice brut
     */
    public function getGrossProfit(): float
    {
        return (float) (($this->unit_price - $this->unit_cost) * $this->quantity_sold);
    }

    /**
     * Clôturer le stock délégué
     */
    public function close(): void
    {
        $this->status = 'closed';
        $this->closed_at = now();
        $this->save();
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

        return "SS-{$today}-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Scope: Stock actif d'un serveur
     */
    public function scopeActiveForServer($query, int $serverId)
    {
        return $query->where('server_id', $serverId)
                     ->where('status', 'active');
    }

    /**
     * Scope: Stock actif pour un produit
     */
    public function scopeActiveForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId)
                     ->where('status', 'active');
    }
}
