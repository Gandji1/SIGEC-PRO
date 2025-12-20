<?php

namespace App\Http\Controllers\Api;

use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosTable;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Contrôleur pour la gestion des commandes POS
 * 
 * Workflow:
 * 1. Serveur crée la commande (status: pending, preparation_status: pending)
 * 2. Gérant approuve (preparation_status: approved)
 * 3. Préparation (preparation_status: preparing -> ready)
 * 4. Serveur sert (preparation_status: served)
 * 5. Serveur initie le paiement (payment_status: processing)
 * 6. Gérant valide le paiement (payment_status: confirmed, status: validated)
 */
class PosOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Liste des commandes POS
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        $limit = min($request->get('limit', 50), 200);

        $query = PosOrder::where('tenant_id', $tenantId)
            ->select(['id', 'reference', 'pos_id', 'table_number', 'customer_id', 'status', 'preparation_status', 'payment_status', 'payment_method', 'subtotal', 'total', 'created_by', 'served_by', 'created_at', 'validated_at'])
            ->with([
                'items:id,pos_order_id,product_id,quantity_ordered,quantity_served,unit_price,line_total',
                'items.product:id,name,code,unit',
                'createdByUser:id,name',
                'servedByUser:id,name',
                'pos:id,name,code'
            ]);

        // Filtres
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('preparation_status')) {
            $query->where('preparation_status', $request->preparation_status);
        }
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->has('pos_id')) {
            $query->where('pos_id', $request->pos_id);
        }
        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // Pour les serveurs, filtrer par leurs commandes uniquement
        if ($user->isServer()) {
            $query->where('created_by', $user->id);
        }

        $orders = $query->orderBy('created_at', 'desc')->limit($limit)->get();

        return response()->json(['data' => $orders]);
    }

    /**
     * Créer une commande POS (SERVEUR UNIQUEMENT)
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Vérifier que seul un serveur peut créer une commande
        if (!$user->canCreateSales()) {
            return response()->json([
                'message' => 'Seuls les serveurs peuvent créer des commandes',
                'error' => 'ROLE_NOT_ALLOWED'
            ], 403);
        }

        $validated = $request->validate([
            'pos_id' => 'required|exists:pos,id',
            'table_id' => 'nullable|exists:pos_tables,id',
            'table_number' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $tenantId = $user->tenant_id;

        // Vérifier affiliation au POS (seulement si le serveur a des affiliations configurées)
        $hasAnyPosAffiliation = $user->affiliatedPos()->exists() || $user->assigned_pos_id;
        if ($hasAnyPosAffiliation) {
            if (!$user->isAffiliatedToPos($validated['pos_id']) && $user->assigned_pos_id != $validated['pos_id']) {
                return response()->json([
                    'message' => 'Vous n\'êtes pas affilié à ce POS',
                    'error' => 'NOT_AFFILIATED'
                ], 403);
            }
        }

        // Vérifier affiliation à la table (seulement si le serveur a des affiliations tables configurées)
        if (!empty($validated['table_id'])) {
            $hasAnyTableAffiliation = $user->affiliatedTables()->exists();
            if ($hasAnyTableAffiliation && !$user->isAffiliatedToTable($validated['table_id'])) {
                return response()->json([
                    'message' => 'Vous n\'êtes pas affilié à cette table',
                    'error' => 'NOT_AFFILIATED'
                ], 403);
            }
        }

        try {
            DB::beginTransaction();

            // Vérifier le stock disponible pour chaque produit
            $tenant = \App\Models\Tenant::find($tenantId);
            $checkStock = $tenant->mode_pos !== 'restaurant'; // En mode commerce, vérifier le stock
            
            if ($checkStock) {
                foreach ($validated['items'] as $item) {
                    $product = Product::find($item['product_id']);
                    $stock = Stock::where('tenant_id', $tenantId)
                        ->where('product_id', $item['product_id'])
                        ->sum('quantity');
                    
                    if ($stock < $item['quantity']) {
                        return response()->json([
                            'message' => "Stock insuffisant pour {$product->name}. Disponible: {$stock}, Demandé: {$item['quantity']}",
                            'error' => 'INSUFFICIENT_STOCK',
                            'product' => $product->name,
                            'available' => $stock,
                            'requested' => $item['quantity']
                        ], 422);
                    }
                }
            }

            // Créer la commande
            $order = PosOrder::create([
                'tenant_id' => $tenantId,
                'pos_id' => $validated['pos_id'],
                'table_number' => $validated['table_number'] ?? null,
                'customer_id' => $validated['customer_id'] ?? null,
                'reference' => PosOrder::generateReference($tenantId),
                'status' => 'pending',
                'preparation_status' => 'pending',
                'payment_status' => 'pending',
                'created_by' => $user->id,
                'notes' => $validated['notes'] ?? null,
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total' => 0,
            ]);

            // Ajouter les items
            $subtotal = 0;
            $taxAmount = 0;

            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                $unitPrice = $item['unit_price'] ?? $product->selling_price;
                $lineTotal = $unitPrice * $item['quantity'];
                $lineTax = $lineTotal * ($product->tax_percent ?? 0) / 100;
                
                // Récupérer le coût unitaire (CMP = Coût Moyen Pondéré) depuis le stock
                $stock = Stock::where('tenant_id', $tenantId)
                    ->where('product_id', $item['product_id'])
                    ->first();
                // Utiliser cost_average (CMP) en priorité, sinon unit_cost, sinon purchase_price
                $unitCost = $stock?->cost_average ?? $stock?->unit_cost ?? $product->purchase_price ?? 0;

                PosOrderItem::create([
                    'pos_order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'quantity_ordered' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost, // Coût d'achat (CMP) pour calcul CMV
                    'tax_percent' => $product->tax_percent ?? 0,
                    'line_total' => $lineTotal,
                    'notes' => $item['notes'] ?? null,
                ]);

                $subtotal += $lineTotal;
                $taxAmount += $lineTax;
            }

            // Mettre à jour les totaux
            $order->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $subtotal + $taxAmount,
            ]);

            // Mettre à jour le statut de la table si spécifiée
            if (!empty($validated['table_id'])) {
                PosTable::where('id', $validated['table_id'])
                    ->update([
                        'status' => 'occupied',
                        'current_order_id' => $order->id
                    ]);
            }

            DB::commit();

            Log::info("Commande POS créée", [
                'order_id' => $order->id,
                'reference' => $order->reference,
                'server_id' => $user->id,
                'total' => $order->total
            ]);

            return response()->json([
                'message' => 'Commande créée avec succès',
                'data' => $order->load('items.product')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur création commande POS: " . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Détail d'une commande
     */
    public function show($id): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        
        $order = PosOrder::where('tenant_id', $tenantId)
            ->with(['items.product', 'createdByUser', 'servedByUser', 'validatedByUser', 'pos', 'customer'])
            ->findOrFail($id);

        return response()->json(['data' => $order]);
    }

    /**
     * Approuver une commande (GÉRANT UNIQUEMENT)
     */
    public function approve($id): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isGerant() && !$user->isManagement()) {
            return response()->json([
                'message' => 'Seul le gérant peut approuver les commandes',
                'error' => 'ROLE_NOT_ALLOWED'
            ], 403);
        }

        $order = PosOrder::where('tenant_id', $user->tenant_id)->findOrFail($id);

        if ($order->preparation_status !== 'pending') {
            return response()->json([
                'message' => 'Cette commande a déjà été traitée',
                'error' => 'ALREADY_PROCESSED'
            ], 422);
        }

        $order->update([
            'preparation_status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        Log::info("Commande approuvée", ['order_id' => $id, 'manager_id' => $user->id]);

        return response()->json([
            'message' => 'Commande approuvée',
            'data' => $order->fresh()
        ]);
    }

    /**
     * Marquer comme en préparation
     */
    public function startPreparing($id): JsonResponse
    {
        $user = auth()->user();
        $order = PosOrder::where('tenant_id', $user->tenant_id)->findOrFail($id);

        if ($order->preparation_status !== 'approved') {
            return response()->json([
                'message' => 'La commande doit être approuvée avant la préparation',
                'error' => 'NOT_APPROVED'
            ], 422);
        }

        $order->update(['preparation_status' => 'preparing']);

        return response()->json([
            'message' => 'Préparation en cours',
            'data' => $order->fresh()
        ]);
    }

    /**
     * Marquer comme prête
     */
    public function markReady($id): JsonResponse
    {
        $user = auth()->user();
        $order = PosOrder::where('tenant_id', $user->tenant_id)->findOrFail($id);

        if ($order->preparation_status !== 'preparing') {
            return response()->json([
                'message' => 'La commande doit être en préparation',
                'error' => 'NOT_PREPARING'
            ], 422);
        }

        $order->update(['preparation_status' => 'ready']);

        return response()->json([
            'message' => 'Commande prête',
            'data' => $order->fresh()
        ]);
    }

    /**
     * Servir la commande (GÉRANT) - Déduit le stock
     */
    public function serve($id): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isGerant() && !$user->isManagement()) {
            return response()->json([
                'message' => 'Seul le gérant peut servir les commandes',
                'error' => 'ROLE_NOT_ALLOWED'
            ], 403);
        }

        $order = PosOrder::where('tenant_id', $user->tenant_id)
            ->with('items.product')
            ->findOrFail($id);

        if (!in_array($order->preparation_status, ['approved', 'preparing', 'ready'])) {
            return response()->json([
                'message' => 'La commande doit être approuvée ou prête',
                'error' => 'NOT_READY'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Déduire le stock du magasin détail
            foreach ($order->items as $item) {
                $this->deductStock($order, $item);
            }

            $order->update([
                'preparation_status' => 'served',
                'served_by' => $user->id,
                'served_at' => now(),
                'status' => 'served',
            ]);

            DB::commit();

            Log::info("Commande servie - Stock déduit", [
                'order_id' => $id,
                'manager_id' => $user->id
            ]);

            return response()->json([
                'message' => 'Commande servie - Stock déduit',
                'data' => $order->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur service commande: " . $e->getMessage());
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Initier le paiement (SERVEUR)
     */
    public function initiatePayment(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        $order = PosOrder::where('tenant_id', $user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'payment_method' => 'required|in:cash,momo,card,fedapay,kakiapay,virement',
            'amount' => 'required|numeric|min:0',
            'payment_reference' => 'nullable|string',
        ]);

        if ($order->payment_status === 'confirmed') {
            return response()->json([
                'message' => 'Cette commande est déjà payée',
                'error' => 'ALREADY_PAID'
            ], 422);
        }

        $order->update([
            'payment_method' => $validated['payment_method'],
            'amount_paid' => $validated['amount'],
            'payment_reference' => $validated['payment_reference'] ?? null,
            'payment_status' => 'processing',
        ]);

        Log::info("Paiement initié", [
            'order_id' => $id,
            'method' => $validated['payment_method'],
            'amount' => $validated['amount']
        ]);

        return response()->json([
            'message' => 'Paiement en attente de validation par le gérant',
            'data' => $order->fresh()
        ]);
    }

    /**
     * Valider le paiement et la commande (GÉRANT UNIQUEMENT)
     * Crée le mouvement de caisse
     */
    public function validatePayment($id): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isGerant() && !$user->isManagement()) {
            return response()->json([
                'message' => 'Seul le gérant peut valider les paiements',
                'error' => 'ROLE_NOT_ALLOWED'
            ], 403);
        }

        $order = PosOrder::where('tenant_id', $user->tenant_id)->findOrFail($id);

        if ($order->payment_status === 'confirmed') {
            return response()->json([
                'message' => 'Ce paiement est déjà validé',
                'error' => 'ALREADY_VALIDATED'
            ], 422);
        }

        // Le gérant peut valider si la commande est servie (peu importe si paiement initié ou non)
        if ($order->preparation_status !== 'served' && $order->payment_status !== 'processing') {
            return response()->json([
                'message' => 'La commande doit être servie avant validation du paiement',
                'error' => 'NOT_SERVED'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Créer le mouvement de caisse
            $this->recordCashMovement($order, $user->id);

            // Mettre à jour la commande
            $order->update([
                'payment_status' => 'confirmed',
                'status' => 'validated',
                'validated_by' => $user->id,
                'validated_at' => now(),
                'paid_at' => now(),
            ]);

            // Libérer la table si applicable
            if ($order->table_number) {
                PosTable::where('tenant_id', $order->tenant_id)
                    ->where('number', $order->table_number)
                    ->update(['status' => 'cleaning', 'current_order_id' => null]);
            }

            DB::commit();

            Log::info("Paiement validé - Mouvement de caisse créé", [
                'order_id' => $id,
                'manager_id' => $user->id,
                'amount' => $order->total
            ]);

            return response()->json([
                'message' => 'Paiement validé - Commande terminée',
                'data' => $order->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur validation paiement: " . $e->getMessage());
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Commandes en attente pour le gérant (toutes les commandes non terminées)
     */
    public function pendingForManager(Request $request): JsonResponse
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;

        // Récupérer toutes les commandes non terminées (payment_status != confirmed)
        $orders = PosOrder::where('tenant_id', $tenantId)
            ->where('payment_status', '!=', 'confirmed')
            ->with(['items.product', 'createdByUser', 'pos'])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'data' => $orders,
            'counts' => [
                'pending_approval' => $orders->where('preparation_status', 'pending')->count(),
                'pending_service' => $orders->whereIn('preparation_status', ['approved', 'preparing', 'ready'])->count(),
                'pending_payment' => $orders->where('preparation_status', 'served')->where('payment_status', '!=', 'confirmed')->count(),
            ]
        ]);
    }

    /**
     * Historique des commandes groupé par serveur (GÉRANT)
     */
    public function historyByServer(Request $request): JsonResponse
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;

        // Filtres de date (par défaut: tout le mois en cours)
        $startDate = $request->get('start_date') 
            ? Carbon::parse($request->get('start_date'))->startOfDay() 
            : now()->startOfMonth();
        $endDate = $request->get('end_date') 
            ? Carbon::parse($request->get('end_date'))->endOfDay() 
            : now()->endOfDay();

        // Récupérer les commandes validées avec items et produits
        $orders = PosOrder::where('tenant_id', $tenantId)
            ->where('payment_status', 'confirmed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['createdByUser', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Grouper par serveur
        $byServer = $orders->groupBy('created_by')->map(function ($serverOrders, $serverId) {
            $server = $serverOrders->first()->createdByUser;
            return [
                'server_id' => $serverId,
                'server_name' => $server?->name ?? 'Inconnu',
                'total_orders' => $serverOrders->count(),
                'total_ca' => (float) $serverOrders->sum('total'),
                'orders' => $serverOrders->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'reference' => $order->reference,
                        'total' => (float) $order->total,
                        'items_count' => $order->items->count(),
                        'items' => $order->items->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'product_id' => $item->product_id,
                                'product_name' => $item->product?->name ?? 'Produit',
                                'quantity' => $item->quantity_ordered ?? $item->quantity ?? 1,
                                'unit_price' => (float) $item->unit_price,
                            ];
                        })->values(),
                        'approved_at' => $order->approved_at,
                        'served_at' => $order->served_at,
                        'validated_at' => $order->validated_at,
                        'created_at' => $order->created_at,
                    ];
                })->values(),
            ];
        })->values();

        // Stats globales
        $totalCA = $orders->sum('total');
        $totalOrders = $orders->count();

        return response()->json([
            'data' => $byServer,
            'summary' => [
                'total_orders' => $totalOrders,
                'total_ca' => (float) $totalCA,
                'servers_count' => $byServer->count(),
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ]
            ]
        ]);
    }

    /**
     * Déduire le stock du magasin détail
     */
    private function deductStock(PosOrder $order, PosOrderItem $item): void
    {
        $tenantId = $order->tenant_id;
        
        // Utiliser quantity_ordered ou quantity
        $qty = (int) ($item->quantity_ordered ?? $item->quantity ?? 1);
        
        // Trouver le stock du magasin détail
        $stock = Stock::where('tenant_id', $tenantId)
            ->where('product_id', $item->product_id)
            ->whereHas('warehouse', function($q) {
                $q->where('type', 'detail');
            })
            ->first();

        if (!$stock) {
            Log::warning("Stock non trouvé pour le produit", [
                'product_id' => $item->product_id,
                'order_id' => $order->id
            ]);
        }

        // Créer le mouvement de stock
        StockMovement::create([
            'tenant_id' => $tenantId,
            'product_id' => $item->product_id,
            'warehouse_id' => $stock?->warehouse_id,
            'type' => 'sale',
            'quantity' => -$qty,
            'unit_cost' => $item->unit_price,
            'reference' => "POS-{$order->reference}",
            'notes' => "Vente POS - Commande {$order->reference}",
            'user_id' => auth()->id(),
        ]);

        // Déduire du stock
        if ($stock && $qty > 0) {
            $stock->decrement('quantity', $qty);
        }
    }

    /**
     * Enregistrer le mouvement de caisse et mettre à jour la session
     */
    private function recordCashMovement(PosOrder $order, int $userId): void
    {
        $session = CashRegisterSession::getOpenSession($order->tenant_id);
        $paymentMethod = $order->payment_method ?? 'cash';
        $amount = (float) $order->total;

        // Utiliser la méthode record() qui met à jour automatiquement la session
        CashMovement::record(
            $order->tenant_id,
            $userId,
            'in',
            'sale',
            $amount,
            "Paiement commande POS {$order->reference}",
            $session?->id,
            $order->pos_id,
            $paymentMethod,
            $order->id,
            'pos_order'
        );

        Log::info("Mouvement de caisse enregistré pour commande POS", [
            'order_id' => $order->id,
            'reference' => $order->reference,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'session_id' => $session?->id
        ]);
    }

    /**
     * Statistiques de vente avec CMV (Coût des Marchandises Vendues)
     * Pour la gestion de caisse et le dashboard
     */
    public function salesStats(Request $request): JsonResponse
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        
        $from = $request->query('from', now()->startOfDay()->toDateTimeString());
        $to = $request->query('to', now()->endOfDay()->toDateTimeString());
        $sessionId = $request->query('session_id');

        $query = PosOrder::where('tenant_id', $tenantId)
            ->where('payment_status', 'confirmed')
            ->whereBetween('validated_at', [$from, $to])
            ->with('items');

        $orders = $query->get();

        // Calculer les totaux
        $totalCA = $orders->sum('total');
        $totalCMV = 0; // Coût des Marchandises Vendues (Charges Variables)
        
        foreach ($orders as $order) {
            $totalCMV += $order->getCostOfGoodsSold();
        }
        
        $beneficeBrut = $totalCA - $totalCMV;
        $margePercent = $totalCA > 0 ? ($beneficeBrut / $totalCA) * 100 : 0;

        // Statistiques par jour pour le graphique
        $dailyStats = $orders->groupBy(function ($order) {
            return Carbon::parse($order->validated_at)->format('Y-m-d');
        })->map(function ($dayOrders, $date) {
            $ca = $dayOrders->sum('total');
            $cmv = $dayOrders->sum(fn($o) => $o->getCostOfGoodsSold());
            return [
                'date' => $date,
                'date_formatted' => Carbon::parse($date)->format('d/m'),
                'ca' => $ca,
                'cmv' => $cmv, // Charges Variables
                'benefice_brut' => $ca - $cmv,
                'transactions' => $dayOrders->count(),
            ];
        })->values();

        return response()->json([
            'period' => ['from' => $from, 'to' => $to],
            'totals' => [
                'ca' => $totalCA,
                'cmv' => $totalCMV, // Charges Variables = Coût des Marchandises Vendues
                'benefice_brut' => $beneficeBrut,
                'marge_percent' => round($margePercent, 2),
                'transactions' => $orders->count(),
            ],
            'daily' => $dailyStats,
        ]);
    }
}
