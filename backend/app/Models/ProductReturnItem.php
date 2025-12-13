<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReturnItem extends Model
{
    protected $fillable = [
        'product_return_id',
        'product_id',
        'quantity',
        'unit_price',
        'line_total',
        'condition',
        'restock',
        'notes',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'restock' => 'boolean',
    ];

    public function productReturn(): BelongsTo
    {
        return $this->belongsTo(ProductReturn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted()
    {
        static::saving(function ($item) {
            $item->line_total = $item->quantity * $item->unit_price;
        });
    }
}
