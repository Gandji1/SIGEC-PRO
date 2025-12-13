<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApprovisionnementService;
use App\Models\Purchase;
use App\Models\Transfer;
use App\Models\StockRequest;
use App\Models\StockMovement;
use App\Models\Inventory;
use App\Models\PosOrder;
use App\Models\Warehouse;
use App\Models\Stock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ApprovisionnementController extends Controller
{
    protected ApprovisionnementService $service;

    public function __construct(ApprovisionnementService $service)
    {
        $this->service = $service;
    }

    // ========================================
    // DASHBOARDS
    // ========================================

    public function grosDashboard(Request $request): JsonResponse
    {
        try {
            $from = $request->query('from');
            $to = $request->query('to');
            
            // Le service gère déjà le cache
            $data = $this->service->getGrosDashboard($from, $to);

            return response()->json($data);
        } catch (\Exception $e) {
            \Log::error('Gros dashboard error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors du chargement du dashboard',
                'message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function detailDashboard(Request $request): JsonResponse
    {
        try {
            $from = $request->query('from');
            $to = $request->query('to');
            
            // Le service gère déjà le cache
            $data = $this->service->getDetailDashboard($from, $to);

            return response()->json($data);
        } catch (\Exception $e) {
            \Log::error('Detail dashboard error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors du chargement du dashboard',
                'message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    // ========================================
    // ACHATS (GROS)
    // ========================================

    public function listPurchases(Request $request): JsonResponse
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        $perPage = min($request->query('per_page', 50), 100);

        $query = Purchase::where('tenant_id', $tenantId)
            ->select(['id', 'reference', 'supplier_name', 'status', 'total', 'created_at', 'warehouse_id'])
            ->with(['items:id,purchase_id,product_id,quantity_ordered,unit_price', 'items.product:id,name,code']);

        if ($request->has('status')) {
            $statuses = explode(',', $request->query('status'));
            $query->whereIn('status', $statuses);
        }

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->query('warehouse_id'));
        }

        if ($request->has('from') && $request->has('to')) {
            $query->whereBetween('created_at', [$request->query('from'), $request->query('to')]);
        }

        $purchases = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($purchases);
    }

    public function createPurchase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_name' => 'required_without:supplier_id|string|max:255',
            'supplier_phone' => 'nullable|string',
            'supplier_email' => 'nullable|email',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty_ordered' => 'required|integer|min:1',
            'items.*.expected_unit_cost' => 'nullable|numeric|min:0',
        ]);

        try {
            $purchase = $this->service->createPurchaseOrder($validated);
            return response()->json($purchase, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function showPurchase(Purchase $purchase): JsonResponse
    {
        $this->authorize('view', $purchase);
        return response()->json($purchase->load(['items.product', 'supplier', 'warehouse', 'user']));
    }

    public function submitPurchase(Purchase $purchase): JsonResponse
    {
        $this->authorize('update', $purchase);

        try {
            $purchase = $this->service->submitPurchaseOrder($purchase->id);
            return response()->json($purchase);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function receivePurchase(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorize('update', $purchase);

        $validated = $request->validate([
            'received_items' => 'required|array|min:1',
            'received_items.*.purchase_item_id' => 'required|exists:purchase_items,id',
            'received_items.*.qty_received' => 'required|integer|min:1',
            'received_items.*.unit_cost' => 'nullable|numeric|min:0',
            'received_items.*.sale_price' => 'nullable|numeric|min:0',
        ]);

        try {
            $purchase = $this->service->receivePurchase($purchase->id, $validated['received_items']);
            return response()->json($purchase);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // ========================================
    // DEMANDES DE STOCK (DETAIL -> GROS)
    // ========================================

    public function listRequests(Request $request): JsonResponse
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        $perPage = min($request->query('per_page', 50), 100);

        $query = StockRequest::where('tenant_id', $tenantId)
            ->select(['id', 'reference', 'status', 'priority', 'from_warehouse_id', 'to_warehouse_id', 'requested_by', 'needed_by_date', 'created_at'])
            ->with([
                'items:id,stock_request_id,product_id,quantity_requested', 
                'items.product:id,name,code',
                'requestedByUser:id,name',
                'fromWarehouse:id,name',
                'toWarehouse:id,name',
            ]);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('from_warehouse_id')) {
            $query->where('from_warehouse_id', $request->query('from_warehouse_id'));
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($requests);
    }

    public function createRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'nullable|exists:warehouses,id',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'needed_by_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty_requested' => 'required|integer|min:1',
        ]);

        try {
            $stockRequest = $this->service->createStockRequest($validated);
            return response()->json($stockRequest, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function showRequest(StockRequest $stockRequest): JsonResponse
    {
        return response()->json($stockRequest->load(['items.product', 'fromWarehouse', 'toWarehouse', 'requestedByUser', 'approvedByUser', 'transfer']));
    }

    public function submitRequest(StockRequest $stockRequest): JsonResponse
    {
        try {
            $stockRequest = $this->service->submitStockRequest($stockRequest->id);
            return response()->json($stockRequest);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function approveRequest(Request $request, StockRequest $stockRequest): JsonResponse
    {
        $validated = $request->validate([
            'approved_items' => 'nullable|array',
            'approved_items.*.item_id' => 'required|exists:stock_request_items,id',
            'approved_items.*.qty_approved' => 'required|integer|min:0',
        ]);

        try {
            $stockRequest = $this->service->approveStockRequest(
                $stockRequest->id,
                $validated['approved_items'] ?? null
            );
            return response()->json($stockRequest);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function rejectRequest(Request $request, StockRequest $stockRequest): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $stockRequest = $this->service->rejectStockRequest($stockRequest->id, $validated['reason'] ?? null);
            return response()->json($stockRequest);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // ========================================
    // TRANSFERTS
    // ========================================

    public function listTransfers(Request $request): JsonResponse
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        $perPage = min($request->query('per_page', 50), 100);

        $query = Transfer::where('tenant_id', $tenantId)
            ->select(['id', 'reference', 'status', 'from_warehouse_id', 'to_warehouse_id', 'total_items', 'created_at'])
            ->with(['items:id,transfer_id,product_id,quantity', 'items.product:id,name,code']);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('from_warehouse_id')) {
            $query->where('from_warehouse_id', $request->query('from_warehouse_id'));
        }

        if ($request->has('to_warehouse_id')) {
            $query->where('to_warehouse_id', $request->query('to_warehouse_id'));
        }

        $transfers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($transfers);
    }

    public function showTransfer(Transfer $transfer): JsonResponse
    {
        $this->authorize('view', $transfer);
        return response()->json($transfer->load(['items.product', 'fromWarehouse', 'toWarehouse', 'requestedByUser', 'approvedByUser']));
    }

    public function executeTransfer(Transfer $transfer): JsonResponse
    {
        $this->authorize('update', $transfer);

        try {
            $transfer = $this->service->executeTransfer($transfer->id);
            return response()->json($transfer);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function receiveTransfer(Request $request, Transfer $transfer): JsonResponse
    {
        $this->authorize('update', $transfer);

        $validated = $request->validate([
            'received_items' => 'nullable|array',
            'received_items.*.item_id' => 'required|exists:transfer_items,id',
            'received_items.*.qty_received' => 'required|integer|min:0',
        ]);

        try {
            $transfer = $this->service->receiveTransfer($transfer->id, $validated['received_items'] ?? null);
            return response()->json($transfer);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function validateTransfer(Transfer $transfer): JsonResponse
    {
        $this->authorize('update', $transfer);

        try {
            $transfer = $this->service->validateTransfer($transfer->id);
            return response()->json($transfer);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // ========================================
    // INVENTAIRES
    // ========================================

    public function listInventories(Request $request): JsonResponse
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;

        $query = Inventory::where('tenant_id', $tenantId)
            ->with(['items.product', 'warehouse', 'user']);

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->query('warehouse_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $inventories = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($inventories);
    }

    public function createInventory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.counted_qty' => 'required|integer|min:0',
        ]);

        try {
            $inventory = $this->service->createInventory(
                $validated['warehouse_id'],
                $validated['items'] ?? []
            );
            return response()->json($inventory, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function validateInventory(Inventory $inventory): JsonResponse
    {
        try {
            $inventory = $this->service->validateInventory($inventory->id);
            return response()->json($inventory);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // ========================================
    // MOUVEMENTS DE STOCK
    // ========================================

    public function listMovements(Request $request): JsonResponse
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;

        $query = StockMovement::where('tenant_id', $tenantId)
            ->with(['product', 'fromWarehouse', 'toWarehouse', 'user']);

        if ($request->has('product_id')) {
            $query->where('product_id', $request->query('product_id'));
        }

        if ($request->has('warehouse_id')) {
            $warehouseId = $request->query('warehouse_id');
            $query->where(function ($q) use ($warehouseId) {
                $q->where('from_warehouse_id', $warehouseId)
                    ->orWhere('to_warehouse_id', $warehouseId);
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->has('from') && $request->has('to')) {
            $query->whereBetween('created_at', [$request->query('from'), $request->query('to')]);
        }

        $movements = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json($movements);
    }

    // ========================================
    // COMMANDES POS
    // ========================================

    public function listPosOrders(Request $request): JsonResponse
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        $perPage = min($request->query('per_page', 20), 100);

        $query = PosOrder::where('tenant_id', $tenantId)
            ->with(['items.product', 'pos', 'customer', 'createdByUser', 'servedByUser']);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('pos_id')) {
            $query->where('pos_id', $request->query('pos_id'));
        }

        // Filtre par date
        if ($request->has('date')) {
            $query->whereDate('created_at', $request->query('date'));
        }

        // Filtre par utilisateur (serveur)
        if ($request->has('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($orders);
    }

    public function createPosOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pos_id' => 'nullable|exists:pos,id',
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_percent' => 'nullable|numeric|min:0',
        ]);

        try {
            $order = $this->service->createPosOrder($validated);
            return response()->json($order, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function showPosOrder(PosOrder $posOrder): JsonResponse
    {
        return response()->json($posOrder->load(['items.product', 'pos', 'customer', 'createdByUser', 'servedByUser', 'remises']));
    }

    public function servePosOrder(Request $request, PosOrder $posOrder): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'items_served' => 'required|array|min:1',
            'items_served.*.item_id' => 'required|exists:pos_order_items,id',
            'items_served.*.qty_served' => 'required|integer|min:1',
        ]);

        try {
            $order = $this->service->servePosOrder(
                $posOrder->id,
                $validated['items_served'],
                $validated['warehouse_id']
            );
            return response()->json($order);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function validatePosOrder(Request $request, PosOrder $posOrder): JsonResponse
    {
        $validated = $request->validate([
            'payment_reference' => 'nullable|string',
        ]);

        try {
            $order = $this->service->validatePosOrder($posOrder->id, $validated['payment_reference'] ?? null);
            return response()->json($order);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // ========================================
    // STOCKS PAR ENTREPOT
    // ========================================

    public function getWarehouseStock(Request $request, Warehouse $warehouse): JsonResponse
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;

        if ($warehouse->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Non autorise'], 403);
        }

        $stocks = Stock::where('tenant_id', $tenantId)
            ->where('warehouse_id', $warehouse->id)
            ->with('product')
            ->paginate(50);

        return response()->json($stocks);
    }

    public function getStockSummary(Request $request): JsonResponse
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;

        $summary = Warehouse::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->map(function ($warehouse) use ($tenantId) {
                $stocks = Stock::where('tenant_id', $tenantId)
                    ->where('warehouse_id', $warehouse->id);

                return [
                    'warehouse_id' => $warehouse->id,
                    'warehouse_name' => $warehouse->name,
                    'warehouse_type' => $warehouse->type,
                    'total_products' => $stocks->count(),
                    'total_quantity' => $stocks->sum('quantity'),
                    'total_value' => $stocks->selectRaw('SUM(quantity * cost_average) as total')->value('total') ?? 0,
                    'low_stock_count' => $stocks->where('quantity', '<', 10)->count(),
                ];
            });

        return response()->json($summary);
    }

    // ========================================
    // EXPORTS
    // ========================================

    public function exportDashboard(Request $request)
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        $format = $request->query('format', 'csv');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $data = $this->service->getGrosDashboard($from, $to);
        
        $filename = "dashboard_gros_{$from}_{$to}";
        
        if ($format === 'csv') {
            $csv = "Indicateur,Valeur\n";
            $csv .= "Valeur Stock," . number_format($data['stock_value'], 0, ',', ' ') . " FCFA\n";
            $csv .= "Mouvements Jour," . $data['movements_today'] . "\n";
            $csv .= "Mouvements Periode," . $data['movements_period'] . "\n";
            $csv .= "Stock Bas," . $data['low_stock_count'] . "\n";
            $csv .= "Commandes en Attente," . $data['pending_po_count'] . "\n";
            $csv .= "Demandes en Attente," . $data['pending_requests'] . "\n";
            $csv .= "Valeur Receptions," . number_format($data['reception_value'], 0, ',', ' ') . " FCFA\n";
            
            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', "attachment; filename={$filename}.csv");
        }

        return response()->json(['error' => 'Format non supporte'], 400);
    }

    public function exportStock(Request $request)
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        $warehouseId = $request->query('warehouse_id');
        $format = $request->query('format', 'csv');

        $query = Stock::where('tenant_id', $tenantId)
            ->with('product');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $stocks = $query->get();
        
        if ($format === 'csv') {
            $csv = "Code,Produit,Quantite,Reserve,Disponible,CMP,Valeur\n";
            foreach ($stocks as $stock) {
                $value = $stock->quantity * ($stock->cost_average ?? 0);
                $csv .= sprintf(
                    "%s,%s,%d,%d,%d,%s,%s\n",
                    $stock->product->code ?? '',
                    str_replace(',', ' ', $stock->product->name ?? ''),
                    $stock->quantity,
                    $stock->reserved ?? 0,
                    $stock->available ?? $stock->quantity,
                    number_format($stock->cost_average ?? 0, 0, ',', ' '),
                    number_format($value, 0, ',', ' ')
                );
            }
            
            return response($csv)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename=stock_gros.csv');
        }

        return response()->json(['error' => 'Format non supporte'], 400);
    }

    public function exportMovements(Request $request)
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $format = $request->query('format', 'csv');

        $movements = StockMovement::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->with(['product', 'fromWarehouse', 'toWarehouse', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        if ($format === 'csv') {
            $csv = "Date,Type,Produit,Quantite,Cout Unitaire,De,Vers,Reference,Utilisateur\n";
            foreach ($movements as $m) {
                $csv .= sprintf(
                    "%s,%s,%s,%d,%s,%s,%s,%s,%s\n",
                    $m->created_at->format('d/m/Y H:i'),
                    $m->type,
                    str_replace(',', ' ', $m->product->name ?? ''),
                    $m->quantity,
                    number_format($m->unit_cost ?? 0, 0, ',', ' '),
                    $m->fromWarehouse->name ?? '-',
                    $m->toWarehouse->name ?? '-',
                    $m->reference ?? '',
                    $m->user->name ?? ''
                );
            }
            
            return response($csv)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', "attachment; filename=mouvements_{$from}_{$to}.csv");
        }

        return response()->json(['error' => 'Format non supporte'], 400);
    }

    public function exportPurchases(Request $request)
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $format = $request->query('format', 'csv');

        $purchases = Purchase::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->with(['items.product', 'supplier'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        if ($format === 'csv') {
            $csv = "Reference,Date,Fournisseur,Statut,Nb Articles,Total\n";
            foreach ($purchases as $p) {
                $csv .= sprintf(
                    "%s,%s,%s,%s,%d,%s\n",
                    $p->reference,
                    $p->created_at->format('d/m/Y'),
                    str_replace(',', ' ', $p->supplier->name ?? $p->supplier_name ?? '-'),
                    $p->status,
                    $p->items->count(),
                    number_format($p->total ?? 0, 0, ',', ' ')
                );
            }
            
            return response($csv)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', "attachment; filename=commandes_{$from}_{$to}.csv");
        }

        return response()->json(['error' => 'Format non supporte'], 400);
    }
}
