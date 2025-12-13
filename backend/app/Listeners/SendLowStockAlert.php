<?php

namespace App\Listeners;

use App\Events\StockLow;
use App\Models\AuditLog;
use App\Domains\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendLowStockAlert implements ShouldQueue
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function handle(StockLow $event): void
    {
        $stock = $event->stock;

        // Log the alert
        AuditLog::create([
            'tenant_id' => $stock->tenant_id,
            'user_id' => null,
            'action' => 'low_stock_alert',
            'resource_type' => 'Stock',
            'resource_id' => $stock->id,
            'ip_address' => null,
            'changes' => [
                'product_id' => $stock->product_id,
                'available_quantity' => $event->availableQuantity,
                'min_stock' => $stock->product->min_stock,
            ],
        ]);

        // Send notification to admin users
        $adminUsers = $stock->tenant->users()
            ->where('role', 'admin')
            ->get();

        foreach ($adminUsers as $user) {
            $this->notificationService->sendLowStockAlert(
                $user->email,
                $stock->product->name,
                $event->availableQuantity,
                $stock->product->min_stock
            );
        }
    }
}
