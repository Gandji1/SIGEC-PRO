<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'user_id',
        'category',
        'description',
        'amount',
        'date',
        'is_fixed',
        'payment_method',
        'recorded_by',
        'expense_date',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'expense_date' => 'datetime',
        'is_fixed' => 'boolean',
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

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}


