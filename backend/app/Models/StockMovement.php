<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockMovement extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'product_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'quantity',
        'unit_cost',
        'type',
        'reference',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id')->nullable();
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id')->nullable();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getValue(): float
    {
        return $this->quantity * $this->unit_cost;
    }

    public static function createFromPurchase(int $product_id, int $quantity, float $unit_cost, int $to_warehouse_id, string $reference, int $user_id, int $tenant_id): self
    {
        return self::create([
            'tenant_id' => $tenant_id,
            'product_id' => $product_id,
            'from_warehouse_id' => null,
            'to_warehouse_id' => $to_warehouse_id,
            'quantity' => $quantity,
            'unit_cost' => $unit_cost,
            'type' => 'purchase',
            'reference' => $reference,
            'user_id' => $user_id,
        ]);
    }

    public static function createTransfer(int $product_id, int $quantity, int $from_warehouse_id, int $to_warehouse_id, string $reference, int $user_id, int $tenant_id): self
    {
        return self::create([
            'tenant_id' => $tenant_id,
            'product_id' => $product_id,
            'from_warehouse_id' => $from_warehouse_id,
            'to_warehouse_id' => $to_warehouse_id,
            'quantity' => $quantity,
            'unit_cost' => 0, // Use stock average cost
            'type' => 'transfer',
            'reference' => $reference,
            'user_id' => $user_id,
        ]);
    }

    public static function createFromSale(int $product_id, int $quantity, int $from_warehouse_id, string $reference, int $user_id, int $tenant_id): self
    {
        return self::create([
            'tenant_id' => $tenant_id,
            'product_id' => $product_id,
            'from_warehouse_id' => $from_warehouse_id,
            'to_warehouse_id' => null,
            'quantity' => $quantity,
            'unit_cost' => 0,
            'type' => 'sale',
            'reference' => $reference,
            'user_id' => $user_id,
        ]);
    }
}
