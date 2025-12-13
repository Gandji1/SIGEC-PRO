<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\AccountingEntry;
use App\Models\Sale;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;

class StockAutomationService
{
    /**
     * Enregistrer un mouvement de stock avec l'écriture comptable automatique
     */
    public static function recordMovement(
        $tenantId,
        $productId,
        $quantity,
        $type, // purchase, sale, transfer, adjustment
        $fromWarehouse = null,
        $toWarehouse = null,
        $reference = null,
        $userId = null,
        $unitCost = 0
    ) {
        return DB::transaction(function () use (
            $tenantId, $productId, $quantity, $type,
            $fromWarehouse, $toWarehouse, $reference, $userId, $unitCost
        ) {
            // 1. Créer le mouvement de stock
            $movement = StockMovement::create([
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'from_warehouse_id' => $fromWarehouse,
                'to_warehouse_id' => $toWarehouse,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'type' => $type,
                'reference' => $reference,
                'user_id' => $userId,
            ]);

            // 2. Mettre à jour les stocks
            if ($type === 'purchase' && $toWarehouse) {
                self::updateStockOnPurchase($tenantId, $productId, $toWarehouse, $quantity, $unitCost);
            } elseif ($type === 'sale' && $fromWarehouse) {
                self::updateStockOnSale($tenantId, $productId, $fromWarehouse, $quantity);
            } elseif ($type === 'transfer' && $fromWarehouse && $toWarehouse) {
                self::updateStockOnTransfer($tenantId, $productId, $fromWarehouse, $toWarehouse, $quantity);
            } elseif ($type === 'adjustment') {
                self::updateStockOnAdjustment($tenantId, $productId, $toWarehouse, $quantity, $unitCost);
            }

            // 3. Créer les écritures comptables automatiquement
            self::recordAccountingEntry($tenantId, $type, $reference, $quantity * $unitCost);

            return $movement;
        });
    }

    /**
     * Mettre à jour le stock après achat
     */
    private static function updateStockOnPurchase($tenantId, $productId, $warehouseId, $quantity, $unitCost)
    {
        $stock = Stock::firstOrCreate(
            ['tenant_id' => $tenantId, 'product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0, 'reserved' => 0, 'available' => 0, 'cost_average' => 0]
        );

        // CMP: Recalculer le coût moyen pondéré
        $totalQty = $stock->quantity + $quantity;
        $newCMP = ($stock->quantity * $stock->cost_average + $quantity * $unitCost) / $totalQty;

        $stock->update([
            'quantity' => $totalQty,
            'available' => $totalQty - $stock->reserved,
            'cost_average' => $newCMP,
            'unit_cost' => $unitCost,
        ]);
    }

    /**
     * Mettre à jour le stock après vente
     */
    private static function updateStockOnSale($tenantId, $productId, $warehouseId, $quantity)
    {
        $stock = Stock::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->firstOrFail();

        $stock->update([
            'quantity' => max(0, $stock->quantity - $quantity),
            'available' => max(0, $stock->available - $quantity),
        ]);
    }

    /**
     * Mettre à jour le stock après transfert
     */
    private static function updateStockOnTransfer($tenantId, $productId, $fromWarehouse, $toWarehouse, $quantity)
    {
        // Décrémenter le stock source
        $fromStock = Stock::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $fromWarehouse)
            ->firstOrFail();

        $fromStock->update([
            'quantity' => max(0, $fromStock->quantity - $quantity),
            'available' => max(0, $fromStock->available - $quantity),
        ]);

        // Incrémenter le stock destination
        $toStock = Stock::firstOrCreate(
            ['tenant_id' => $tenantId, 'product_id' => $productId, 'warehouse_id' => $toWarehouse],
            ['quantity' => 0, 'reserved' => 0, 'available' => 0, 'cost_average' => $fromStock->cost_average]
        );

        $toStock->update([
            'quantity' => $toStock->quantity + $quantity,
            'available' => $toStock->available + $quantity,
        ]);
    }

    /**
     * Mettre à jour le stock sur ajustement
     */
    private static function updateStockOnAdjustment($tenantId, $productId, $warehouseId, $quantity, $unitCost)
    {
        $stock = Stock::firstOrCreate(
            ['tenant_id' => $tenantId, 'product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0, 'reserved' => 0, 'available' => 0, 'cost_average' => 0]
        );

        $stock->update([
            'quantity' => max(0, $stock->quantity + $quantity),
            'available' => max(0, $stock->available + $quantity),
            'unit_cost' => $unitCost,
        ]);
    }

    /**
     * Enregistrer l'écriture comptable automatiquement
     */
    private static function recordAccountingEntry($tenantId, $type, $reference, $amount)
    {
        $entries = [];

        switch ($type) {
            case 'purchase':
                // Débit: Stocks / Crédit: Fournisseurs
                $entries = [
                    ['account' => '31', 'debit' => $amount, 'credit' => 0], // Stocks
                    ['account' => '40', 'debit' => 0, 'credit' => $amount], // Fournisseurs
                ];
                break;
            case 'sale':
                // Débit: Banque/Caisse / Crédit: Ventes
                $entries = [
                    ['account' => '5', 'debit' => $amount, 'credit' => 0], // Caisse/Banque
                    ['account' => '70', 'debit' => 0, 'credit' => $amount], // Ventes
                ];
                break;
            case 'transfer':
                // Intra-company: aucune écriture comptable
                break;
        }

        foreach ($entries as $entry) {
            AccountingEntry::create([
                'tenant_id' => $tenantId,
                'account_code' => $entry['account'],
                'debit' => $entry['debit'],
                'credit' => $entry['credit'],
                'description' => "Mouvement automatique: {$type} - {$reference}",
                'reference' => $reference,
                'recorded_at' => now(),
            ]);
        }
    }
}
