<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'warehouse_id',
        'reference',
        'mode',
        'customer_name',
        'customer_phone',
        'customer_email',
        'subtotal',
        'tax_amount',
        'total',
        'amount_paid',
        'change',
        'payment_method',
        'cost_of_goods_sold',
        'status',
        'notes',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'change' => 'decimal:2',
        'cost_of_goods_sold' => 'decimal:2',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->nullable();
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->nullable();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function complete(): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();

        // Update customer totals if linked
        if ($this->customer_id) {
            $this->customer->updateTotals();
        }
    }

    public function calculateTotals(): void
    {
        $this->subtotal = $this->items()->sum('line_subtotal');
        $this->tax_amount = $this->items()->sum('tax_amount');
        $this->total = $this->items()->sum('line_total');
        $this->change = max(0, $this->amount_paid - $this->total);
    }
}
