<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Export extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'format',
        'filename',
        'path',
        'url',
        'size',
        'url_expires_at',
        'from_date',
        'to_date',
        'status',
        'error_message',
    ];

    protected $casts = [
        'url_expires_at' => 'datetime',
        'from_date' => 'datetime',
        'to_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function markAsProcessing(): void
    {
        $this->status = 'processing';
        $this->save();
    }

    public function markAsCompleted(string $path, string $url, int $size): void
    {
        $this->status = 'completed';
        $this->path = $path;
        $this->url = $url;
        $this->size = $size;
        $this->url_expires_at = now()->addDays(7);
        $this->save();
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->status = 'failed';
        $this->error_message = $errorMessage;
        $this->save();
    }

    public function isUrlExpired(): bool
    {
        return $this->url_expires_at?->isPast() ?? true;
    }
}
