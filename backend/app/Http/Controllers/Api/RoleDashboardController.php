<?php

namespace App\Http\Controllers\Api;

use App\Models\PosOrder;
use App\Models\Purchase;
use App\Models\Stock;
use App\Models\StockRequest;
use App\Models\Transfer;
use App\Models\Expense;
use App\Models\AccountingEntry;
use App\Models\CashRegisterSession;
use App\Models\CashRemittance;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RoleDashboardController extends Controller
{
    /**
     * Stats globales pour la page d'accueil (tous les rôles) - OPTIMISÉ
     */
    public function globalStats(Request $request)
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        $userRole = $user->role;

        // Cache 30 secondes pour performance
        $cacheKey = "global_stats_{$tenantId}_{$userRole}_" . now()->format('Y-m-d-H-i');
        
        $stats = Cache::remember($cacheKey, 30, function () use ($tenantId, $userRole) {
            // Pour SuperAdmin - stats plateforme
            if ($userRole === 'super_admin') {
                return [
                    'tenants_count' => \DB::table('tenants')->where('is_active', true)->count(),
                    'total_revenue' => \DB::table('pos_orders')->where('payment_status', 'confirmed')->sum('total') ?? 0,
                    'orders_today' => \DB::table('pos_orders')->whereDate('created_at', today())->count(),
                    'active_users' => \DB::table('users')->where('is_active', true)->count(),
                ];
            }
            
            // Pour les autres rôles - stats du tenant avec requête agrégée
            $todayStats = \DB::table('pos_orders')
                ->where('tenant_id', $tenantId)
                ->whereDate('created_at', today())
                ->selectRaw('COUNT(*) as total_count, SUM(CASE WHEN payment_status = \'confirmed\' THEN total ELSE 0 END) as confirmed_total')
                ->first();
            
            $monthTotal = \DB::table('pos_orders')
                ->where('tenant_id', $tenantId)
                ->whereMonth('created_at', now()->month)
                ->where('payment_status', 'confirmed')
                ->sum('total') ?? 0;

            $pendingCount = \DB::table('pos_orders')
                ->where('tenant_id', $tenantId)
                ->where('payment_status', '!=', 'confirmed')
                ->whereIn('preparation_status', ['pending', 'approved', 'preparing', 'ready'])
                ->count();

            $activeUsers = \DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->count();

            return [
                'entreprises' => 1,
                'chiffre_affaires' => (float) $monthTotal,
                'orders_today' => $todayStats->total_count ?? 0,
                'today_sales' => (float) ($todayStats->confirmed_total ?? 0),
                'active_users' => $activeUsers,
                'pending_orders' => $pendingCount,
            ];
        });

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * Dashboard Caissier - Stats POS - OPTIMISÉ
     */
    public function cashierStats(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $userId = $request->user()->id;
        $posId = $request->user()->assigned_pos_id;

        $cacheKey = "cashier_stats_{$tenantId}_{$userId}_" . now()->format('Y-m-d-H-i');

        $stats = Cache::remember($cacheKey, 30, function () use ($tenantId, $posId) {
            // Requête agrégée unique au lieu de ->get()
            $query = \DB::table('pos_orders')
                ->where('tenant_id', $tenantId)
                ->whereDate('created_at', today())
                ->where('status', 'completed');

            if ($posId) $query->where('pos_id', $posId);
            
            $result = $query->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')->first();
            
            $pendingCount = \DB::table('pos_orders')
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['pending', 'preparing'])
                ->when($posId, fn($q) => $q->where('pos_id', $posId))
                ->count();

            $count = $result->count ?? 0;
            $total = $result->total ?? 0;

            return [
                'today_sales' => (float) $total,
                'today_transactions' => $count,
                'avg_basket' => $count > 0 ? round($total / $count, 2) : 0,
                'pending_orders' => $pendingCount,
            ];
        });

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * Dashboard Serveur POS - OPTIMISÉ
     */
    public function serverStats(Request $request)
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        $userId = $user->id;

        $cacheKey = "server_stats_{$tenantId}_{$userId}_" . now()->format('Y-m-d-H-i');
        
        $stats = Cache::remember($cacheKey, 30, function () use ($tenantId, $userId) {
            // Requête agrégée pour aujourd'hui
            $todayStats = \DB::table('pos_orders')
                ->where('tenant_id', $tenantId)
                ->where('created_by', $userId)
                ->whereDate('created_at', today())
                ->selectRaw("
                    COUNT(*) as total_count,
                    SUM(CASE WHEN payment_status = 'confirmed' THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN payment_status = 'confirmed' THEN total ELSE 0 END) as paid_total,
                    SUM(CASE WHEN preparation_status IN ('pending', 'approved', 'preparing', 'ready') THEN 1 ELSE 0 END) as preparing_count,
                    SUM(CASE WHEN preparation_status = 'served' THEN 1 ELSE 0 END) as served_count
                ")
                ->first();

            // Stats globales (tout le temps)
            $allTimeStats = \DB::table('pos_orders')
                ->where('tenant_id', $tenantId)
                ->where('created_by', $userId)
                ->selectRaw("COUNT(*) as total_orders, SUM(CASE WHEN payment_status = 'confirmed' THEN total ELSE 0 END) as total_ca")
                ->first();

            return [
                'my_orders_count' => $todayStats->total_count ?? 0,
                'preparing_count' => $todayStats->preparing_count ?? 0,
                'served_count' => $todayStats->served_count ?? 0,
                'my_sales' => (float) ($allTimeStats->total_ca ?? 0),
                'today_orders' => $todayStats->total_count ?? 0,
                'today_paid' => $todayStats->paid_count ?? 0,
                'today_ca' => (float) ($todayStats->paid_total ?? 0),
                'total_orders' => $allTimeStats->total_orders ?? 0,
                'total_ca' => (float) ($allTimeStats->total_ca ?? 0),
            ];
        });

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * Dashboard Magasinier Gros - Tâches
     */
    public function warehouseGrosTasks(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');

        $purchases = Purchase::where('tenant_id', $tenantId)
            ->whereIn('status', ['ordered', 'confirmed', 'shipped'])
            ->select('id', 'reference', 'status', 'created_at')
            ->limit(10)->get()
            ->map(fn($p) => ['id' => $p->id, 'reference' => $p->reference, 'type' => 'reception', 'priority' => 'normal']);

        $requests = StockRequest::where('tenant_id', $tenantId)
            ->where('status', 'requested')
            ->select('id', 'reference', 'priority', 'created_at')
            ->limit(10)->get()
            ->map(fn($r) => ['id' => $r->id, 'reference' => $r->reference, 'type' => 'request', 'priority' => $r->priority ?? 'normal']);

        $transfers = Transfer::where('tenant_id', $tenantId)
            ->whereIn('status', ['approved', 'pending'])
            ->select('id', 'reference', 'status', 'created_at')
            ->limit(10)->get()
            ->map(fn($t) => ['id' => $t->id, 'reference' => $t->reference, 'type' => 'transfer', 'priority' => 'normal']);

        $tasks = $purchases->concat($requests)->concat($transfers)->take(15);

        return response()->json(['success' => true, 'data' => $tasks]);
    }

    /**
     * Dashboard Magasinier Détail - Tâches
     */
    public function warehouseDetailTasks(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');

        $transfers = Transfer::where('tenant_id', $tenantId)
            ->whereIn('status', ['executed', 'shipped', 'in_transit'])
            ->select('id', 'reference', 'status', 'created_at')
            ->limit(10)->get()
            ->map(fn($t) => ['id' => $t->id, 'reference' => $t->reference, 'type' => 'transfer_reception', 'priority' => 'normal']);

        $orders = PosOrder::where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'preparing', 'paid'])
            ->select('id', 'reference', 'status', 'created_at')
            ->limit(10)->get()
            ->map(fn($o) => ['id' => $o->id, 'reference' => $o->reference, 'type' => 'pos_order', 'priority' => $o->status === 'paid' ? 'high' : 'normal']);

        $tasks = $transfers->concat($orders)->take(15);

        return response()->json(['success' => true, 'data' => $tasks]);
    }

    /**
     * Dashboard Comptable
     */
    public function accountantStats(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $month = $request->query('month', now()->format('Y-m'));
        $start = \Carbon\Carbon::parse($month)->startOfMonth();
        $end = \Carbon\Carbon::parse($month)->endOfMonth();

        $sales = PosOrder::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'completed')->sum('total');

        $purchases = Purchase::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', ['received', 'completed'])->sum('total');

        $expenses = Expense::where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$start, $end])->sum('amount');

        $pendingEntries = AccountingEntry::where('tenant_id', $tenantId)
            ->where('status', 'draft')->count();

        return response()->json(['success' => true, 'data' => [
            'month' => $month,
            'total_sales' => $sales,
            'total_purchases' => $purchases,
            'total_expenses' => $expenses,
            'gross_margin' => $sales - $purchases,
            'net_result' => $sales - $purchases - $expenses,
            'pending_entries' => $pendingEntries,
        ]]);
    }

    /**
     * Dashboard Gérant
     */
    public function managerStats(Request $request)
    {
        try {
            $user = auth()->guard('sanctum')->user();
            $tenantId = $user->tenant_id ?? $request->header('X-Tenant-ID');
            
            if (!$tenantId) {
                return response()->json(['success' => false, 'message' => 'Tenant ID manquant'], 400);
            }

            // Cache de 30 secondes pour les stats manager
            $cacheKey = "manager_stats_{$tenantId}_" . today()->format('Ymd');
            
            $data = Cache::remember($cacheKey, 30, function () use ($tenantId) {
                // Ventes aujourd'hui - une seule requête
                $todayStats = PosOrder::where('tenant_id', $tenantId)
                    ->whereDate('created_at', today())
                    ->where(function($q) {
                        $q->whereIn('status', ['completed', 'paid', 'served', 'validated'])
                          ->orWhere('payment_status', 'confirmed');
                    })
                    ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
                    ->first();

                // Compteurs simples en une requête
                $pendingTransfers = Transfer::where('tenant_id', $tenantId)
                    ->whereIn('status', ['pending', 'requested'])->count();

                $lowStock = Stock::where('tenant_id', $tenantId)
                    ->where('quantity', '<=', 5)->count();

                $pendingPurchases = Purchase::where('tenant_id', $tenantId)
                    ->whereIn('status', ['draft', 'ordered', 'confirmed', 'pending'])->count();

                $openSessions = CashRegisterSession::where('tenant_id', $tenantId)
                    ->where('status', 'open')->count();

                $pendingRemittances = CashRemittance::where('tenant_id', $tenantId)
                    ->where('status', 'pending')->count();

                $todayRemittances = CashRemittance::where('tenant_id', $tenantId)
                    ->whereDate('created_at', today())
                    ->where('status', 'received')->sum('amount') ?? 0;

                // Ventes des 7 derniers jours - UNE SEULE requête groupée
                $sevenDaysAgo = now()->subDays(6)->startOfDay();
                $salesByDay = PosOrder::where('tenant_id', $tenantId)
                    ->where('created_at', '>=', $sevenDaysAgo)
                    ->where(function($q) {
                        $q->whereIn('status', ['completed', 'paid', 'served', 'validated'])
                          ->orWhere('payment_status', 'confirmed');
                    })
                    ->selectRaw('DATE(created_at) as date, COUNT(*) as count, COALESCE(SUM(total), 0) as total')
                    ->groupBy('date')
                    ->get()
                    ->keyBy('date')
                    ->toArray();

                $salesTrend = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = now()->subDays($i)->toDateString();
                    $dayName = now()->subDays($i)->locale('fr')->isoFormat('ddd');
                    $dayData = $salesByDay[$date] ?? null;
                    $salesTrend[] = [
                        'date' => ucfirst($dayName),
                        'ventes' => (float) ($dayData['total'] ?? 0),
                        'count' => (int) ($dayData['count'] ?? 0),
                    ];
                }

                // Top 5 produits - optimisé
                $topProducts = \DB::table('pos_order_items')
                    ->join('pos_orders', 'pos_order_items.pos_order_id', '=', 'pos_orders.id')
                    ->join('products', 'pos_order_items.product_id', '=', 'products.id')
                    ->where('pos_orders.tenant_id', $tenantId)
                    ->where('pos_orders.created_at', '>=', now()->subDays(7))
                    ->where(function($q) {
                        $q->whereIn('pos_orders.status', ['completed', 'paid', 'served', 'validated'])
                          ->orWhere('pos_orders.payment_status', 'confirmed');
                    })
                    ->select('products.name', \DB::raw('SUM(pos_order_items.quantity_ordered) as qty'), \DB::raw('SUM(pos_order_items.line_total) as total'))
                    ->groupBy('products.id', 'products.name')
                    ->orderByDesc('total')
                    ->limit(5)
                    ->get();

                // Stock critique
                $lowStockProducts = Stock::where('tenant_id', $tenantId)
                    ->where('quantity', '<=', 5)
                    ->with('product:id,name,unit')
                    ->limit(5)
                    ->get()
                    ->map(fn($s) => [
                        'name' => $s->product?->name ?? 'Produit',
                        'quantity' => $s->quantity,
                        'unit' => $s->product?->unit ?? 'unités',
                        'min_quantity' => $s->min_quantity ?? 5,
                    ]);

                return [
                    'todaySales' => $todayStats->count ?? 0,
                    'todaySalesAmount' => (float) ($todayStats->total ?? 0),
                    'pendingTransfers' => $pendingTransfers,
                    'lowStockItems' => $lowStock,
                    'pendingPurchases' => $pendingPurchases,
                    'openSessions' => $openSessions,
                    'pendingRemittances' => $pendingRemittances,
                    'todayRemittances' => (float) $todayRemittances,
                    'salesTrend' => $salesTrend,
                    'topProducts' => $topProducts,
                    'lowStockProducts' => $lowStockProducts,
                ];
            });

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            \Log::error('Manager stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Erreur lors du chargement des statistiques',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Dashboard Caisse Manager - données complètes
     */
    public function cashRegisterManagerDashboard(Request $request)
    {
        try {
            $user = auth()->guard('sanctum')->user();
            $tenantId = $user->tenant_id ?? $request->header('X-Tenant-ID');
            
            if (!$tenantId) {
                return response()->json(['success' => false, 'message' => 'Tenant ID manquant'], 400);
            }

        // Sessions ouvertes avec détails
        $openSessions = CashRegisterSession::where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->with(['user:id,name', 'pos:id,name'])
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'user' => $s->user?->name ?? 'N/A',
                'pos' => $s->pos?->name ?? 'Principal',
                'opening_balance' => $s->opening_balance,
                'current_balance' => $s->current_balance,
                'opened_at' => $s->opened_at,
            ]);

        // Remises en attente
        $pendingRemittances = CashRemittance::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->with(['cashier:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'cashier' => $r->cashier?->name ?? 'N/A',
                'amount' => $r->amount,
                'created_at' => $r->created_at,
            ]);

        // Résumé du jour
        $todaySummary = [
            'total_sales' => PosOrder::where('tenant_id', $tenantId)
                ->whereDate('created_at', today())
                ->where('status', 'completed')->sum('total'),
            'total_remittances' => CashRemittance::where('tenant_id', $tenantId)
                ->whereDate('created_at', today())
                ->where('status', 'received')->sum('amount'),
            'sessions_opened' => CashRegisterSession::where('tenant_id', $tenantId)
                ->whereDate('opened_at', today())->count(),
            'sessions_closed' => CashRegisterSession::where('tenant_id', $tenantId)
                ->whereDate('closed_at', today())->count(),
        ];

            return response()->json(['success' => true, 'data' => [
                'open_sessions' => $openSessions,
                'pending_remittances' => $pendingRemittances,
                'today_summary' => $todaySummary,
            ]]);
        } catch (\Exception $e) {
            \Log::error('Cash register manager dashboard error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Erreur lors du chargement des données caisse',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
