<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosOrderItem extends Model
{
    protected $fillable = [
        'pos_order_id',
        'product_id',
        'quantity_ordered',
        'quantity_served',
        'unit_price',
        'unit_cost',
        'discount_percent',
        'tax_percent',
        'line_total',
        'notes',
    ];

    protected $casts = [
        'quantity_ordered' => 'integer',
        'quantity_served' => 'integer',
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function posOrder(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class);
    }

    // Alias pour compatibilité
    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function calculateLineTotal(): void
    {
        $subtotal = $this->quantity_ordered * $this->unit_price;
        $discount = $subtotal * ($this->discount_percent / 100);
        $this->line_total = $subtotal - $discount;
        $this->save();
    }

    public function isFullyServed(): bool
    {
        return $this->quantity_served >= $this->quantity_ordered;
    }

    public function getRemainingQuantity(): int
    {
        return max(0, $this->quantity_ordered - $this->quantity_served);
    }
}
