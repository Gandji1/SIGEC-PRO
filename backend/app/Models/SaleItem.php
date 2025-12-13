<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'sale_id',
        'product_id',
        'quantity',
        'unit_price',
        'line_subtotal',
        'tax_percent',
        'tax_amount',
        'line_total',
        'unit',
        'metadata',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'line_subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function calculateTotals(): void
    {
        $this->line_subtotal = $this->quantity * $this->unit_price;
        $this->tax_amount = $this->tax_percent > 0 ? ($this->line_subtotal * $this->tax_percent) / 100 : 0;
        $this->line_total = $this->line_subtotal + $this->tax_amount;
    }
}
