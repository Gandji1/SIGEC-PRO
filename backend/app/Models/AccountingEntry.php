<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingEntry extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'reference',
        'account_code',
        'description',
        'type',
        'amount',
        'category',
        'source',
        'source_id',
        'source_type',
        'entry_date',
        'status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'entry_date' => 'date',
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

    public function post(): void
    {
        $this->status = 'posted';
        $this->save();
    }

    public function getDebitAmount(): float
    {
        return $this->type === 'debit' ? $this->amount : 0;
    }

    public function getCreditAmount(): float
    {
        return $this->type === 'credit' ? $this->amount : 0;
    }
}
