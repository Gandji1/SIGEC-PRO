<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'category',
        'purchase_price',
        'selling_price',
        'margin_percent',
        'unit',
        'min_stock',
        'max_stock',
        'image',
        'barcode',
        'tax_code',
        'tax_percent',
        'is_taxable',
        'track_stock',
        'status',
        'metadata',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'margin_percent' => 'decimal:2',
        'is_taxable' => 'boolean',
        'track_stock' => 'boolean',
        'metadata' => 'array',
    ];

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function transferItems(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }

    public function calculateMargin(): void
    {
        if ($this->purchase_price > 0) {
            $this->margin_percent = (($this->selling_price - $this->purchase_price) / $this->purchase_price) * 100;
        }
    }

    public function isLowStock(): bool
    {
        return $this->stocks()->sum('available') <= $this->min_stock;
    }
}
