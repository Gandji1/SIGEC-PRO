<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $table = 'subscription_plans';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_period',
        'trial_days',
        'max_users',
        'max_warehouses',
        'max_tenants',
        'has_accounting',
        'has_exports',
        'has_api',
        'has_backup',
        'features',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean',
        'has_accounting' => 'boolean',
        'has_exports' => 'boolean',
        'has_api' => 'boolean',
        'has_backup' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    public function getMonthlyPrice(): float
    {
        if ($this->billing_period === 'yearly') {
            return $this->price / 12;
        }
        return $this->price;
    }

    public function getDisplayPrice(): string
    {
        return number_format($this->price, 2) . ' (' . $this->billing_period . ')';
    }
}
