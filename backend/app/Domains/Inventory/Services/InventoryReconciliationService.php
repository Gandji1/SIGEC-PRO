<?php

namespace App\Domains\Inventory\Services;

use App\Models\Tenant;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\AccountingEntry;
use Illuminate\Database\Eloquent\Collection;
use DB;

class InventoryReconciliationService
{
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Start physical inventory count
     */
    public function startInventoryCount(string $warehouse_id, string $reason): InventoryCount
    {
        return InventoryCount::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $warehouse_id,
            'reason' => $reason,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    /**
     * Record physical count item
     */
    public function recordCountItem(InventoryCount $count, int $product_id, int $physical_qty): InventoryCountItem
    {
        $stock = Stock::where('tenant_id', $this->tenant->id)
            ->where('product_id', $product_id)
            ->where('warehouse_id', $count->warehouse_id)
            ->first();

        if (!$stock) {
            throw new \Exception("Stock not found for product {$product_id}");
        }

        $variance = $physical_qty - $stock->quantity;

        return InventoryCountItem::create([
            'inventory_count_id' => $count->id,
            'product_id' => $product_id,
            'expected_quantity' => $stock->quantity,
            'physical_quantity' => $physical_qty,
            'variance' => $variance,
            'variance_percentage' => $stock->quantity > 0 
                ? (($variance / $stock->quantity) * 100) 
                : 0,
        ]);
    }

    /**
     * Complete inventory count and generate GL entries for variances
     */
    public function completeInventoryCount(InventoryCount $count): array
    {
        return DB::transaction(function () use ($count) {
            $count->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $items = $count->items;
            $total_variance_value = 0;
            $gl_entries = [];

            foreach ($items as $item) {
                if ($item->variance === 0) {
                    continue; // No variance, no GL entry needed
                }

                $stock = Stock::where('tenant_id', $this->tenant->id)
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $count->warehouse_id)
                    ->first();

                if (!$stock) {
                    continue;
                }

                // Calculate variance value using cost average
                $variance_value = abs($item->variance) * $stock->cost_average;
                $total_variance_value += $variance_value;

                // Create GL entries for variance
                if ($item->variance > 0) {
                    // Surplus: Debit Inventory (1300), Credit Variance/Gain (8100)
                    $gl_entries[] = [
                        'tenant_id' => $this->tenant->id,
                        'inventory_count_id' => $count->id,
                        'account' => 1300, // Inventory
                        'debit' => $variance_value,
                        'credit' => 0,
                        'description' => "Inventory surplus: {$item->product->name} (+{$item->variance})",
                    ];
                    $gl_entries[] = [
                        'tenant_id' => $this->tenant->id,
                        'inventory_count_id' => $count->id,
                        'account' => 8100, // Gain
                        'debit' => 0,
                        'credit' => $variance_value,
                        'description' => "Inventory surplus gain: {$item->product->name}",
                    ];
                } else {
                    // Shortage: Debit Loss/Variance (8200), Credit Inventory (1300)
                    $gl_entries[] = [
                        'tenant_id' => $this->tenant->id,
                        'inventory_count_id' => $count->id,
                        'account' => 8200, // Loss/Variance
                        'debit' => $variance_value,
                        'credit' => 0,
                        'description' => "Inventory shortage: {$item->product->name} ({$item->variance})",
                    ];
                    $gl_entries[] = [
                        'tenant_id' => $this->tenant->id,
                        'inventory_count_id' => $count->id,
                        'account' => 1300, // Inventory
                        'debit' => 0,
                        'credit' => $variance_value,
                        'description' => "Inventory shortage: {$item->product->name}",
                    ];
                }

                // Update stock to match physical count
                $stock->update([
                    'quantity' => $item->physical_quantity,
                    'available' => $item->physical_quantity,
                    'reserved' => 0,
                ]);

                // Record stock movement for audit
                StockMovement::create([
                    'tenant_id' => $this->tenant->id,
                    'stock_id' => $stock->id,
                    'type' => 'reconciliation',
                    'quantity' => $item->variance,
                    'reference' => "INV-{$count->id}",
                    'description' => "Physical count reconciliation: {$item->variance}",
                    'recorded_at' => now(),
                ]);
            }

            // Create accounting entries for all variances
            foreach ($gl_entries as $entry) {
                AccountingEntry::create($entry);
            }

            return [
                'count_id' => $count->id,
                'total_items' => count($items),
                'items_with_variance' => $items->where('variance', '!=', 0)->count(),
                'total_variance_value' => $total_variance_value,
                'gl_entries_created' => count($gl_entries),
                'status' => 'completed',
            ];
        });
    }

    /**
     * Get inventory count summary
     */
    public function getCountSummary(InventoryCount $count): array
    {
        $items = $count->items;
        $total_expected = $items->sum('expected_quantity');
        $total_physical = $items->sum('physical_quantity');
        $total_variance = $items->sum('variance');

        return [
            'count_id' => $count->id,
            'warehouse_id' => $count->warehouse_id,
            'status' => $count->status,
            'started_at' => $count->started_at,
            'completed_at' => $count->completed_at,
            'reason' => $count->reason,
            'total_expected_quantity' => $total_expected,
            'total_physical_quantity' => $total_physical,
            'total_variance' => $total_variance,
            'variance_percentage' => $total_expected > 0 
                ? (($total_variance / $total_expected) * 100) 
                : 0,
            'items_count' => count($items),
            'items_with_variance' => $items->where('variance', '!=', 0)->count(),
            'surplus_items' => $items->where('variance', '>', 0)->count(),
            'shortage_items' => $items->where('variance', '<', 0)->count(),
        ];
    }

    /**
     * Get variance analysis by product
     */
    public function getVarianceAnalysis(InventoryCount $count): array
    {
        return $count->items()
            ->where('variance', '!=', 0)
            ->with('product')
            ->get()
            ->map(function ($item) {
                $stock = Stock::where('tenant_id', $this->tenant->id)
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $item->inventoryCount->warehouse_id)
                    ->first();

                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'expected' => $item->expected_quantity,
                    'physical' => $item->physical_quantity,
                    'variance' => $item->variance,
                    'variance_percentage' => $item->variance_percentage,
                    'unit_cost' => $stock->cost_average ?? 0,
                    'variance_value' => abs($item->variance) * ($stock->cost_average ?? 0),
                    'type' => $item->variance > 0 ? 'surplus' : 'shortage',
                ];
            })
            ->sortByDesc('variance_value')
            ->values()
            ->all();
    }

    /**
     * Cancel inventory count (revert if not completed)
     */
    public function cancelInventoryCount(InventoryCount $count): void
    {
        if ($count->status !== 'in_progress') {
            throw new \Exception('Only in-progress counts can be cancelled');
        }

        $count->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $count->items()->delete();
    }

    /**
     * Export variance report
     */
    public function generateVarianceReport(InventoryCount $count): array
    {
        $analysis = $this->getVarianceAnalysis($count);
        $summary = $this->getCountSummary($count);

        return [
            'summary' => $summary,
            'details' => $analysis,
            'generated_at' => now(),
        ];
    }
}
