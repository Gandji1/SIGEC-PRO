<?php

namespace App\Domains\Purchases\Services;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\AuditLog;
use App\Models\AccountingEntry;
use App\Domains\Stocks\Services\StockService;
use App\Domains\Accounting\Services\AutoPostingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class PurchaseService
{
    private StockService $stockService;

    public function __construct()
    {
        $this->stockService = new StockService();
    }

    public function createPurchase(array $data): Purchase
    {
        $tenant_id = auth()->guard('sanctum')->user()->tenant_id;
        $user_id = auth()->guard('sanctum')->id();

        $purchase = Purchase::create([
            'tenant_id' => $tenant_id,
            'user_id' => $user_id,
            'reference' => $this->generateReference($tenant_id),
            'supplier_id' => $data['supplier_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'supplier_name' => $data['supplier_name'],
            'supplier_phone' => $data['supplier_phone'] ?? null,
            'supplier_email' => $data['supplier_email'] ?? null,
            'payment_method' => $data['payment_method'] ?? 'transfer',
            'expected_date' => $data['expected_date'] ?? null,
            'status' => Purchase::STATUS_DRAFT, // Brouillon - gérant doit soumettre pour approbation
            'created_by_user_id' => $user_id,
        ]);

        AuditLog::log('create', 'purchase', $purchase->id, $data, 'Purchase created');

        return $purchase;
    }

    public function addItem(int $purchase_id, int $product_id, int $quantity, float $unit_price = null): PurchaseItem
    {
        $purchase = Purchase::findOrFail($purchase_id);
        $product = Product::find($product_id);

        if (!$product) {
            throw new Exception("Product not found");
        }

        $unit_price = $unit_price ?? $product->purchase_price ?? 0;

        $item = PurchaseItem::create([
            'tenant_id' => $purchase->tenant_id,
            'purchase_id' => $purchase->id,
            'product_id' => $product_id,
            'quantity_ordered' => $quantity,
            'unit_price' => $unit_price,
            'tax_percent' => $product->tax_percent ?? 0,
            'unit' => $product->unit,
        ]);

        // Calculer les totaux
        $item->subtotal = $quantity * $unit_price;
        $item->tax_amount = $item->subtotal * ($item->tax_percent / 100);
        $item->total = $item->subtotal + $item->tax_amount;
        $item->save();

        // Mettre à jour les totaux du bon d'achat
        $purchase->subtotal = $purchase->items()->sum('subtotal');
        $purchase->tax_amount = $purchase->items()->sum('tax_amount');
        $purchase->total = $purchase->subtotal + $purchase->tax_amount;
        $purchase->save();

        return $item;
    }

    public function confirmPurchase($purchase_id): Purchase
    {
        $purchase = Purchase::findOrFail($purchase_id);
        $purchase->status = 'confirmed';
        $purchase->confirmed_at = now();
        $purchase->save();

        AuditLog::log('update', 'purchase', $purchase->id, ['status' => 'confirmed'], 'Purchase confirmed');

        return $purchase;
    }

    public function receiveItem($purchase_id, $purchase_item_id, $quantity_received): PurchaseItem
    {
        $item = PurchaseItem::findOrFail($purchase_item_id);
        
        if ($quantity_received > $item->quantity_ordered) {
            throw new Exception('Received quantity exceeds ordered quantity');
        }

        $item->quantity_received = $quantity_received;
        $item->received_at = now();
        $item->save();

        return $item;
    }

    public function receivePurchase($purchase_id): Purchase
    {
        $purchase = Purchase::findOrFail($purchase_id);
        
        // Déterminer le warehouse cible (celui de la commande ou le warehouse gros par défaut)
        $warehouseId = $purchase->warehouse_id ?? $this->getDefaultWarehouse($purchase->tenant_id);
        
        DB::beginTransaction();
        try {
            foreach ($purchase->items as $item) {
                $this->updateStockWithCMP(
                    $purchase->tenant_id,
                    $item->product_id,
                    $item->quantity_received ?? $item->quantity_ordered,
                    $item->unit_price,
                    $warehouseId
                );

                // Créer mouvement de stock
                StockMovement::create([
                    'tenant_id' => $purchase->tenant_id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $warehouseId,
                    'type' => 'purchase',
                    'quantity' => $item->quantity_received ?? $item->quantity_ordered,
                    'unit_cost' => $item->unit_price,
                    'reference' => 'PUR-' . $purchase->id,
                    'reference_id' => $purchase->id,
                    'user_id' => auth()->id(),
                ]);
            }

            $purchase->status = 'received';
            $purchase->received_at = now();
            $purchase->save();

            // Auto-post to GL
            $autoPostingService = new AutoPostingService($purchase->tenant_id);
            $autoPostingService->postPurchaseReceived($purchase);

            // Créer mouvement de caisse (sortie) pour le paiement fournisseur
            $this->recordPurchaseCashMovement($purchase);

            AuditLog::log('update', 'purchase', $purchase->id, ['status' => 'received'], 'Purchase received');

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $purchase;
    }

    public function cancelPurchase($purchase_id): Purchase
    {
        $purchase = Purchase::findOrFail($purchase_id);
        
        if (!in_array($purchase->status, ['pending', 'confirmed'])) {
            throw new Exception("Cannot cancel purchase in {$purchase->status} status");
        }

        $purchase->status = 'cancelled';
        $purchase->save();

        AuditLog::log('update', 'purchase', $purchase->id, ['status' => 'cancelled'], 'Purchase cancelled');

        return $purchase;
    }

    /**
     * Obtenir le warehouse par défaut (gros) pour un tenant
     */
    private function getDefaultWarehouse(int $tenant_id): int
    {
        $warehouse = \App\Models\Warehouse::where('tenant_id', $tenant_id)
            ->where('type', 'gros')
            ->first();
        
        return $warehouse?->id ?? 1;
    }

    /**
     * Mettre à jour le stock avec calcul du CMP (Coût Moyen Pondéré)
     */
    private function updateStockWithCMP(int $tenant_id, int $product_id, int $quantity_received, float $unit_price, int $warehouse_id = null): Stock
    {
        $warehouse_id = $warehouse_id ?? $this->getDefaultWarehouse($tenant_id);
        
        $stock = Stock::firstOrCreate(
            [
                'tenant_id' => $tenant_id,
                'product_id' => $product_id,
                'warehouse_id' => $warehouse_id,
            ],
            [
                'quantity' => 0,
                'cost_average' => 0,
                'reserved' => 0,
                'available' => 0,
            ]
        );

        $old_qty = $stock->quantity;
        $old_cmp = $stock->cost_average ?? 0;
        
        // Formule CMP: (old_qty * old_cmp + new_qty * new_price) / (old_qty + new_qty)
        $new_cmp = ($old_qty * $old_cmp + $quantity_received * $unit_price) / ($old_qty + $quantity_received);

        $stock->quantity += $quantity_received;
        $stock->cost_average = $new_cmp;
        $stock->unit_cost = $unit_price; // Latest price
        $stock->updateAvailableQuantity();
        $stock->save();

        return $stock;
    }

    public function getPurchasesReport(string $start_date, string $end_date): array
    {
        $start = Carbon::parse($start_date)->startOfDay();
        $end = Carbon::parse($end_date)->endOfDay();

        $purchases = Purchase::where('tenant_id', auth()->guard('sanctum')->user()->tenant_id)
            ->whereIn('status', ['received', 'completed', 'partial'])
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $buckets = [];
        $totalPurchases = 0;

        foreach ($purchases as $purchase) {
            $key = ($purchase->received_at ?? $purchase->created_at)->format('Y-m-d');

            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'date' => $key,
                    'count' => 0,
                    'total' => 0,
                ];
            }

            $buckets[$key]['count']++;
            $buckets[$key]['total'] += (float) ($purchase->total ?? 0);
            $totalPurchases += (float) ($purchase->total ?? 0);
        }

        $daily = array_values($buckets);
        usort($daily, fn($a, $b) => strcmp($a['date'], $b['date']));

        return [
            'total_purchases' => $totalPurchases,
            'purchases_count' => $purchases->count(),
            'daily' => $daily,
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
        ];
    }

    private function generateReference(int $tenant_id): string
    {
        $today = now()->format('Ymd');
        $count = Purchase::where('tenant_id', $tenant_id)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return "PUR-$today-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Enregistrer le mouvement de caisse pour un achat (sortie de fonds)
     * Diminue le solde de caisse du gérant
     */
    private function recordPurchaseCashMovement(Purchase $purchase): void
    {
        $userId = auth()->id();
        $session = CashRegisterSession::getOpenSession($purchase->tenant_id);

        CashMovement::create([
            'tenant_id' => $purchase->tenant_id,
            'user_id' => $userId,
            'session_id' => $session?->id,
            'pos_id' => null,
            'type' => 'out', // Sortie de caisse
            'category' => 'purchase',
            'amount' => (float) $purchase->total,
            'payment_method' => $purchase->payment_method ?? 'cash',
            'reference' => $purchase->reference,
            'description' => "Paiement fournisseur - {$purchase->supplier_name}",
            'related_id' => $purchase->id,
            'related_type' => 'purchase',
        ]);
    }
}
