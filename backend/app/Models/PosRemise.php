<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosRemise extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'pos_order_id',
        'warehouse_id',
        'served_by',
        'reference',
        'status',
        'items',
        'notes',
    ];

    protected $casts = [
        'items' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function posOrder(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function servedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    public function complete(): void
    {
        $this->status = 'completed';
        $this->save();
    }

    public function cancel(): void
    {
        $this->status = 'cancelled';
        $this->save();
    }

    public static function generateReference(int $tenantId): string
    {
        $today = now()->format('Ymd');
        $count = self::where('tenant_id', $tenantId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return "REM-$today-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }
}
