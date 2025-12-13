<?php

namespace App\Domains\Transfers\Services;

use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\AuditLog;
use App\Domains\Accounting\Services\AccountingService;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * TransferService
 * 
 * Gère les transferts entre magasins (gros → détail → POS)
 * Automatise les validations, les mouvements de stock et les écritures comptables
 */
class TransferService
{
    private AccountingService $accountingService;

    public function __construct()
    {
        $this->accountingService = new AccountingService();
    }

    /**
     * Crée une demande de transfert
     * 
     * @param array $data
     * @return Transfer
     */
    public function requestTransfer(array $data): Transfer
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        $userId = auth()->id();

        return DB::transaction(function () use ($data, $tenantId, $userId) {
            $transfer = Transfer::create([
                'tenant_id' => $tenantId,
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'status' => 'pending',
                'requested_by' => $userId,
                'requested_at' => now(),
            ]);

            // Ajouter les items
            foreach ($data['items'] as $item) {
                TransferItem::create([
                    'transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $this->getProductCost($item['product_id']),
                ]);
            }

            AuditLog::log('create', 'transfer', $transfer->id, $data, 'Demande de transfert créée');

            return $transfer->load('items');
        });
    }

    /**
     * Valide une demande de transfert
     * Vérifie que le stock source est suffisant
     * 
     * @param Transfer $transfer
     * @return bool
     */
    public function validateTransfer(Transfer $transfer): bool
    {
        foreach ($transfer->items as $item) {
            $stock = Stock::where('tenant_id', $transfer->tenant_id)
                ->where('product_id', $item->product_id)
                ->where('warehouse_id', $transfer->from_warehouse_id)
                ->first();

            if (!$stock || $stock->available < $item->quantity) {
                throw new Exception("Stock insuffisant pour le produit {$item->product_id}");
            }
        }

        return true;
    }

    /**
     * Approuve et exécute un transfert
     * 
     * @param Transfer $transfer
     * @return Transfer
     */
    public function approveAndExecuteTransfer(Transfer $transfer): Transfer
    {
        return DB::transaction(function () use ($transfer) {
            // Valider le stock
            $this->validateTransfer($transfer);

            // Exécuter le transfert
            foreach ($transfer->items as $item) {
                // Déduction du stock source
                $fromStock = Stock::where('tenant_id', $transfer->tenant_id)
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $transfer->from_warehouse_id)
                    ->first();

                $fromStock->quantity -= $item->quantity;
                $fromStock->updateAvailableQuantity();
                $fromStock->save();

                // Augmentation du stock destination
                $toStock = Stock::updateOrCreate(
                    [
                        'tenant_id' => $transfer->tenant_id,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $transfer->to_warehouse_id,
                    ],
                    [
                        'quantity' => Stock::where('tenant_id', $transfer->tenant_id)
                            ->where('product_id', $item->product_id)
                            ->where('warehouse_id', $transfer->to_warehouse_id)
                            ->value('quantity') + $item->quantity,
                        'cost_average' => $item->unit_cost,
                    ]
                );
                $toStock->updateAvailableQuantity();

                // Créer le mouvement de stock
                StockMovement::create([
                    'tenant_id' => $transfer->tenant_id,
                    'product_id' => $item->product_id,
                    'from_warehouse_id' => $transfer->from_warehouse_id,
                    'to_warehouse_id' => $transfer->to_warehouse_id,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'type' => 'transfer',
                    'reference' => $transfer->reference ?? "TRANSFER-{$transfer->id}",
                    'user_id' => auth()->id(),
                ]);

                // Générer les écritures comptables
                $this->accountingService->generateTransferEntry(
                    $transfer->tenant_id,
                    $item->product_id,
                    $transfer->from_warehouse_id,
                    $transfer->to_warehouse_id,
                    $item->quantity * $item->unit_cost,
                    "TRANSFER-{$transfer->id}"
                );
            }

            // Marquer le transfert comme exécuté
            $transfer->status = 'approved';
            $transfer->approved_by = auth()->id();
            $transfer->approved_at = now();
            $transfer->executed_at = now();
            $transfer->save();

            AuditLog::log('update', 'transfer', $transfer->id, 
                ['status' => 'approved', 'executed_at' => now()],
                'Transfert approuvé et exécuté'
            );

            return $transfer->load('items');
        });
    }

    /**
     * Annule un transfert
     * 
     * @param Transfer $transfer
     * @return Transfer
     */
    public function cancelTransfer(Transfer $transfer): Transfer
    {
        if (in_array($transfer->status, ['approved', 'executed'])) {
            throw new Exception("Impossible d'annuler un transfert déjà exécuté");
        }

        $transfer->status = 'cancelled';
        $transfer->save();

        AuditLog::log('update', 'transfer', $transfer->id, 
            ['status' => 'cancelled'],
            'Transfert annulé'
        );

        return $transfer;
    }

    /**
     * Obtient le coût unitaire d'un produit
     * 
     * @param int $productId
     * @return float
     */
    private function getProductCost(int $productId): float
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        
        // Chercher le stock le plus récent pour obtenir le CMP
        return Stock::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->value('cost_average') ?? 0;
    }

    /**
     * Transfère automatiquement si le stock est bas dans la destination
     * Déclenche un transfert automatique gros → détail → POS selon les besoins
     * 
     * @param int $productId
     * @param int $warehouseId
     * @param int $minThreshold
     * @return Transfer|null
     */
    public function autoTransferIfNeeded(int $productId, int $warehouseId, int $minThreshold = 10): ?Transfer
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;

        $stock = Stock::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($stock && $stock->quantity <= $minThreshold) {
            // Trouver un warehouse source (priorité : gros → détail)
            $warehouse = Warehouse::find($warehouseId);

            if ($warehouse->isPos()) {
                // Chercher détail
                $sourceWarehouse = Warehouse::where('tenant_id', $tenantId)
                    ->where('type', 'detail')
                    ->first();
            } elseif ($warehouse->isDetail()) {
                // Chercher gros
                $sourceWarehouse = Warehouse::where('tenant_id', $tenantId)
                    ->where('type', 'gros')
                    ->first();
            } else {
                return null;
            }

            if ($sourceWarehouse) {
                $sourceStock = Stock::where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $sourceWarehouse->id)
                    ->first();

                if ($sourceStock && $sourceStock->available >= 10) {
                    return $this->requestTransfer([
                        'from_warehouse_id' => $sourceWarehouse->id,
                        'to_warehouse_id' => $warehouseId,
                        'items' => [
                            [
                                'product_id' => $productId,
                                'quantity' => 10,
                            ],
                        ],
                    ]);
                }
            }
        }

        return null;
    }
}
