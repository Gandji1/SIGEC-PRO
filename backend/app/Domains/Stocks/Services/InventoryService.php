<?php

namespace App\Domains\Stocks\Services;

use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\AuditLog;
use App\Domains\Accounting\Services\AccountingService;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * InventoryService
 * 
 * Gère les inventaires physiques
 * Détecte les écarts et génère les ajustements automatiques
 */
class InventoryService
{
    private StockService $stockService;
    private AccountingService $accountingService;

    public function __construct()
    {
        $this->stockService = new StockService();
        $this->accountingService = new AccountingService();
    }

    /**
     * Crée un nouvel inventaire
     * 
     * @param int $warehouseId
     * @return Inventory
     */
    public function createInventory(int $warehouseId): Inventory
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        $userId = auth()->id();

        $reference = 'INV-' . now()->format('YmdHis');

        $inventory = Inventory::create([
            'tenant_id' => $tenantId,
            'warehouse_id' => $warehouseId,
            'user_id' => $userId,
            'reference' => $reference,
            'status' => 'draft',
        ]);

        AuditLog::log('create', 'inventory', $inventory->id, 
            ['warehouse_id' => $warehouseId],
            'Inventaire créé'
        );

        return $inventory;
    }

    /**
     * Démarre un inventaire
     * 
     * @param Inventory $inventory
     * @return Inventory
     */
    public function startInventory(Inventory $inventory): Inventory
    {
        $inventory->start();

        AuditLog::log('update', 'inventory', $inventory->id, 
            ['status' => 'in_progress'],
            'Inventaire démarré'
        );

        return $inventory;
    }

    /**
     * Ajoute un article à l'inventaire
     * 
     * @param Inventory $inventory
     * @param int $productId
     * @param int $countedQty
     * @return InventoryItem
     */
    public function addItem(Inventory $inventory, int $productId, int $countedQty): InventoryItem
    {
        $systemQty = Stock::where('tenant_id', $inventory->tenant_id)
            ->where('product_id', $productId)
            ->where('warehouse_id', $inventory->warehouse_id)
            ->value('quantity') ?? 0;

        $product = \App\Models\Product::find($productId);
        $variance = $countedQty - $systemQty;
        $varianceValue = $variance * ($product->purchase_price ?? 0);

        $item = InventoryItem::create([
            'inventory_id' => $inventory->id,
            'product_id' => $productId,
            'counted_qty' => $countedQty,
            'system_qty' => $systemQty,
            'variance' => $variance,
            'variance_value' => $varianceValue,
        ]);

        return $item;
    }

    /**
     * Finalise l'inventaire et crée les ajustements
     * 
     * @param Inventory $inventory
     * @return Inventory
     */
    public function completeInventory(Inventory $inventory): Inventory
    {
        return DB::transaction(function () use ($inventory) {
            // Marquer comme complété
            $inventory->complete();

            // Traiter chaque écart
            foreach ($inventory->items as $item) {
                if ($item->variance !== 0) {
                    $this->adjustStockFromInventory($inventory, $item);
                }
            }

            AuditLog::log('update', 'inventory', $inventory->id, 
                ['status' => 'completed', 'total_variance' => $inventory->getTotalVariance()],
                'Inventaire finalisé'
            );

            return $inventory->load('items');
        });
    }

    /**
     * Ajuste le stock basé sur l'inventaire
     * Crée un mouvement de stock et l'écriture comptable correspondante
     * 
     * @param Inventory $inventory
     * @param InventoryItem $item
     * @return void
     */
    private function adjustStockFromInventory(Inventory $inventory, InventoryItem $item): void
    {
        $stock = Stock::where('tenant_id', $inventory->tenant_id)
            ->where('product_id', $item->product_id)
            ->where('warehouse_id', $inventory->warehouse_id)
            ->first();

        if (!$stock) {
            // Créer un nouveau stock
            $stock = Stock::create([
                'tenant_id' => $inventory->tenant_id,
                'product_id' => $item->product_id,
                'warehouse_id' => $inventory->warehouse_id,
                'quantity' => $item->counted_qty,
                'cost_average' => \App\Models\Product::find($item->product_id)->purchase_price ?? 0,
            ]);
        } else {
            // Ajuster la quantité
            $stock->quantity = $item->counted_qty;
            $stock->save();
        }

        // Créer le mouvement de stock
        StockMovement::create([
            'tenant_id' => $inventory->tenant_id,
            'product_id' => $item->product_id,
            'from_warehouse_id' => null,
            'to_warehouse_id' => $inventory->warehouse_id,
            'quantity' => $item->variance,
            'unit_cost' => $stock->cost_average,
            'type' => 'adjustment',
            'reference' => $inventory->reference,
            'user_id' => $inventory->user_id,
            'notes' => $item->notes ?? 'Ajustement d\'inventaire',
        ]);

        // Générer l'écriture comptable
        $this->accountingService->generateAdjustmentEntry(
            $inventory->tenant_id,
            $item->product_id,
            $item->variance_value,
            $inventory->reference,
            'Ajustement d\'inventaire'
        );
    }

    /**
     * Valide l'inventaire (avant transmission au comptable)
     * 
     * @param Inventory $inventory
     * @return Inventory
     */
    public function validateInventory(Inventory $inventory): Inventory
    {
        $inventory->validate();

        AuditLog::log('update', 'inventory', $inventory->id, 
            ['status' => 'validated'],
            'Inventaire validé'
        );

        return $inventory;
    }

    /**
     * Obtient un résumé de l'inventaire avec écarts
     * 
     * @param Inventory $inventory
     * @return array
     */
    public function getInventorySummary(Inventory $inventory): array
    {
        $items = $inventory->items;

        return [
            'reference' => $inventory->reference,
            'warehouse_id' => $inventory->warehouse_id,
            'total_items' => $items->count(),
            'total_variance' => $inventory->getTotalVariance(),
            'total_variance_value' => $inventory->getTotalVarianceValue(),
            'items_with_variance' => $items->filter(fn ($item) => $item->variance !== 0)->count(),
            'items' => $items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'counted_qty' => $item->counted_qty,
                'system_qty' => $item->system_qty,
                'variance' => $item->variance,
                'variance_value' => $item->variance_value,
            ]),
        ];
    }

    /**
     * Exporte un inventaire en CSV
     * 
     * @param Inventory $inventory
     * @return string (CSV content)
     */
    public function exportAsCSV(Inventory $inventory): string
    {
        $csv = "SKU,Produit,Quantité Système,Quantité Comptée,Écart,Valeur Écart\n";

        foreach ($inventory->items as $item) {
            $csv .= implode(',', [
                $item->product->code,
                $item->product->name,
                $item->system_qty,
                $item->counted_qty,
                $item->variance,
                number_format($item->variance_value, 2, ',', ' '),
            ]) . "\n";
        }

        return $csv;
    }

    /**
     * Importe des items d'inventaire depuis CSV
     * Format : SKU,Quantité Comptée
     * 
     * @param Inventory $inventory
     * @param string $csvContent
     * @return array (results with count of added items)
     */
    public function importFromCSV(Inventory $inventory, string $csvContent): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $csvContent)));
        array_shift($lines); // Remove header

        $added = 0;
        $errors = [];

        foreach ($lines as $line) {
            try {
                [$sku, $countedQty] = explode(',', $line);

                $product = \App\Models\Product::where('tenant_id', $inventory->tenant_id)
                    ->where('code', trim($sku))
                    ->first();

                if (!$product) {
                    $errors[] = "SKU non trouvé : $sku";
                    continue;
                }

                $this->addItem($inventory, $product->id, (int) $countedQty);
                $added++;
            } catch (Exception $e) {
                $errors[] = "Erreur ligne : $line - " . $e->getMessage();
            }
        }

        return [
            'added' => $added,
            'errors' => $errors,
        ];
    }
}
