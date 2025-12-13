<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    protected $fillable = [
        'inventory_id',
        'product_id',
        'counted_qty',
        'system_qty',
        'variance',
        'variance_value',
        'notes',
    ];

    protected $casts = [
        'counted_qty' => 'integer',
        'system_qty' => 'integer',
        'variance' => 'integer',
        'variance_value' => 'decimal:2',
    ];

    protected $attributes = [
        'system_qty' => 0,
        'variance' => 0,
        'variance_value' => 0,
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function calculateVariance(): void
    {
        $this->variance = $this->counted_qty - $this->system_qty;
        $this->variance_value = $this->variance * ($this->product->purchase_price ?? 0);
        $this->save();
    }

    public function hasDiscrepancy(): bool
    {
        return $this->variance !== 0;
    }
}
