<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'user_id',
        'reference',
        'status',
        'started_at',
        'completed_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function start(): void
    {
        $this->status = 'in_progress';
        $this->started_at = now();
        $this->save();
    }

    public function complete(): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
    }

    public function validate(): void
    {
        $this->status = 'validated';
        $this->save();
    }

    public function getTotalVariance(): int
    {
        return $this->items()->sum('variance');
    }

    public function getTotalVarianceValue(): float
    {
        return $this->items()->sum('variance_value');
    }

    public function hasDiscrepancies(): bool
    {
        return $this->items()->where('variance', '!=', 0)->exists();
    }
}
