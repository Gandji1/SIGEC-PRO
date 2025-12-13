<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transfer extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'reference',
        // Legacy string fields
        'from_warehouse',
        'to_warehouse',
        // Nouveaux champs relationnels
        'from_warehouse_id',
        'to_warehouse_id',
        'stock_request_id',
        'requested_by',
        'requested_at',
        'approved_by',
        'approved_at',
        'status',
        'total_items',
        'transferred_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'total_items' => 'decimal:2',
        'transferred_at' => 'datetime',
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

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }

    public function complete(): void
    {
        $this->status = 'completed';
        $this->transferred_at = now();
        $this->save();
    }

    public function calculateTotalItems(): void
    {
        $this->total_items = $this->items()->sum('quantity');
    }
}
