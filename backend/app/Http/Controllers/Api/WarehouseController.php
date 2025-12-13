<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class WarehouseController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Lister les entrepôts du tenant
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenantId = auth()->guard('sanctum')->user()->tenant_id;

            $query = Warehouse::where('tenant_id', $tenantId)
                ->where('is_active', true);

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            $warehouses = $query->with(['stocks' => function ($q) {
                $q->select('id', 'warehouse_id', 'product_id', 'quantity', 'cost_average');
            }])->get();

            $warehouses = $warehouses->map(function ($warehouse) {
                return [
                    'id' => $warehouse->id,
                    'code' => $warehouse->code,
                    'name' => $warehouse->name,
                    'type' => $warehouse->type,
                    'location' => $warehouse->location,
                    'max_capacity' => $warehouse->max_capacity,
                    'total_stock' => $warehouse->getTotalStock(),
                    'stock_value' => $warehouse->getStockValue(),
                    'is_active' => $warehouse->is_active,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $warehouses,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Créer un nouvel entrepôt
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $tenantId = auth()->guard('sanctum')->user()->tenant_id;
            
            $validated = $request->validate([
                'code' => [
                    'required',
                    'string',
                    \Illuminate\Validation\Rule::unique('warehouses', 'code')->where('tenant_id', $tenantId),
                ],
                'name' => 'required|string',
                'type' => 'required|in:gros,detail,pos',
                'location' => 'nullable|string',
                'max_capacity' => 'nullable|numeric|min:0',
            ]);

            $warehouse = Warehouse::create([
                'tenant_id' => $tenantId,
                'code' => $validated['code'],
                'name' => $validated['name'],
                'type' => $validated['type'],
                'location' => $validated['location'] ?? null,
                'max_capacity' => $validated['max_capacity'] ?? null,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Entrepôt créé',
                'data' => $warehouse,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Obtenir les détails d'un entrepôt avec ses stocks
     */
    public function show(Warehouse $warehouse): JsonResponse
    {
        try {
            $this->authorize('view', $warehouse);

            $stocks = $warehouse->stocks()->with('product')->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'warehouse' => $warehouse,
                    'stocks' => $stocks->map(function ($stock) {
                        return [
                            'product_id' => $stock->product_id,
                            'product_name' => $stock->product->name,
                            'product_sku' => $stock->product->code,
                            'quantity' => $stock->quantity,
                            'reserved' => $stock->reserved,
                            'available' => $stock->available,
                            'cost_average' => $stock->cost_average,
                            'value' => $stock->quantity * $stock->cost_average,
                        ];
                    }),
                    'total_stock' => $warehouse->getTotalStock(),
                    'total_value' => $warehouse->getStockValue(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Mettre à jour un entrepôt
     */
    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        try {
            $this->authorize('update', $warehouse);

            $validated = $request->validate([
                'name' => 'nullable|string',
                'location' => 'nullable|string',
                'max_capacity' => 'nullable|numeric|min:0',
                'is_active' => 'nullable|boolean',
            ]);

            $warehouse->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Entrepôt mis à jour',
                'data' => $warehouse,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Supprimer un entrepôt (soft delete)
     */
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        try {
            $this->authorize('delete', $warehouse);

            // Vérifier s'il y a du stock
            if ($warehouse->getTotalStock() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer un entrepôt contenant du stock',
                ], 422);
            }

            $warehouse->delete();

            return response()->json([
                'success' => true,
                'message' => 'Entrepôt supprimé',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Obtenir la valeur totale du stock d'un entrepôt
     */
    public function stockValue(Warehouse $warehouse): JsonResponse
    {
        try {
            $this->authorize('view', $warehouse);

            $stocks = $warehouse->stocks()->get();

            $value = $stocks->sum(fn ($stock) => $stock->quantity * $stock->cost_average);

            return response()->json([
                'success' => true,
                'data' => [
                    'warehouse_id' => $warehouse->id,
                    'warehouse_name' => $warehouse->name,
                    'total_quantity' => $warehouse->getTotalStock(),
                    'total_value' => $value,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Obtenir les mouvements de stock d'un entrepôt
     */
    public function movements(Request $request, Warehouse $warehouse): JsonResponse
    {
        try {
            $this->authorize('view', $warehouse);

            $query = $warehouse->stockMovementsFrom()
                ->orWhere(function ($q) use ($warehouse) {
                    $q->where('to_warehouse_id', $warehouse->id);
                });

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            $movements = $query->with('product', 'user')
                ->latest()
                ->paginate(50);

            return response()->json([
                'success' => true,
                'data' => $movements,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
