<?php

namespace App\Services;

use App\Models\LowStockAlert;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;

class StockAlertService
{
    /**
     * Check all products and create alerts for low stock
     */
    public function checkAndCreateAlerts($tenantId)
    {
        $products = Product::where('tenant_id', $tenantId)
            ->where('track_stock', true)
            ->get();

        $alertsCreated = 0;

        foreach ($products as $product) {
            // Get warehouse stock info
            $warehouses = Warehouse::where('tenant_id', $tenantId)->get();

            foreach ($warehouses as $warehouse) {
                $stock = Stock::where('product_id', $product->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->first();

                if (!$stock) {
                    continue;
                }

                $currentQuantity = $stock->available_quantity ?? 0;
                
                // Check if alert already exists and is active
                $existingAlert = LowStockAlert::where('product_id', $product->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('status', 'active')
                    ->first();

                // Get threshold from product settings or use default
                $threshold = $product->low_stock_threshold ?? 5;

                if ($currentQuantity < $threshold) {
                    if (!$existingAlert) {
                        LowStockAlert::create([
                            'tenant_id' => $tenantId,
                            'product_id' => $product->id,
                            'warehouse_id' => $warehouse->id,
                            'current_quantity' => $currentQuantity,
                            'threshold_quantity' => $threshold,
                            'reorder_quantity' => $product->reorder_quantity ?? ($threshold * 2),
                            'status' => 'active',
                            'notes' => "Stock de {$product->name} en dessous du seuil dans {$warehouse->name}",
                        ]);
                        $alertsCreated++;
                    }
                } else {
                    // Resolve alert if stock is back above threshold
                    if ($existingAlert) {
                        $existingAlert->update([
                            'status' => 'resolved',
                            'resolved_at' => now(),
                            'notes' => "Stock restauré au-dessus du seuil",
                        ]);
                    }
                }
            }
        }

        return $alertsCreated;
    }

    /**
     * Create alert for specific product and warehouse
     */
    public function createAlert($tenantId, $productId, $warehouseId = null)
    {
        $product = Product::where('id', $productId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$product) {
            return null;
        }

        $threshold = $product->low_stock_threshold ?? 5;

        if ($warehouseId) {
            $stock = Stock::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->first();

            $currentQuantity = $stock->available_quantity ?? 0;

            $existingAlert = LowStockAlert::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('status', 'active')
                ->first();

            if (!$existingAlert && $currentQuantity < $threshold) {
                return LowStockAlert::create([
                    'tenant_id' => $tenantId,
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'current_quantity' => $currentQuantity,
                    'threshold_quantity' => $threshold,
                    'reorder_quantity' => $product->reorder_quantity ?? ($threshold * 2),
                    'status' => 'active',
                ]);
            }
        } else {
            // Check all warehouses
            $warehouses = Warehouse::where('tenant_id', $tenantId)->get();

            foreach ($warehouses as $warehouse) {
                $this->createAlert($tenantId, $productId, $warehouse->id);
            }
        }

        return null;
    }

    /**
     * Resolve alert
     */
    public function resolveAlert($alertId, $userId, $notes = null)
    {
        $alert = LowStockAlert::find($alertId);

        if ($alert) {
            $alert->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $userId,
                'notes' => $notes ?? $alert->notes,
            ]);
        }

        return $alert;
    }

    /**
     * Get low stock summary
     */
    public function getSummary($tenantId)
    {
        $activeAlerts = LowStockAlert::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        $resolvedToday = LowStockAlert::where('tenant_id', $tenantId)
            ->where('status', 'resolved')
            ->whereDate('resolved_at', today())
            ->count();

        $ignoredCount = LowStockAlert::where('tenant_id', $tenantId)
            ->where('status', 'ignored')
            ->count();

        return [
            'active_alerts' => $activeAlerts,
            'resolved_today' => $resolvedToday,
            'ignored' => $ignoredCount,
            'total' => $activeAlerts + $resolvedToday + $ignoredCount,
        ];
    }
}
