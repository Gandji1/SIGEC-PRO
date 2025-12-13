<?php

namespace App\Listeners;

use App\Events\SaleCompleted;
use App\Models\AuditLog;
use App\Models\AccountingEntry;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordSaleAuditLog implements ShouldQueue
{
    public function handle(SaleCompleted $event): void
    {
        $sale = $event->sale;

        // Create audit log
        AuditLog::create([
            'tenant_id' => $sale->tenant_id,
            'user_id' => $sale->user_id,
            'action' => 'sale_completed',
            'resource_type' => 'Sale',
            'resource_id' => $sale->id,
            'ip_address' => request()->ip(),
            'changes' => [
                'total' => $sale->total,
                'items_count' => $sale->items()->count(),
            ],
        ]);

        // Deduct stock
        foreach ($sale->items as $item) {
            $stock = $item->product->stocks()
                ->where('warehouse', 'main')
                ->first();

            if ($stock) {
                $stock->available -= $item->quantity;
                $stock->save();
            }
        }

        // Update customer if linked
        if ($sale->customer_id) {
            $sale->customer->updateTotals();
        }
    }
}
