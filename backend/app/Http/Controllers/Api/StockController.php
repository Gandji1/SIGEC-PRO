<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Domains\Stocks\Services\StockService;
use App\Traits\ResolveTenantId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StockController extends Controller
{
    use ResolveTenantId;
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé', 'data' => []], 400);
        }

        $warehouseId = $request->query('warehouse_id', '');
        $warehouse = $request->query('warehouse', '');
        $lowStock = $request->has('low_stock');
        $perPage = min($request->query('per_page', 50), 200);
        $page = $request->query('page', 1);

        // Cache key basée sur les paramètres
        $cacheKey = "stocks_{$tenantId}_{$warehouseId}_{$warehouse}_{$lowStock}_{$perPage}_{$page}";
        
           $query = Stock::where('tenant_id', $tenantId)
                ->select(['id', 'product_id', 'warehouse_id', 'warehouse', 'quantity', 'available', 'reserved', 'unit_cost', 'cost_average'])
                ->with(['product:id,name,code,unit,selling_price,purchase_price']);

            if ($warehouse) {
                $query->where('warehouse', $warehouse);
            }

            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }

            if ($lowStock) {
                $query->where('available', '<=', 10);
            }

            $stocks = $query->orderBy('created_at', 'desc')->paginate($perPage);
        

        return response()->json($stocks);
    }

    public function show(Request $request, Stock $stock): JsonResponse
    {
        $this->authorize('view', $stock);

        return response()->json($stock->load('product'));
    }

    public function adjust(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse' => 'required|string|max:255',
            'quantity_change' => 'required|integer',
            'reason' => 'required|string|in:inventory_count,damage,loss,correction,return,other',
            'notes' => 'nullable|string',
        ]);

        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }

        try {
            if ($validated['quantity_change'] > 0) {
                $this->stockService->addStock(
                    $tenantId,
                    $validated['product_id'],
                    $validated['quantity_change'],
                    0,
                    $validated['warehouse'],
                    'adjustment_' . $validated['reason'],
                    $validated['notes'] ?? ''
                );
            } else {
                $this->stockService->removeStock(
                    $tenantId,
                    $validated['product_id'],
                    abs($validated['quantity_change']),
                    $validated['warehouse'],
                    'adjustment_' . $validated['reason'],
                    $validated['notes'] ?? ''
                );
            }

            $stock = Stock::where('tenant_id', $tenantId)
                ->where('product_id', $validated['product_id'])
                ->where('warehouse', $validated['warehouse'])
                ->first();

            return response()->json(
                $stock->load('product'),
                200
            );
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function reserve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:1',
            'reference' => 'required|string|max:255',
        ]);

        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }

        try {
            $this->stockService->reserveStock(
                $tenantId,
                $validated['product_id'],
                $validated['quantity'],
                $validated['warehouse'],
                $validated['reference']
            );

            $stock = Stock::where('tenant_id', $tenantId)
                ->where('product_id', $validated['product_id'])
                ->where('warehouse', $validated['warehouse'])
                ->first();

            return response()->json($stock->load('product'), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function release(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:1',
            'reference' => 'required|string|max:255',
        ]);

        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }

        try {
            $this->stockService->releaseStock(
                $tenantId,
                $validated['product_id'],
                $validated['quantity'],
                $validated['warehouse'],
                $validated['reference']
            );

            $stock = Stock::where('tenant_id', $tenantId)
                ->where('product_id', $validated['product_id'])
                ->where('warehouse', $validated['warehouse'])
                ->first();

            return response()->json($stock->load('product'), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function transfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'from_warehouse' => 'required|string|max:255',
            'to_warehouse' => 'required|string|max:255|different:from_warehouse',
            'quantity' => 'required|numeric|min:1',
        ]);

        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }

        try {
            $this->stockService->transferStock(
                $tenantId,
                $validated['product_id'],
                $validated['quantity'],
                $validated['from_warehouse'],
                $validated['to_warehouse']
            );

            $stock = Stock::where('tenant_id', $tenantId)
                ->where('product_id', $validated['product_id'])
                ->where('warehouse', $validated['to_warehouse'])
                ->first();

            return response()->json($stock->load('product'), 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function lowStock(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }
        $warehouse = $request->query('warehouse');
        
        $query = Stock::where('tenant_id', $tenantId)
            ->where('available', '<=', 10)
            ->with(['product:id,name,code,unit,min_stock,purchase_price,selling_price']);
        
        if ($warehouse) {
            $query->where('warehouse', $warehouse);
        }
        
        $lowStockProducts = $query->get()->map(function ($stock) {
            $minStock = $stock->product->min_stock ?? 10;
            $toOrder = max(0, $minStock - $stock->available);
            return [
                'id' => $stock->id,
                'product_id' => $stock->product_id,
                'product_name' => $stock->product->name ?? 'N/A',
                'product_code' => $stock->product->code ?? '',
                'warehouse' => $stock->warehouse,
                'current_stock' => $stock->available,
                'min_stock' => $minStock,
                'quantity_to_order' => $toOrder,
                'is_out_of_stock' => $stock->available <= 0,
                'unit' => $stock->product->unit ?? 'unité',
                'purchase_price' => $stock->product->purchase_price ?? 0,
                'estimated_cost' => $toOrder * ($stock->product->purchase_price ?? 0),
            ];
        });

        // Séparer les ruptures et les stocks bas
        $outOfStock = $lowStockProducts->where('is_out_of_stock', true)->values();
        $lowButAvailable = $lowStockProducts->where('is_out_of_stock', false)->values();

        return response()->json([
            'count' => $lowStockProducts->count(),
            'out_of_stock_count' => $outOfStock->count(),
            'low_stock_count' => $lowButAvailable->count(),
            'out_of_stock' => $outOfStock,
            'low_stock' => $lowButAvailable,
            'products' => $lowStockProducts,
            'total_estimated_cost' => $lowStockProducts->sum('estimated_cost'),
        ]);
    }

    /**
     * Alertes de stock pour le gérant
     */
    public function alerts(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }
        $warehouse = $request->query('warehouse', 'Magasin de détail');
        
        // Produits en rupture de stock
        $outOfStock = Stock::where('tenant_id', $tenantId)
            ->where('warehouse', $warehouse)
            ->where('available', '<=', 0)
            ->with(['product:id,name,code,unit'])
            ->get()
            ->map(fn($s) => [
                'product_id' => $s->product_id,
                'name' => $s->product->name ?? 'N/A',
                'code' => $s->product->code ?? '',
                'warehouse' => $s->warehouse,
                'available' => $s->available,
                'alert_type' => 'out_of_stock',
                'message' => "Rupture de stock: {$s->product->name}",
            ]);

        // Produits sous le minimum
        $lowStock = Stock::where('tenant_id', $tenantId)
            ->where('warehouse', $warehouse)
            ->where('available', '>', 0)
            ->where('available', '<=', 10)
            ->with(['product:id,name,code,unit,min_stock'])
            ->get()
            ->map(fn($s) => [
                'product_id' => $s->product_id,
                'name' => $s->product->name ?? 'N/A',
                'code' => $s->product->code ?? '',
                'warehouse' => $s->warehouse,
                'available' => $s->available,
                'min_stock' => $s->product->min_stock ?? 10,
                'alert_type' => 'low_stock',
                'message' => "Stock bas: {$s->product->name} ({$s->available} restants)",
            ]);

        return response()->json([
            'alerts' => $outOfStock->merge($lowStock)->values(),
            'out_of_stock_count' => $outOfStock->count(),
            'low_stock_count' => $lowStock->count(),
            'total_alerts' => $outOfStock->count() + $lowStock->count(),
        ]);
    }

    /**
     * Générer un bon de commande automatique pour les produits à commander
     */
    public function suggestedOrders(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }
        $warehouse = $request->query('warehouse');
        
        $query = Stock::where('tenant_id', $tenantId)
            ->where('available', '<=', 10)
            ->with(['product:id,name,code,unit,min_stock,max_stock,purchase_price,supplier_id', 'product.supplier:id,name']);
        
        if ($warehouse) {
            $query->where('warehouse', $warehouse);
        }
        
        $toOrder = $query->get()->map(function ($stock) {
            $minStock = $stock->product->min_stock ?? 10;
            $maxStock = $stock->product->max_stock ?? ($minStock * 3);
            $qtyToOrder = max(0, $maxStock - $stock->available);
            
            return [
                'product_id' => $stock->product_id,
                'product_name' => $stock->product->name ?? 'N/A',
                'product_code' => $stock->product->code ?? '',
                'supplier_id' => $stock->product->supplier_id,
                'supplier_name' => $stock->product->supplier->name ?? 'Non défini',
                'warehouse' => $stock->warehouse,
                'current_stock' => $stock->available,
                'min_stock' => $minStock,
                'max_stock' => $maxStock,
                'quantity_to_order' => $qtyToOrder,
                'unit' => $stock->product->unit ?? 'unité',
                'unit_price' => $stock->product->purchase_price ?? 0,
                'total_price' => $qtyToOrder * ($stock->product->purchase_price ?? 0),
                'status' => $stock->available <= 0 ? 'URGENT' : 'A commander',
            ];
        })->filter(fn($item) => $item['quantity_to_order'] > 0);

        // Grouper par fournisseur
        $bySupplier = $toOrder->groupBy('supplier_name')->map(function ($items, $supplierName) {
            return [
                'supplier_name' => $supplierName,
                'supplier_id' => $items->first()['supplier_id'],
                'items_count' => $items->count(),
                'total_amount' => $items->sum('total_price'),
                'items' => $items->values(),
            ];
        })->values();

        return response()->json([
            'products_to_order' => $toOrder->values(),
            'by_supplier' => $bySupplier,
            'total_products' => $toOrder->count(),
            'total_amount' => $toOrder->sum('total_price'),
            'urgent_count' => $toOrder->where('status', 'URGENT')->count(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant non trouvé'], 400);
        }
        
        // Cache de 60 secondes pour le summary
        $cacheKey = "stock_summary_{$tenantId}";
        
            $summary = [
                'total_items' => Stock::where('tenant_id', $tenantId)->count(),
                'total_quantity' => Stock::where('tenant_id', $tenantId)->sum('quantity'),
                'total_available' => Stock::where('tenant_id', $tenantId)->sum('available'),
                'total_reserved' => Stock::where('tenant_id', $tenantId)->sum('reserved'),
                'total_value' => Stock::where('tenant_id', $tenantId)
                    ->selectRaw('SUM(available * unit_cost) as value')
                    ->value('value') ?? 0,
                'low_stock_count' => Stock::where('tenant_id', $tenantId)
                    ->where('available', '<=', 10)
                    ->count(),
            ];
        

        return response()->json($summary);
    }
}
