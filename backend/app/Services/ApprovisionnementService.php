<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Warehouse;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosRemise;
use App\Models\AuditLog;
use App\Models\AccountingEntry;
use App\Domains\Accounting\Services\AccountingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Exception;

class ApprovisionnementService
{
    protected AccountingService $accountingService;
    protected int $tenantId;
    protected int $userId;

    public function __construct()
    {
        $this->accountingService = new AccountingService();
    }

    protected function init(): void
    {
        $user = auth()->guard('sanctum')->user();
        $this->tenantId = $user->tenant_id;
        $this->userId = $user->id;
    }

    // ========================================
    // MAGASIN GROS - Achats et Receptions
    // ========================================

    public function createPurchaseOrder(array $data): Purchase
    {
        $this->init();

        return DB::transaction(function () use ($data) {
            // FLUX SIMPLIFIÉ: Gérant crée → IMMÉDIATEMENT visible par fournisseur
            // Si un supplier_id est fourni, la commande est directement soumise au fournisseur
            $hasSupplier = !empty($data['supplier_id']);
            $initialStatus = $hasSupplier ? Purchase::STATUS_SUBMITTED : 'draft';
            
            $purchase = Purchase::create([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'created_by_user_id' => $this->userId,
                'warehouse_id' => $data['warehouse_id'] ?? $this->getGrosWarehouseId(),
                'supplier_id' => $data['supplier_id'] ?? null,
                'reference' => $this->generatePurchaseReference(),
                'supplier_name' => $data['supplier_name'] ?? null,
                'supplier_phone' => $data['supplier_phone'] ?? null,
                'supplier_email' => $data['supplier_email'] ?? null,
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $initialStatus,
                'submitted_at' => $hasSupplier ? now() : null,
            ]);

            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                $qty = (int)($item['qty_ordered'] ?? $item['quantity'] ?? 1);
                // Accepter plusieurs noms de champs pour le prix
                $price = (float)($item['expected_unit_cost'] ?? $item['unit_price'] ?? $item['unit_cost'] ?? 0);
                
                // Si prix = 0, essayer de récupérer le prix d'achat du produit
                if ($price <= 0) {
                    $product = \App\Models\Product::find($item['product_id']);
                    $price = (float)($product->purchase_price ?? $product->cost_price ?? $product->price ?? 0);
                }
                
                $subtotal = $qty * $price;
                $totalAmount += $subtotal;
                
                PurchaseItem::create([
                    'tenant_id' => $this->tenantId,
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity_ordered' => $qty,
                    'unit_price' => $price,
                    'line_subtotal' => $subtotal,
                    'line_total' => $subtotal,
                ]);
            }
            
            // Mettre à jour le total directement
            $purchase->subtotal = $totalAmount;
            $purchase->total = $totalAmount;
            $purchase->save();

            $purchase->calculateTotals();

            AuditLog::create([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'action' => $hasSupplier ? 'create_and_submit' : 'create',
                'model_type' => 'purchase',
                'model_id' => $purchase->id,
                'payload' => $data,
                'description' => $hasSupplier ? 'Commande créée et envoyée au fournisseur' : 'Commande achat creee',
                'ip_address' => request()->ip(),
            ]);

            // Notifier le fournisseur si la commande est soumise
            if ($hasSupplier) {
                try {
                    $notificationService = app(\App\Services\SupplierNotificationService::class);
                    $notificationService->notifySupplierNewOrder($purchase->fresh()->load(['supplier', 'tenant', 'items.product']));
                } catch (\Exception $e) {
                    \Log::warning('Notification fournisseur échouée: ' . $e->getMessage());
                }
            }

            return $purchase->load('items.product');
        });
    }

    public function submitPurchaseOrder(int $purchaseId): Purchase
    {
        $this->init();
        $purchase = Purchase::where('tenant_id', $this->tenantId)->findOrFail($purchaseId);

        if ($purchase->status !== 'draft') {
            throw new Exception('Seules les commandes en brouillon peuvent etre soumises');
        }

        $purchase->status = 'ordered';
        $purchase->save();

        AuditLog::create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'action' => 'submit',
            'model_type' => 'purchase',
            'model_id' => $purchase->id,
            'payload' => ['status' => 'ordered'],
            'description' => 'Commande soumise',
            'ip_address' => request()->ip(),
        ]);

        return $purchase;
    }

    public function receivePurchase(int $purchaseId, array $receivedItems): Purchase
    {
        $this->init();

        return DB::transaction(function () use ($purchaseId, $receivedItems) {
            $purchase = Purchase::where('tenant_id', $this->tenantId)
                ->with('items')
                ->findOrFail($purchaseId);

            // Statuts valides pour réception: submitted (chez fournisseur), confirmed, shipped, delivered
            if (!in_array($purchase->status, ['submitted', 'ordered', 'confirmed', 'shipped', 'delivered', 'partial'])) {
                throw new Exception('Cette commande ne peut pas etre receptionnee. Statut actuel: ' . $purchase->status);
            }

            $warehouseId = $purchase->warehouse_id ?? $this->getGrosWarehouseId();

            foreach ($receivedItems as $itemData) {
                $purchaseItem = $purchase->items->find($itemData['purchase_item_id']);
                if (!$purchaseItem) continue;

                $qtyReceived = $itemData['qty_received'];
                $unitCost = $itemData['unit_cost'] ?? $purchaseItem->unit_price;
                $salePrice = $itemData['sale_price'] ?? null;

                // Mettre a jour l'item
                $purchaseItem->quantity_received = ($purchaseItem->quantity_received ?? 0) + $qtyReceived;
                $purchaseItem->unit_price = $unitCost;
                $purchaseItem->received_at = now();
                $purchaseItem->save();

                // Mettre a jour le stock avec CMP
                $this->updateStockWithCMP(
                    $purchaseItem->product_id,
                    $qtyReceived,
                    $unitCost,
                    $warehouseId
                );

                // Creer mouvement de stock
                StockMovement::create([
                    'tenant_id' => $this->tenantId,
                    'product_id' => $purchaseItem->product_id,
                    'from_warehouse_id' => null,
                    'to_warehouse_id' => $warehouseId,
                    'quantity' => $qtyReceived,
                    'unit_cost' => $unitCost,
                    'type' => 'purchase',
                    'reference' => $purchase->reference,
                    'user_id' => $this->userId,
                    'notes' => 'Reception achat',
                ]);

                // Mettre a jour prix de vente si fourni
                if ($salePrice) {
                    $purchaseItem->product->update(['sale_price' => $salePrice]);
                }
            }

            // Verifier si tout est recu
            $allReceived = $purchase->items->every(function ($item) {
                return $item->quantity_received >= $item->quantity_ordered;
            });

            if ($allReceived) {
                // Si le paiement a déjà été validé par le fournisseur, passer directement à 'paid'
                if ($purchase->payment_validated_by_supplier) {
                    $purchase->status = 'paid';
                    $purchase->paid_at = $purchase->payment_validated_at ?? now();
                } else {
                    $purchase->status = 'received';
                }
                $purchase->received_date = now();
                $purchase->received_at = now();
            } else {
                $purchase->status = 'partial';
            }
            $purchase->save();

            // Ecritures comptables
            $this->accountingService->postPurchaseReception($purchase);

            AuditLog::create([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'action' => 'receive',
                'model_type' => 'purchase',
                'model_id' => $purchase->id,
                'payload' => $receivedItems,
                'description' => 'Reception achat',
                'ip_address' => request()->ip(),
            ]);

            return $purchase->fresh()->load('items.product');
        });
    }

    protected function updateStockWithCMP(int $productId, int $qtyReceived, float $unitCost, int $warehouseId): Stock
    {
        // Recuperer le type de warehouse pour le champ legacy
        $warehouse = Warehouse::find($warehouseId);
        $warehouseType = $warehouse ? $warehouse->type : 'gros';

        $stock = Stock::firstOrCreate(
            [
                'tenant_id' => $this->tenantId,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
            ],
            [
                'warehouse' => $warehouseType, // Champ legacy
                'quantity' => 0,
                'cost_average' => 0,
                'reserved' => 0,
                'available' => 0,
            ]
        );

        $oldQty = $stock->quantity;
        $oldCmp = $stock->cost_average ?? 0;

        // Formule CMP
        if ($oldQty + $qtyReceived > 0) {
            $newCmp = ($oldQty * $oldCmp + $qtyReceived * $unitCost) / ($oldQty + $qtyReceived);
        } else {
            $newCmp = $unitCost;
        }

        $stock->quantity += $qtyReceived;
        $stock->cost_average = $newCmp;
        $stock->unit_cost = $unitCost;
        $stock->available = $stock->quantity - ($stock->reserved ?? 0);
        $stock->save();

        return $stock;
    }

    // ========================================
    // MAGASIN GROS - Inventaire
    // ========================================

    public function createInventory(int $warehouseId, array $items = []): Inventory
    {
        $this->init();

        return DB::transaction(function () use ($warehouseId, $items) {
            $inventory = Inventory::create([
                'tenant_id' => $this->tenantId,
                'warehouse_id' => $warehouseId,
                'user_id' => $this->userId,
                'reference' => $this->generateInventoryReference(),
                'status' => 'draft',
                'started_at' => now(),
            ]);

            foreach ($items as $item) {
                $stock = Stock::where('tenant_id', $this->tenantId)
                    ->where('product_id', $item['product_id'])
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                $theoreticalQty = $stock ? $stock->quantity : 0;
                $countedQty = $item['counted_qty'];
                $variance = $countedQty - $theoreticalQty;

                InventoryItem::create([
                    'inventory_id' => $inventory->id,
                    'product_id' => $item['product_id'],
                    'theoretical_qty' => $theoreticalQty,
                    'counted_qty' => $countedQty,
                    'variance' => $variance,
                    'variance_value' => $variance * ($stock->cost_average ?? 0),
                ]);
            }

            return $inventory->load('items.product');
        });
    }

    public function validateInventory(int $inventoryId): Inventory
    {
        $this->init();

        return DB::transaction(function () use ($inventoryId) {
            $inventory = Inventory::where('tenant_id', $this->tenantId)
                ->with('items')
                ->findOrFail($inventoryId);

            if ($inventory->status !== 'completed') {
                throw new Exception('L\'inventaire doit etre complete avant validation');
            }

            foreach ($inventory->items as $item) {
                if ($item->variance != 0) {
                    $stock = Stock::where('tenant_id', $this->tenantId)
                        ->where('product_id', $item->product_id)
                        ->where('warehouse_id', $inventory->warehouse_id)
                        ->first();

                    if ($stock) {
                        // Ajuster le stock
                        $stock->quantity = $item->counted_qty;
                        $stock->available = $item->counted_qty - ($stock->reserved ?? 0);
                        $stock->last_counted_at = now();
                        $stock->save();

                        // Mouvement d'ajustement
                        StockMovement::create([
                            'tenant_id' => $this->tenantId,
                            'product_id' => $item->product_id,
                            'from_warehouse_id' => $item->variance < 0 ? $inventory->warehouse_id : null,
                            'to_warehouse_id' => $item->variance > 0 ? $inventory->warehouse_id : null,
                            'quantity' => abs($item->variance),
                            'unit_cost' => $stock->cost_average ?? 0,
                            'type' => 'adjustment',
                            'reference' => $inventory->reference,
                            'user_id' => $this->userId,
                            'notes' => 'Ajustement inventaire',
                        ]);
                    }
                }
            }

            $inventory->status = 'validated';
            $inventory->completed_at = now();
            $inventory->save();

            // Ecritures comptables pour ajustements
            $this->accountingService->postInventoryAdjustment($inventory);

            AuditLog::create([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'action' => 'validate',
                'model_type' => 'inventory',
                'model_id' => $inventory->id,
                'payload' => ['status' => 'validated'],
                'description' => 'Inventaire valide',
                'ip_address' => request()->ip(),
            ]);

            return $inventory;
        });
    }

    // ========================================
    // MAGASIN DETAIL - Demandes
    // ========================================

    public function createStockRequest(array $data): StockRequest
    {
        $this->init();

        return DB::transaction(function () use ($data) {
            $request = StockRequest::create([
                'tenant_id' => $this->tenantId,
                'reference' => StockRequest::generateReference($this->tenantId),
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'] ?? $this->getGrosWarehouseId(),
                'requested_by' => $this->userId,
                'status' => 'draft',
                'priority' => $data['priority'] ?? 'normal',
                'needed_by_date' => $data['needed_by_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                StockRequestItem::create([
                    'stock_request_id' => $request->id,
                    'product_id' => $item['product_id'],
                    'quantity_requested' => $item['qty_requested'],
                ]);
            }

            AuditLog::create([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'action' => 'create',
                'model_type' => 'stock_request',
                'model_id' => $request->id,
                'payload' => $data,
                'description' => 'Demande de stock creee',
                'ip_address' => request()->ip(),
            ]);

            return $request->load('items.product');
        });
    }

    public function submitStockRequest(int $requestId): StockRequest
    {
        $this->init();
        $request = StockRequest::where('tenant_id', $this->tenantId)->findOrFail($requestId);

        if ($request->status !== 'draft') {
            throw new Exception('Seules les demandes en brouillon peuvent etre soumises');
        }

        $request->submit();

        return $request;
    }

    public function approveStockRequest(int $requestId, array $approvedItems = null): StockRequest
    {
        $this->init();

        return DB::transaction(function () use ($requestId, $approvedItems) {
            $request = StockRequest::where('tenant_id', $this->tenantId)
                ->with('items')
                ->findOrFail($requestId);

            if ($request->status !== 'requested') {
                throw new Exception('Seules les demandes soumises peuvent etre approuvees');
            }

            // Mettre a jour les quantites approuvees
            if ($approvedItems) {
                foreach ($approvedItems as $itemData) {
                    $item = $request->items->find($itemData['item_id']);
                    if ($item) {
                        $item->quantity_approved = $itemData['qty_approved'];
                        $item->save();
                    }
                }
            } else {
                // Approuver toutes les quantites demandees
                foreach ($request->items as $item) {
                    $item->quantity_approved = $item->quantity_requested;
                    $item->save();
                }
            }

            $request->approve($this->userId);

            // Creer automatiquement le transfert
            $transfer = $this->createTransferFromRequest($request);
            $request->markTransferred($transfer->id);

            // Exécuter automatiquement le transfert (déduire du Gros, préparer pour Détail)
            $this->executeTransferInternal($transfer);

            AuditLog::create([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'action' => 'approve',
                'model_type' => 'stock_request',
                'model_id' => $request->id,
                'payload' => ['transfer_id' => $transfer->id],
                'description' => 'Demande approuvee, transfert cree et execute',
                'ip_address' => request()->ip(),
            ]);

            return $request->fresh()->load(['items.product', 'transfer']);
        });
    }

    public function rejectStockRequest(int $requestId, string $reason = null): StockRequest
    {
        $this->init();
        $request = StockRequest::where('tenant_id', $this->tenantId)->findOrFail($requestId);

        if ($request->status !== 'requested') {
            throw new Exception('Seules les demandes soumises peuvent etre rejetees');
        }

        $request->reject($this->userId, $reason);

        AuditLog::create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'action' => 'reject',
            'model_type' => 'stock_request',
            'model_id' => $request->id,
            'payload' => ['reason' => $reason],
            'description' => 'Demande rejetee',
            'ip_address' => request()->ip(),
        ]);

        return $request;
    }

    // ========================================
    // TRANSFERTS
    // ========================================

    protected function createTransferFromRequest(StockRequest $request): Transfer
    {
        // Renseigner aussi les champs legacy from_warehouse / to_warehouse (string)
        $fromWarehouse = Warehouse::find($request->to_warehouse_id);   // gros
        $toWarehouse = Warehouse::find($request->from_warehouse_id);   // detail

        $transfer = Transfer::create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'stock_request_id' => $request->id,
            'reference' => $this->generateTransferReference(),
            'from_warehouse_id' => $request->to_warehouse_id, // gros (entrepôt source)
            'to_warehouse_id' => $request->from_warehouse_id, // detail (entrepôt destination)
            'from_warehouse' => $fromWarehouse ? $fromWarehouse->type : 'gros',
            'to_warehouse' => $toWarehouse ? $toWarehouse->type : 'detail',
            'status' => 'pending',
            'requested_by' => $request->requested_by,
            'requested_at' => $request->requested_at,
        ]);

        foreach ($request->items as $item) {
            $qty = $item->quantity_approved ?? $item->quantity_requested;
            $stock = Stock::where('tenant_id', $this->tenantId)
                ->where('product_id', $item->product_id)
                ->where('warehouse_id', $request->to_warehouse_id)
                ->first();

            TransferItem::create([
                'tenant_id' => $this->tenantId,
                'transfer_id' => $transfer->id,
                'product_id' => $item->product_id,
                'quantity' => $qty,
                'unit_cost' => $stock->cost_average ?? 0,
            ]);
        }

        $transfer->calculateTotalItems();

        return $transfer;
    }

    /**
     * Exécute un transfert en interne (appelé après approbation d'une demande)
     * Déduit le stock du Gros et l'ajoute au Détail
     */
    protected function executeTransferInternal(Transfer $transfer): void
    {
        $transfer->load('items');

        // Vérifier et exécuter le transfert
        foreach ($transfer->items as $item) {
            // Déduire du stock source (Gros)
            $sourceStock = Stock::where('tenant_id', $this->tenantId)
                ->where('product_id', $item->product_id)
                ->where('warehouse_id', $transfer->from_warehouse_id)
                ->first();

            if ($sourceStock && $sourceStock->available >= $item->quantity) {
                $sourceStock->quantity -= $item->quantity;
                $sourceStock->available = $sourceStock->quantity - ($sourceStock->reserved ?? 0);
                $sourceStock->save();
            }

            // Ajouter au stock destination (Détail)
            $destWarehouse = Warehouse::find($transfer->to_warehouse_id);
            $destStock = Stock::firstOrCreate(
                [
                    'tenant_id' => $this->tenantId,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $transfer->to_warehouse_id,
                ],
                [
                    'warehouse' => $destWarehouse ? $destWarehouse->type : 'detail',
                    'quantity' => 0,
                    'cost_average' => $sourceStock->cost_average ?? 0,
                    'unit_cost' => $sourceStock->cost_average ?? $item->unit_cost ?? 0,
                    'reserved' => 0,
                    'available' => 0,
                ]
            );

            $destStock->quantity += $item->quantity;
            $destStock->cost_average = $sourceStock->cost_average ?? $destStock->cost_average;
            $destStock->unit_cost = $sourceStock->cost_average ?? $item->unit_cost ?? $destStock->unit_cost;
            $destStock->available = $destStock->quantity - ($destStock->reserved ?? 0);
            $destStock->save();

            // Mouvement de stock
            StockMovement::create([
                'tenant_id' => $this->tenantId,
                'product_id' => $item->product_id,
                'from_warehouse_id' => $transfer->from_warehouse_id,
                'to_warehouse_id' => $transfer->to_warehouse_id,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost ?? 0,
                'type' => 'transfer',
                'reference' => $transfer->reference,
                'user_id' => $this->userId,
            ]);
        }

        // Marquer comme exécuté
        $transfer->status = 'executed';
        $transfer->executed_by = $this->userId;
        $transfer->executed_at = now();
        $transfer->save();

        // Écritures comptables
        try {
            $this->accountingService->postTransfer($transfer);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas le transfert
            \Log::warning('Erreur comptabilité transfert: ' . $e->getMessage());
        }
    }

    public function executeTransfer(int $transferId): Transfer
    {
        $this->init();

        return DB::transaction(function () use ($transferId) {
            $transfer = Transfer::where('tenant_id', $this->tenantId)
                ->with('items')
                ->findOrFail($transferId);

            if (!in_array($transfer->status, ['pending', 'approved'])) {
                throw new Exception('Ce transfert ne peut pas etre execute');
            }

            // Verifier disponibilite stock source
            foreach ($transfer->items as $item) {
                $sourceStock = Stock::where('tenant_id', $this->tenantId)
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $transfer->from_warehouse_id)
                    ->first();

                if (!$sourceStock || $sourceStock->available < $item->quantity) {
                    throw new Exception("Stock insuffisant pour le produit ID {$item->product_id}");
                }
            }

            // Executer le transfert
            foreach ($transfer->items as $item) {
                // Deduire du stock source
                $sourceStock = Stock::where('tenant_id', $this->tenantId)
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $transfer->from_warehouse_id)
                    ->first();

                $sourceStock->quantity -= $item->quantity;
                $sourceStock->available = $sourceStock->quantity - ($sourceStock->reserved ?? 0);
                $sourceStock->save();

                // Ajouter au stock destination
                $destWarehouse = Warehouse::find($transfer->to_warehouse_id);
                $destStock = Stock::firstOrCreate(
                    [
                        'tenant_id' => $this->tenantId,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $transfer->to_warehouse_id,
                    ],
                    [
                        'warehouse' => $destWarehouse ? $destWarehouse->type : 'detail',
                        'quantity' => 0,
                        'cost_average' => $sourceStock->cost_average ?? 0,
                        'unit_cost' => $sourceStock->cost_average ?? $item->unit_cost ?? 0,
                        'reserved' => 0,
                        'available' => 0,
                    ]
                );

                $destStock->quantity += $item->quantity;
                $destStock->cost_average = $sourceStock->cost_average ?? $destStock->cost_average;
                $destStock->unit_cost = $sourceStock->cost_average ?? $item->unit_cost ?? $destStock->unit_cost;
                $destStock->available = $destStock->quantity - ($destStock->reserved ?? 0);
                $destStock->save();

                // Mouvement de stock
                StockMovement::create([
                    'tenant_id' => $this->tenantId,
                    'product_id' => $item->product_id,
                    'from_warehouse_id' => $transfer->from_warehouse_id,
                    'to_warehouse_id' => $transfer->to_warehouse_id,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'type' => 'transfer',
                    'reference' => $transfer->reference,
                    'user_id' => $this->userId,
                ]);
            }

            $transfer->status = 'executed';
            $transfer->executed_by = $this->userId;
            $transfer->executed_at = now();
            $transfer->save();

            // Ecritures comptables
            $this->accountingService->postTransfer($transfer);

            AuditLog::create([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'action' => 'execute',
                'model_type' => 'transfer',
                'model_id' => $transfer->id,
                'payload' => ['status' => 'executed'],
                'description' => 'Transfert execute',
                'ip_address' => request()->ip(),
            ]);

            return $transfer->fresh()->load('items.product');
        });
    }

    public function receiveTransfer(int $transferId, array $receivedItems = null): Transfer
    {
        $this->init();

        return DB::transaction(function () use ($transferId, $receivedItems) {
            $transfer = Transfer::where('tenant_id', $this->tenantId)
                ->with('items')
                ->findOrFail($transferId);

            if ($transfer->status !== 'executed') {
                throw new Exception('Seuls les transferts executes peuvent etre receptionnes');
            }

            // Enregistrer les quantites recues (avec ecarts eventuels)
            if ($receivedItems) {
                foreach ($receivedItems as $itemData) {
                    $item = $transfer->items->find($itemData['item_id']);
                    if ($item) {
                        $received = $itemData['qty_received'];
                        $shortage = $item->quantity - $received;

                        if ($shortage > 0) {
                            // Enregistrer le manquant
                            StockMovement::create([
                                'tenant_id' => $this->tenantId,
                                'product_id' => $item->product_id,
                                'from_warehouse_id' => $transfer->to_warehouse_id,
                                'to_warehouse_id' => null,
                                'quantity' => $shortage,
                                'unit_cost' => $item->unit_cost,
                                'type' => 'adjustment',
                                'reference' => $transfer->reference . '-SHORTAGE',
                                'user_id' => $this->userId,
                                'notes' => 'Manquant a la reception',
                            ]);

                            // Ajuster le stock
                            $stock = Stock::where('tenant_id', $this->tenantId)
                                ->where('product_id', $item->product_id)
                                ->where('warehouse_id', $transfer->to_warehouse_id)
                                ->first();

                            if ($stock) {
                                $stock->quantity -= $shortage;
                                $stock->available = $stock->quantity - ($stock->reserved ?? 0);
                                $stock->save();
                            }
                        }
                    }
                }
            }

            $transfer->status = 'received';
            $transfer->received_by = $this->userId;
            $transfer->received_at = now();
            $transfer->save();

            AuditLog::create([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'action' => 'receive',
                'model_type' => 'transfer',
                'model_id' => $transfer->id,
                'payload' => $receivedItems,
                'description' => 'Transfert receptionne',
                'ip_address' => request()->ip(),
            ]);

            return $transfer;
        });
    }

    public function validateTransfer(int $transferId): Transfer
    {
        $this->init();
        $transfer = Transfer::where('tenant_id', $this->tenantId)->findOrFail($transferId);

        if ($transfer->status !== 'received') {
            throw new Exception('Seuls les transferts receptionnes peuvent etre valides');
        }

        $transfer->status = 'validated';
        $transfer->validated_by = $this->userId;
        $transfer->validated_at = now();
        $transfer->save();

        AuditLog::create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'action' => 'validate',
            'model_type' => 'transfer',
            'model_id' => $transfer->id,
            'payload' => ['status' => 'validated'],
            'description' => 'Transfert valide',
            'ip_address' => request()->ip(),
        ]);

        return $transfer;
    }

    // ========================================
    // POS ORDERS - Servir et Valider
    // ========================================

    public function createPosOrder(array $data): PosOrder
    {
        $this->init();

        return DB::transaction(function () use ($data) {
            $order = PosOrder::create([
                'tenant_id' => $this->tenantId,
                'pos_id' => $data['pos_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'reference' => PosOrder::generateReference($this->tenantId),
                'status' => 'pending',
                'created_by' => $this->userId,
            ]);

            foreach ($data['items'] as $item) {
                $stock = Stock::where('tenant_id', $this->tenantId)
                    ->where('product_id', $item['product_id'])
                    ->first();

                $orderItem = PosOrderItem::create([
                    'pos_order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity_ordered' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'unit_cost' => $stock->cost_average ?? 0,
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'tax_percent' => $item['tax_percent'] ?? 0,
                ]);

                $orderItem->calculateLineTotal();

                // Reserver le stock
                if ($stock) {
                    $stock->reserved = ($stock->reserved ?? 0) + $item['quantity'];
                    $stock->available = $stock->quantity - $stock->reserved;
                    $stock->save();
                }
            }

            $order->calculateTotals();

            return $order->load('items.product');
        });
    }

    public function servePosOrder(int $orderId, array $servedItems, int $warehouseId): PosOrder
    {
        $this->init();

        return DB::transaction(function () use ($orderId, $servedItems, $warehouseId) {
            $order = PosOrder::where('tenant_id', $this->tenantId)
                ->with('items')
                ->findOrFail($orderId);

            if (!in_array($order->status, ['pending', 'preparing'])) {
                throw new Exception('Cette commande ne peut pas etre servie');
            }

            $remiseItems = [];

            foreach ($servedItems as $itemData) {
                $orderItem = $order->items->find($itemData['item_id']);
                if (!$orderItem) continue;

                $qtyServed = $itemData['qty_served'];
                $orderItem->quantity_served += $qtyServed;
                $orderItem->save();

                // Deduire du stock
                $stock = Stock::where('tenant_id', $this->tenantId)
                    ->where('product_id', $orderItem->product_id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                if ($stock) {
                    $stock->quantity -= $qtyServed;
                    $stock->reserved = max(0, ($stock->reserved ?? 0) - $qtyServed);
                    $stock->available = $stock->quantity - $stock->reserved;
                    $stock->save();
                }

                // Mouvement de stock
                StockMovement::create([
                    'tenant_id' => $this->tenantId,
                    'product_id' => $orderItem->product_id,
                    'from_warehouse_id' => $warehouseId,
                    'to_warehouse_id' => null,
                    'quantity' => $qtyServed,
                    'unit_cost' => $orderItem->unit_cost,
                    'type' => 'sale',
                    'reference' => $order->reference,
                    'user_id' => $this->userId,
                    'notes' => 'Remise commande POS',
                ]);

                $remiseItems[] = [
                    'product_id' => $orderItem->product_id,
                    'quantity_served' => $qtyServed,
                    'unit_cost' => $orderItem->unit_cost,
                ];
            }

            // Creer la remise
            PosRemise::create([
                'tenant_id' => $this->tenantId,
                'pos_order_id' => $order->id,
                'warehouse_id' => $warehouseId,
                'served_by' => $this->userId,
                'reference' => PosRemise::generateReference($this->tenantId),
                'status' => 'completed',
                'items' => $remiseItems,
            ]);

            // Verifier si tout est servi
            $allServed = $order->items->every(fn($item) => $item->isFullyServed());
            $order->status = $allServed ? 'served' : 'preparing';
            if ($allServed) {
                $order->served_by = $this->userId;
                $order->served_at = now();
            }
            $order->save();

            AuditLog::create([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'action' => 'serve',
                'model_type' => 'pos_order',
                'model_id' => $order->id,
                'payload' => $servedItems,
                'description' => 'Commande servie',
                'ip_address' => request()->ip(),
            ]);

            return $order->fresh()->load('items.product');
        });
    }

    public function validatePosOrder(int $orderId, string $paymentReference = null): PosOrder
    {
        $this->init();

        return DB::transaction(function () use ($orderId, $paymentReference) {
            $order = PosOrder::where('tenant_id', $this->tenantId)
                ->with('items')
                ->findOrFail($orderId);

            if (!$order->isPaid()) {
                throw new Exception('La commande doit etre payee avant validation');
            }

            $order->validate($this->userId);

            // Ecritures comptables
            $this->accountingService->postSale($order);

            AuditLog::create([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'action' => 'validate',
                'model_type' => 'pos_order',
                'model_id' => $order->id,
                'payload' => ['payment_reference' => $paymentReference],
                'description' => 'Commande validee',
                'ip_address' => request()->ip(),
            ]);

            return $order;
        });
    }

    // ========================================
    // DASHBOARDS
    // ========================================

    public function getGrosDashboard(string $from = null, string $to = null): array
    {
        $this->init();
        $warehouseId = $this->getGrosWarehouseId();
        $from = $from ?? now()->startOfMonth()->toDateString();
        $to = $to ?? now()->toDateString();

        // Cache 5 minutes pour performance
        $cacheKey = "gros_dashboard_{$this->tenantId}_{$warehouseId}";
        
        return Cache::remember($cacheKey, 300, function () use ($warehouseId, $from, $to) {
            // Requête optimisée - une seule requête pour les stocks
            $stockStats = Stock::where('tenant_id', $this->tenantId)
                ->where('warehouse_id', $warehouseId)
                ->selectRaw('SUM(quantity * cost_average) as total_value, COUNT(CASE WHEN quantity < 10 THEN 1 END) as low_count, COUNT(*) as total_products')
                ->first();

            // Compteurs simples en parallèle
            $movementsToday = StockMovement::where('tenant_id', $this->tenantId)
                ->where(function ($q) use ($warehouseId) {
                    $q->where('from_warehouse_id', $warehouseId)->orWhere('to_warehouse_id', $warehouseId);
                })
                ->whereDate('created_at', now())
                ->count();

            $pendingPO = Purchase::where('tenant_id', $this->tenantId)
                ->whereIn('status', ['draft', 'ordered', 'confirmed'])
                ->count();

            $pendingRequests = StockRequest::where('tenant_id', $this->tenantId)
                ->where('status', 'requested')
                ->count();

            return [
                'stock_value' => $stockStats->total_value ?? 0,
                'movements_today' => $movementsToday,
                'low_stock_count' => $stockStats->low_count ?? 0,
                'total_products' => $stockStats->total_products ?? 0,
                'pending_po_count' => $pendingPO,
                'pending_requests_count' => $pendingRequests,
                'period' => ['from' => $from, 'to' => $to],
            ];
        });
    }

    public function getDetailDashboard(string $from = null, string $to = null): array
    {
        $this->init();
        $warehouseId = $this->getDetailWarehouseId();
        $from = $from ?? now()->startOfMonth()->toDateString();
        $to = $to ?? now()->toDateString();

        // Cache 5 minutes pour performance
        $cacheKey = "detail_dashboard_{$this->tenantId}_{$warehouseId}";
        
        return Cache::remember($cacheKey, 300, function () use ($warehouseId, $from, $to) {
            // Requête optimisée - une seule requête pour les stocks
            $stockStats = Stock::where('tenant_id', $this->tenantId)
                ->where('warehouse_id', $warehouseId)
                ->selectRaw('SUM(quantity * cost_average) as total_value, SUM(available) as total_available, COUNT(CASE WHEN quantity < 10 THEN 1 END) as low_count')
                ->first();

            // Compteurs simples
            $pendingRequests = StockRequest::where('tenant_id', $this->tenantId)
                ->where('from_warehouse_id', $warehouseId)
                ->whereIn('status', ['draft', 'requested'])
                ->count();

            $pendingTransfers = Transfer::where('tenant_id', $this->tenantId)
                ->where('to_warehouse_id', $warehouseId)
                ->whereIn('status', ['pending', 'approved', 'shipped', 'in_transit'])
                ->count();

            // Ventes du jour (optionnel, peut être null)
            $salesToday = PosOrder::where('tenant_id', $this->tenantId)
                ->whereDate('created_at', now())
                ->where('status', 'completed')
                ->sum('total') ?? 0;

            return [
                'stock_value' => $stockStats->total_value ?? 0,
                'available_stock' => $stockStats->total_available ?? 0,
                'low_stock_count' => $stockStats->low_count ?? 0,
                'pending_requests_count' => $pendingRequests,
                'pending_transfers_count' => $pendingTransfers,
                'sales_today' => $salesToday,
                'period' => ['from' => $from, 'to' => $to],
            ];
        });
    }

    // ========================================
    // HELPERS
    // ========================================

    protected function getGrosWarehouseId(): int
    {
        $warehouse = Warehouse::where('tenant_id', $this->tenantId)
            ->where('type', 'gros')
            ->first();

        if (!$warehouse) {
            // Creer un entrepot gros par defaut
            $warehouse = Warehouse::create([
                'tenant_id' => $this->tenantId,
                'code' => 'GROS',
                'name' => 'Magasin Gros',
                'type' => 'gros',
                'is_active' => true,
            ]);
        }

        return $warehouse->id;
    }

    protected function getDetailWarehouseId(): int
    {
        $warehouse = Warehouse::where('tenant_id', $this->tenantId)
            ->where('type', 'detail')
            ->first();

        if (!$warehouse) {
            // Creer un entrepot detail par defaut
            $warehouse = Warehouse::create([
                'tenant_id' => $this->tenantId,
                'code' => 'DETAIL',
                'name' => 'Magasin Detail',
                'type' => 'detail',
                'is_active' => true,
            ]);
        }

        return $warehouse->id;
    }

    protected function generatePurchaseReference(): string
    {
        $today = now()->format('Ymd');
        $count = Purchase::where('tenant_id', $this->tenantId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return "PO-$today-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    protected function generateTransferReference(): string
    {
        $today = now()->format('Ymd');
        $count = Transfer::where('tenant_id', $this->tenantId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return "TX-$today-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    protected function generateInventoryReference(): string
    {
        $today = now()->format('Ymd');
        $count = Inventory::where('tenant_id', $this->tenantId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return "INV-$today-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }
}
