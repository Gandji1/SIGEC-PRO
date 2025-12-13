<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    use BelongsToTenant;
    protected $table = 'price_history';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'price_type',
        'old_price',
        'new_price',
        'reason',
        'changed_by',
    ];

    protected $casts = [
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public static function recordChange(int $tenantId, int $productId, string $priceType, float $oldPrice, float $newPrice, ?string $reason = null, ?int $userId = null): ?self
    {
        if ($oldPrice == $newPrice) {
            return null;
        }

        return self::create([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'price_type' => $priceType,
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'reason' => $reason,
            'changed_by' => $userId,
        ]);
    }

    public function getVariationPercent(): float
    {
        if ($this->old_price == 0) {
            return 100;
        }
        return round((($this->new_price - $this->old_price) / $this->old_price) * 100, 2);
    }
}
