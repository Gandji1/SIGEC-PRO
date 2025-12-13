<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'postal_code',
        'country',
        'tax_id',
        'category',
        'credit_limit',
        'notes',
        'total_purchases',
        'total_payments',
        'status',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'total_purchases' => 'decimal:2',
        'total_payments' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function getOutstandingBalance(): float
    {
        return (float) ($this->total_purchases - $this->total_payments);
    }

    public function canBuySon(float $amount): bool
    {
        if (!$this->credit_limit) {
            return true;
        }
        
        return $this->getOutstandingBalance() + $amount <= $this->credit_limit;
    }

    public function updateTotals(): void
    {
        $this->total_purchases = $this->sales()
            ->where('status', 'completed')
            ->sum('total');

        $this->total_payments = $this->payments()
            ->sum('amount');

        $this->save();
    }
}
