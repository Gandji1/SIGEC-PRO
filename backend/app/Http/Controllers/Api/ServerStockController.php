<?php

namespace App\Http\Controllers\Api;

use App\Models\ServerStock;
use App\Models\ServerStockMovement;
use App\Models\ServerReconciliation;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Product;
use App\Models\User;
use App\Models\Tenant;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Contrôleur pour la gestion du stock délégué aux serveurs (Option B)
 * 
 * Workflow Option B:
 * 1. Gérant délègue du stock à un serveur
 * 2. Serveur vend depuis son stock délégué
 * 3. Serveur fait le point (réconciliation) avec le gérant
 * 4. Gérant valide et récupère le stock restant + argent
 */
class ServerStockController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Vérifier si le tenant utilise l'Option B
     */
    private function checkOptionB(): bool
    {
        $tenant = Tenant::find(auth()->user()->tenant_id);
        return $tenant && $tenant->pos_option === 'B';
    }

    /**
     * Liste des stocks délégués
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;

        $query = ServerStock::where('tenant_id', $tenantId)
            ->with(['server', 'product', 'pos', 'delegatedByUser']);

        // Filtres
        if ($request->has('server_id')) {
            $query->where('server_id', $request->server_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        if ($request->has('date')) {
            $query->whereDate('delegated_at', $request->date);
        }

        // Pour les serveurs, filtrer par leur propre stock
        if ($user->isServer()) {
            $query->where('server_id', $user->id);
        }

        $stocks = $query->orderBy('delegated_at', 'desc')
            ->limit($request->get('limit', 100))
            ->get();

        return response()->json(['data' => $stocks]);
    }

    /**
     * Déléguer du stock à un serveur (GÉRANT UNIQUEMENT)
     */
    public function delegate(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isGerant() && !$user->isManagement()) {
            return response()->json([
                'message' => 'Seul le gérant peut déléguer du stock',
                'error' => 'ROLE_NOT_ALLOWED'
            ], 403);
        }

        $validated = $request->validate([
            'server_id' => 'required|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'pos_id' => 'nullable|exists:pos,id',
            'notes' => 'nullable|string',
        ]);

        $tenantId = $user->tenant_id;

        // Vérifier que le serveur appartient au même tenant
        $server = User::where('id', $validated['server_id'])
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$server) {
            return response()->json([
                'message' => 'Serveur non trouvé',
                'error' => 'SERVER_NOT_FOUND'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $delegatedStocks = [];

            foreach ($validated['items'] as $item) {
                $product = Product::where('id', $item['product_id'])
                    ->where('tenant_id', $tenantId)
                    ->first();

                if (!$product) {
                    throw new \Exception("Produit {$item['product_id']} non trouvé");
                }

                // Vérifier le stock disponible dans l'entrepôt
                $warehouseStock = Stock::where('tenant_id', $tenantId)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (!$warehouseStock || $warehouseStock->quantity < $item['quantity']) {
                    throw new \Exception("Stock insuffisant pour {$product->name}");
                }

                $unitPrice = $item['unit_price'] ?? $product->selling_price;
                $unitCost = $warehouseStock->cost_average ?? $warehouseStock->unit_cost ?? $product->purchase_price ?? 0;

                // Créer le stock délégué
                $serverStock = ServerStock::create([
                    'tenant_id' => $tenantId,
                    'server_id' => $validated['server_id'],
                    'product_id' => $item['product_id'],
                    'pos_id' => $validated['pos_id'] ?? null,
                    'delegated_by' => $user->id,
                    'quantity_delegated' => $item['quantity'],
                    'quantity_remaining' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost,
                    'status' => 'active',
                    'delegated_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                ]);

                // Enregistrer le mouvement de délégation
                ServerStockMovement::create([
                    'tenant_id' => $tenantId,
                    'server_stock_id' => $serverStock->id,
                    'server_id' => $validated['server_id'],
                    'product_id' => $item['product_id'],
                    'performed_by' => $user->id,
                    'type' => 'delegation',
                    'quantity' => $item['quantity'],
                    'quantity_before' => 0,
                    'quantity_after' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'total_amount' => $item['quantity'] * $unitPrice,
                    'reference' => ServerStock::generateReference($tenantId),
                ]);

                // Déduire du stock principal
                $warehouseStock->decrement('quantity', $item['quantity']);

                // Enregistrer le mouvement de stock principal
                StockMovement::create([
                    'tenant_id' => $tenantId,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $warehouseStock->warehouse_id,
                    'type' => 'delegation',
                    'quantity' => -$item['quantity'],
                    'unit_cost' => $unitCost,
                    'reference' => "DELEG-{$serverStock->id}",
                    'notes' => "Délégation au serveur {$server->name}",
                    'user_id' => $user->id,
                ]);

                $delegatedStocks[] = $serverStock->load('product');
            }

            DB::commit();

            Log::info("Stock délégué au serveur", [
                'server_id' => $validated['server_id'],
                'manager_id' => $user->id,
                'items_count' => count($delegatedStocks)
            ]);

            return response()->json([
                'message' => 'Stock délégué avec succès',
                'data' => $delegatedStocks
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur délégation stock: " . $e->getMessage());
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Stock actif d'un serveur (pour le serveur lui-même)
     */
    public function myStock(): JsonResponse
    {
        $user = auth()->user();

        $stocks = ServerStock::where('tenant_id', $user->tenant_id)
            ->where('server_id', $user->id)
            ->where('status', 'active')
            ->with(['product', 'pos'])
            ->get();

        $totalValue = $stocks->sum(fn($s) => $s->quantity_remaining * $s->unit_price);
        $totalSales = $stocks->sum('total_sales_amount');

        return response()->json([
            'data' => $stocks,
            'summary' => [
                'total_products' => $stocks->count(),
                'total_remaining_value' => $totalValue,
                'total_sales' => $totalSales,
            ]
        ]);
    }

    /**
     * Enregistrer une vente depuis le stock délégué (SERVEUR)
     */
    public function recordSale(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.server_stock_id' => 'required|exists:server_stocks,id',
            'items.*.quantity' => 'required|integer|min:1',
            'pos_order_id' => 'nullable|exists:pos_orders,id',
            'notes' => 'nullable|string',
        ]);

        $tenantId = $user->tenant_id;

        try {
            DB::beginTransaction();

            $totalAmount = 0;

            foreach ($validated['items'] as $item) {
                $serverStock = ServerStock::where('id', $item['server_stock_id'])
                    ->where('tenant_id', $tenantId)
                    ->where('server_id', $user->id)
                    ->where('status', 'active')
                    ->first();

                if (!$serverStock) {
                    throw new \Exception("Stock délégué non trouvé ou inactif");
                }

                if (!$serverStock->recordSale(
                    $item['quantity'],
                    $serverStock->unit_price,
                    $validated['pos_order_id'] ?? null
                )) {
                    throw new \Exception("Stock insuffisant pour {$serverStock->product->name}");
                }

                $totalAmount += $item['quantity'] * $serverStock->unit_price;
            }

            DB::commit();

            return response()->json([
                'message' => 'Vente enregistrée',
                'total_amount' => $totalAmount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Déclarer une perte (SERVEUR)
     */
    public function declareLoss(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'server_stock_id' => 'required|exists:server_stocks,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:500',
        ]);

        $serverStock = ServerStock::where('id', $validated['server_stock_id'])
            ->where('tenant_id', $user->tenant_id)
            ->where('server_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$serverStock) {
            return response()->json([
                'message' => 'Stock délégué non trouvé',
                'error' => 'NOT_FOUND'
            ], 404);
        }

        if (!$serverStock->declareLoss($validated['quantity'], $validated['reason'])) {
            return response()->json([
                'message' => 'Quantité insuffisante',
                'error' => 'INSUFFICIENT_QUANTITY'
            ], 422);
        }

        return response()->json([
            'message' => 'Perte déclarée',
            'data' => $serverStock->fresh()
        ]);
    }

    /**
     * Initier la réconciliation (SERVEUR)
     */
    public function startReconciliation(Request $request): JsonResponse
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;

        // Vérifier s'il y a des stocks actifs
        $activeStocks = ServerStock::where('tenant_id', $tenantId)
            ->where('server_id', $user->id)
            ->where('status', 'active')
            ->count();

        if ($activeStocks === 0) {
            return response()->json([
                'message' => 'Aucun stock actif à réconcilier',
                'error' => 'NO_ACTIVE_STOCK'
            ], 422);
        }

        // Vérifier s'il y a déjà une réconciliation ouverte
        $existingReconciliation = ServerReconciliation::where('tenant_id', $tenantId)
            ->where('server_id', $user->id)
            ->whereIn('status', ['open', 'pending'])
            ->first();

        if ($existingReconciliation) {
            return response()->json([
                'message' => 'Une réconciliation est déjà en cours',
                'data' => $existingReconciliation
            ], 422);
        }

        $reconciliation = ServerReconciliation::create([
            'tenant_id' => $tenantId,
            'server_id' => $user->id,
            'reference' => ServerReconciliation::generateReference($tenantId),
            'session_start' => now()->startOfDay(),
            'status' => 'open',
        ]);

        return response()->json([
            'message' => 'Réconciliation initiée',
            'data' => $reconciliation
        ], 201);
    }

    /**
     * Soumettre la réconciliation pour validation (SERVEUR)
     */
    public function submitReconciliation(Request $request, $id): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'cash_collected' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $reconciliation = ServerReconciliation::where('id', $id)
            ->where('tenant_id', $user->tenant_id)
            ->where('server_id', $user->id)
            ->where('status', 'open')
            ->first();

        if (!$reconciliation) {
            return response()->json([
                'message' => 'Réconciliation non trouvée',
                'error' => 'NOT_FOUND'
            ], 404);
        }

        $reconciliation->submitForValidation(
            $validated['cash_collected'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Réconciliation soumise pour validation',
            'data' => $reconciliation->fresh()
        ]);
    }

    /**
     * Liste des réconciliations en attente (GÉRANT)
     */
    public function pendingReconciliations(): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isGerant() && !$user->isManagement()) {
            return response()->json([
                'message' => 'Accès non autorisé',
                'error' => 'ROLE_NOT_ALLOWED'
            ], 403);
        }

        $reconciliations = ServerReconciliation::where('tenant_id', $user->tenant_id)
            ->where('status', 'pending')
            ->with(['server', 'pos'])
            ->orderBy('session_end', 'desc')
            ->get();

        return response()->json(['data' => $reconciliations]);
    }

    /**
     * Valider une réconciliation (GÉRANT)
     */
    public function validateReconciliation(Request $request, $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isGerant() && !$user->isManagement()) {
            return response()->json([
                'message' => 'Seul le gérant peut valider',
                'error' => 'ROLE_NOT_ALLOWED'
            ], 403);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $reconciliation = ServerReconciliation::where('id', $id)
            ->where('tenant_id', $user->tenant_id)
            ->where('status', 'pending')
            ->first();

        if (!$reconciliation) {
            return response()->json([
                'message' => 'Réconciliation non trouvée',
                'error' => 'NOT_FOUND'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $reconciliation->validate($user->id, $validated['notes'] ?? null);

            // Enregistrer le mouvement de caisse
            $session = CashRegisterSession::getOpenSession($user->tenant_id);
            CashMovement::record(
                $user->tenant_id,
                $user->id,
                'in',
                'reconciliation',
                $reconciliation->cash_collected,
                "Réconciliation serveur {$reconciliation->server->name} - {$reconciliation->reference}",
                $session?->id,
                $reconciliation->pos_id,
                'cash',
                $reconciliation->id,
                'server_reconciliation'
            );

            DB::commit();

            Log::info("Réconciliation validée", [
                'reconciliation_id' => $id,
                'manager_id' => $user->id,
                'cash_collected' => $reconciliation->cash_collected
            ]);

            return response()->json([
                'message' => 'Réconciliation validée',
                'data' => $reconciliation->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Contester une réconciliation (GÉRANT)
     */
    public function disputeReconciliation(Request $request, $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isGerant() && !$user->isManagement()) {
            return response()->json([
                'message' => 'Seul le gérant peut contester',
                'error' => 'ROLE_NOT_ALLOWED'
            ], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $reconciliation = ServerReconciliation::where('id', $id)
            ->where('tenant_id', $user->tenant_id)
            ->where('status', 'pending')
            ->first();

        if (!$reconciliation) {
            return response()->json([
                'message' => 'Réconciliation non trouvée',
                'error' => 'NOT_FOUND'
            ], 404);
        }

        $reconciliation->dispute($user->id, $validated['reason']);

        return response()->json([
            'message' => 'Réconciliation contestée',
            'data' => $reconciliation->fresh()
        ]);
    }

    /**
     * Historique des mouvements de stock serveur
     */
    public function movements(Request $request): JsonResponse
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;

        $query = ServerStockMovement::where('tenant_id', $tenantId)
            ->with(['server', 'product', 'performedByUser']);

        if ($user->isServer()) {
            $query->where('server_id', $user->id);
        }

        if ($request->has('server_id')) {
            $query->where('server_id', $request->server_id);
        }
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $movements = $query->orderBy('created_at', 'desc')
            ->limit($request->get('limit', 100))
            ->get();

        return response()->json(['data' => $movements]);
    }

    /**
     * Statistiques Option B (GÉRANT)
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;

        $from = $request->query('from', now()->startOfDay()->toDateTimeString());
        $to = $request->query('to', now()->endOfDay()->toDateTimeString());

        // Stocks délégués
        $delegatedStocks = ServerStock::where('tenant_id', $tenantId)
            ->whereBetween('delegated_at', [$from, $to])
            ->get();

        $totalDelegated = $delegatedStocks->sum(fn($s) => $s->quantity_delegated * $s->unit_price);
        $totalSales = $delegatedStocks->sum('total_sales_amount');
        $totalReturned = $delegatedStocks->sum(fn($s) => $s->quantity_returned * $s->unit_price);
        $totalLosses = $delegatedStocks->sum(fn($s) => $s->quantity_lost * $s->unit_price);

        // Par serveur
        $byServer = $delegatedStocks->groupBy('server_id')->map(function ($stocks, $serverId) {
            $server = User::find($serverId);
            return [
                'server_id' => $serverId,
                'server_name' => $server?->name ?? 'Inconnu',
                'total_delegated' => $stocks->sum(fn($s) => $s->quantity_delegated * $s->unit_price),
                'total_sales' => $stocks->sum('total_sales_amount'),
                'total_returned' => $stocks->sum(fn($s) => $s->quantity_returned * $s->unit_price),
                'total_losses' => $stocks->sum(fn($s) => $s->quantity_lost * $s->unit_price),
            ];
        })->values();

        // Réconciliations
        $reconciliations = ServerReconciliation::where('tenant_id', $tenantId)
            ->whereBetween('session_start', [$from, $to])
            ->get();

        return response()->json([
            'period' => ['from' => $from, 'to' => $to],
            'totals' => [
                'delegated_value' => $totalDelegated,
                'sales' => $totalSales,
                'returned_value' => $totalReturned,
                'losses_value' => $totalLosses,
                'gross_profit' => $totalSales - $delegatedStocks->sum(fn($s) => $s->quantity_sold * $s->unit_cost),
            ],
            'by_server' => $byServer,
            'reconciliations' => [
                'total' => $reconciliations->count(),
                'validated' => $reconciliations->where('status', 'validated')->count(),
                'pending' => $reconciliations->where('status', 'pending')->count(),
                'disputed' => $reconciliations->where('status', 'disputed')->count(),
            ]
        ]);
    }
}
