<?php

namespace App\Listeners;

use App\Events\PurchaseReceived;
use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordPurchaseAuditLog implements ShouldQueue
{
    public function handle(PurchaseReceived $event): void
    {
        $purchase = $event->purchase;

        // Create audit log
        AuditLog::create([
            'tenant_id' => $purchase->tenant_id,
            'user_id' => $purchase->user_id,
            'action' => 'purchase_received',
            'resource_type' => 'Purchase',
            'resource_id' => $purchase->id,
            'ip_address' => request()->ip(),
            'changes' => [
                'total' => $purchase->total,
                'items_count' => $purchase->items()->count(),
            ],
        ]);

        // Add stock
        foreach ($purchase->items as $item) {
            $stock = $item->product->stocks()
                ->where('warehouse', 'main')
                ->first();

            if ($stock) {
                $stock->available += $item->received_quantity ?? $item->quantity;
                $stock->save();
            }
        }

        // Update supplier if linked
        if ($purchase->supplier_id) {
            $purchase->supplier->updateTotals();
        }
    }
}
