<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'user_id',
        'model',
        'model_id',
        'action',
        'message',
        'level',
        'type',
        'resource_type',
        'changes',
        'ip_address',
        'user_agent',
        'description',
        'details',
        'metadata',
    ];

    protected $casts = [
        'changes' => 'array',
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

    public static function log(string $action, string $resource_type, $model_id = null, array $changes = [], $description = null): void
    {
        $tenant_id = auth()->guard('sanctum')->user()?->tenant_id;
        $user_id = auth()->guard('sanctum')->id();

        self::create([
            'tenant_id' => $tenant_id,
            'user_id' => $user_id,
            'action' => $action,
            'resource_type' => $resource_type,
            'model_id' => $model_id,
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'description' => $description,
        ]);
    }
}
