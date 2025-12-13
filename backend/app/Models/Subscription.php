<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'stripe_subscription_id',
        'status',
        'current_period_start',
        'current_period_end',
        'trial_ends_at',
        'canceled_at',
        'ended_at',
        'next_billing_amount',
        'billing_retry_count',
        'next_retry_at',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'trial_ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'ended_at' => 'datetime',
        'next_billing_amount' => 'decimal:2',
        'next_retry_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing' && $this->trial_ends_at?->isFuture();
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing']);
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    public function cancel(): void
    {
        $this->status = 'canceled';
        $this->canceled_at = now();
        $this->save();
    }

    public function suspend(): void
    {
        $this->status = 'suspended';
        $this->save();
    }

    public function reactivate(): void
    {
        $this->status = 'active';
        $this->save();
    }

    public function getDaysUntilExpiry(): int
    {
        return $this->trial_ends_at?->diffInDays(now()) ?? 0;
    }

    public function isExpiringSoon(): bool
    {
        return $this->getDaysUntilExpiry() <= 3 && $this->isTrialing();
    }
}
