<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'invoice_number',
        'subscription_id',
        'status',
        'subtotal',
        'tax',
        'total',
        'amount_paid',
        'issued_at',
        'due_at',
        'paid_at',
        'payment_method',
        'description',
        'items',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'items' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class)->nullable();
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid' || $this->amount_paid >= $this->total;
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getOutstandingAmount(): float
    {
        return max(0, $this->total - $this->amount_paid);
    }

    public function isOverdue(): bool
    {
        return !$this->isPaid() && $this->due_at?->isPast();
    }

    public function markAsPaid(): void
    {
        $this->status = 'paid';
        $this->paid_at = now();
        $this->amount_paid = $this->total;
        $this->save();
    }

    public function markAsFailed(): void
    {
        $this->status = 'failed';
        $this->save();
    }
}
