<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductReturn extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'reference',
        'sale_id',
        'pos_order_id',
        'customer_id',
        'status',
        'return_type',
        'total_amount',
        'refund_amount',
        'reason',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function posOrder(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductReturnItem::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function generateReference(int $tenantId): string
    {
        $count = self::where('tenant_id', $tenantId)->count() + 1;
        return 'RET-' . date('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function calculateTotals(): void
    {
        $this->total_amount = $this->items->sum('line_total');
        $this->refund_amount = $this->total_amount;
        $this->save();
    }

    public function approve(int $userId): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }

    public function process(): void
    {
        if ($this->status !== 'approved') {
            throw new \Exception('Le retour doit être approuvé avant traitement');
        }

        // Remettre en stock les produits en bon état
        foreach ($this->items as $item) {
            if ($item->restock && $item->condition === 'good') {
                $stock = Stock::where('tenant_id', $this->tenant_id)
                    ->where('product_id', $item->product_id)
                    ->whereHas('warehouse', fn($q) => $q->where('type', 'detail'))
                    ->first();

                if ($stock) {
                    $stock->increment('quantity', $item->quantity);
                    
                    StockMovement::create([
                        'tenant_id' => $this->tenant_id,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $stock->warehouse_id,
                        'type' => 'return',
                        'quantity' => $item->quantity,
                        'reference' => $this->reference,
                        'notes' => 'Retour produit: ' . $this->reason,
                    ]);
                }
            }
        }

        $this->update(['status' => 'processed']);
    }
}
