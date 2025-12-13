<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockRequest extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'reference',
        'from_warehouse_id',
        'to_warehouse_id',
        'requested_by',
        'approved_by',
        'transfer_id',
        'status',
        'priority',
        'needed_by_date',
        'notes',
        'rejection_reason',
        'requested_at',
        'approved_at',
        'rejected_at',
        'metadata',
    ];

    protected $casts = [
        'needed_by_date' => 'date',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockRequestItem::class);
    }

    public function submit(): void
    {
        $this->status = 'requested';
        $this->requested_at = now();
        $this->save();
    }

    public function approve(int $userId): void
    {
        $this->status = 'approved';
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->save();
    }

    public function reject(int $userId, string $reason = null): void
    {
        $this->status = 'rejected';
        $this->approved_by = $userId;
        $this->rejected_at = now();
        $this->rejection_reason = $reason;
        $this->save();
    }

    public function markTransferred(int $transferId): void
    {
        $this->status = 'transferred';
        $this->transfer_id = $transferId;
        $this->save();
    }

    public function getTotalQuantity(): int
    {
        return $this->items()->sum('quantity_requested');
    }

    public static function generateReference(int $tenantId): string
    {
        $today = now()->format('Ymd');
        $count = self::where('tenant_id', $tenantId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return "REQ-$today-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }
}
