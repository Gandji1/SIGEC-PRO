<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    use HasFactory, BelongsToTenant, Auditable;
    protected $fillable = [
        'tenant_id',
        'product_id',
        'warehouse_id',
        'warehouse', // Legacy
        'quantity',
        'reserved',
        'available',
        'cost_average', // CMP - Coût Moyen Pondéré
        'unit_cost',
        'last_counted_at',
        'location',
        'metadata',
        // Inventaire enrichi
        'sdu_theorique',
        'stock_physique',
        'ecart',
        'last_inventory_at',
        'last_inventory_by',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'cost_average' => 'decimal:2',
        'last_counted_at' => 'datetime',
        'last_inventory_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'quantity' => 0,
        'reserved' => 0,
        'available' => 0,
        'unit_cost' => 0,
        'cost_average' => 0,
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_id', 'product_id');
    }

    public function updateAvailableQuantity(): void
    {
        $this->available = $this->quantity - $this->reserved;
        $this->save();
    }

    public function reserve(int $quantity): bool
    {
        if ($this->available >= $quantity) {
            $this->reserved += $quantity;
            $this->updateAvailableQuantity();
            return true;
        }
        return false;
    }

    public function release(int $quantity): void
    {
        $this->reserved = max(0, $this->reserved - $quantity);
        $this->updateAvailableQuantity();
    }
}
