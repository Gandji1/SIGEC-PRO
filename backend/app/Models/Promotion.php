<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Promotion extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'type',
        'value',
        'min_purchase',
        'max_discount',
        'usage_limit',
        'usage_count',
        'applicable_products',
        'applicable_categories',
        'start_date',
        'end_date',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'applicable_products' => 'array',
        'applicable_categories' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isValid(): bool
    {
        $now = Carbon::now();
        return $this->is_active 
            && $now->gte($this->start_date) 
            && $now->lte($this->end_date)
            && ($this->usage_limit === null || $this->usage_count < $this->usage_limit);
    }

    public function isApplicableToProduct(int $productId): bool
    {
        if (empty($this->applicable_products)) {
            return true; // Applicable à tous
        }
        return in_array($productId, $this->applicable_products);
    }

    public function calculateDiscount(float $subtotal, array $productIds = []): float
    {
        if (!$this->isValid()) {
            return 0;
        }

        if ($this->min_purchase && $subtotal < $this->min_purchase) {
            return 0;
        }

        // Vérifier si au moins un produit est applicable
        if (!empty($this->applicable_products) && !empty($productIds)) {
            $hasApplicable = !empty(array_intersect($productIds, $this->applicable_products));
            if (!$hasApplicable) {
                return 0;
            }
        }

        $discount = 0;
        switch ($this->type) {
            case 'percentage':
                $discount = $subtotal * ($this->value / 100);
                break;
            case 'fixed':
                $discount = $this->value;
                break;
        }

        // Appliquer le plafond
        if ($this->max_discount && $discount > $this->max_discount) {
            $discount = $this->max_discount;
        }

        return min($discount, $subtotal);
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    public static function findByCode(int $tenantId, string $code): ?self
    {
        return self::where('tenant_id', $tenantId)
            ->where('code', strtoupper($code))
            ->where('is_active', true)
            ->first();
    }
}
