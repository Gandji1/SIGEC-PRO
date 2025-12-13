<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
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
        'bank_details',
        'contact_person',
        'notes',
        'total_purchases',
        'total_paid',
        'status',
        'user_id',
        'has_portal_access',
        'portal_email',
    ];

    protected $casts = [
        'total_purchases' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'bank_details' => 'array',
        'has_portal_access' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function getOutstandingBalance(): float
    {
        return (float) ($this->total_purchases - $this->total_paid);
    }

    public function updateTotals(): void
    {
        $this->total_purchases = $this->purchases()
            ->where('status', 'received')
            ->sum('total');

        $this->total_paid = $this->payments()
            ->sum('amount');

        $this->save();
    }
}
