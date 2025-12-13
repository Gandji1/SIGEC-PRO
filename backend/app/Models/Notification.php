<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'priority',
        'read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read' => 'boolean',
        'read_at' => 'datetime',
    ];

    // Types de notifications
    const TYPE_ORDER_CREATED = 'order_created';
    const TYPE_ORDER_APPROVED = 'order_approved';
    const TYPE_ORDER_READY = 'order_ready';
    const TYPE_PAYMENT_PENDING = 'payment_pending';
    const TYPE_PAYMENT_VALIDATED = 'payment_validated';
    const TYPE_STOCK_LOW = 'stock_low';
    const TYPE_STOCK_REQUEST = 'stock_request';
    const TYPE_TRANSFER_PENDING = 'transfer_pending';
    const TYPE_PURCHASE_RECEIVED = 'purchase_received';
    
    // Types pour le flux fournisseur
    const TYPE_SUPPLIER_NEW_ORDER = 'supplier_new_order';
    const TYPE_SUPPLIER_ORDER_RECEIVED = 'supplier_order_received';
    const TYPE_SUPPLIER_PAYMENT_RECEIVED = 'supplier_payment_received';
    const TYPE_PURCHASE_CONFIRMED = 'purchase_confirmed';
    const TYPE_PURCHASE_SHIPPED = 'purchase_shipped';
    const TYPE_PURCHASE_DELIVERED = 'purchase_delivered';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead(): void
    {
        $this->update([
            'read' => true,
            'read_at' => now(),
        ]);
    }

    public static function notify(int $tenantId, string $type, string $title, string $message, array $data = [], ?int $userId = null, string $priority = 'normal'): self
    {
        return self::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'priority' => $priority,
        ]);
    }

    public static function notifyManagers(int $tenantId, string $type, string $title, string $message, array $data = [], string $priority = 'normal'): void
    {
        $managers = User::where('tenant_id', $tenantId)
            ->whereIn('role', ['owner', 'admin', 'gerant', 'manager'])
            ->pluck('id');

        foreach ($managers as $managerId) {
            self::notify($tenantId, $type, $title, $message, $data, $managerId, $priority);
        }
    }
}
