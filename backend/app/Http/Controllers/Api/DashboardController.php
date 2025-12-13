<?php

namespace App\Http\Controllers\Api;

use App\Domains\Dashboard\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    private DashboardService $dashboardService;

    public function __construct()
    {
        $this->dashboardService = new DashboardService();
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    /**
     * KPIs du jour - avec cache de 30 secondes pour performance optimale
     */
    public function stats(): JsonResponse
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            // Cache key plus granulaire (par minute pour fraîcheur)
            $cacheKey = "dashboard_stats_{$tenantId}_" . now()->format('Y-m-d-H-i');
            
            // Cache de 30 secondes - équilibre entre fraîcheur et performance
            $kpis = Cache::remember($cacheKey, 30, function () {
                return $this->dashboardService->getTodayKPIs();
            });

            return response()->json([
                'status' => 'success',
                'data' => $kpis,
                'cached_at' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Dashboard stats error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors du chargement des statistiques',
                'data' => $this->getEmptyStats(),
            ], 500);
        }
    }

    /**
     * Stats vides pour fallback
     */
    private function getEmptyStats(): array
    {
        return [
            'date' => now()->toDateString(),
            'currency' => 'XOF',
            'sales' => ['count' => 0, 'total_revenue' => 0],
            'purchases' => ['count' => 0, 'total_cost' => 0],
            'cash_flow' => ['cash_in' => 0, 'cash_out' => 0, 'net_cash' => 0],
            'stock_alerts' => ['low_stock_count' => 0, 'items' => []],
            'critical_stocks' => ['critical_count' => 0, 'items' => []],
            'pending_operations' => ['total_pending' => 0],
            'user_sessions' => ['active_users' => 0, 'total_users' => 0],
        ];
    }

    /**
     * Rapport mensuel
     */
    public function monthlyReport(Request $request): JsonResponse
    {
        $month = $request->query('month', now()->month);
        $year = $request->query('year', now()->year);

        try {
            $report = $this->dashboardService->getMonthlyReport($month, $year);

            return response()->json([
                'status' => 'success',
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stats Manager Dashboard - données agrégées pour le gérant
     */
    public function managerStats(): JsonResponse
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            $cacheKey = "manager_stats_{$tenantId}_" . now()->format('Y-m-d-H-i');
            
            $stats = Cache::remember($cacheKey, 30, function () use ($tenantId) {
                // Ventes aujourd'hui
                $todaySales = \DB::table('sales')
                    ->where('tenant_id', $tenantId)
                    ->whereDate('completed_at', today())
                    ->where('status', 'completed')
                    ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
                    ->first();

                // Transferts en attente
                $pendingTransfers = \DB::table('transfers')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('status', ['pending', 'approved'])
                    ->count();

                // Stock critique
                $lowStockItems = \DB::table('stocks')
                    ->where('tenant_id', $tenantId)
                    ->where('available', '<=', 10)
                    ->count();

                // Achats en attente
                $pendingPurchases = \DB::table('purchases')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('status', ['draft', 'ordered', 'pending_approval'])
                    ->count();

                // Ventes des 7 derniers jours (pour le graphique)
                $salesTrend = \DB::table('sales')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'completed')
                    ->whereDate('completed_at', '>=', now()->subDays(6)->startOfDay())
                    ->selectRaw("DATE(completed_at) as date, COUNT(*) as count, COALESCE(SUM(total), 0) as ventes")
                    ->groupBy(\DB::raw('DATE(completed_at)'))
                    ->orderBy('date')
                    ->get();

                // Remplir les jours manquants
                $trendData = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = now()->subDays($i)->format('Y-m-d');
                    $dayData = $salesTrend->firstWhere('date', $date);
                    $trendData[] = [
                        'date' => now()->subDays($i)->format('D'),
                        'count' => $dayData->count ?? 0,
                        'ventes' => (float) ($dayData->ventes ?? 0),
                    ];
                }

                // Top produits vendus
                $topProducts = \DB::table('sale_items')
                    ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                    ->join('products', 'sale_items.product_id', '=', 'products.id')
                    ->where('sales.tenant_id', $tenantId)
                    ->where('sales.status', 'completed')
                    ->whereDate('sales.completed_at', '>=', now()->subDays(30))
                    ->selectRaw('products.name, SUM(sale_items.quantity) as qty, SUM(sale_items.line_total) as total')
                    ->groupBy('products.id', 'products.name')
                    ->orderByDesc('total')
                    ->limit(5)
                    ->get();

                // Produits en stock critique
                $lowStockProducts = \DB::table('stocks')
                    ->join('products', 'stocks.product_id', '=', 'products.id')
                    ->where('stocks.tenant_id', $tenantId)
                    ->where('stocks.available', '<=', 10)
                    ->select('products.name', 'products.unit', 'stocks.available as quantity')
                    ->orderBy('stocks.available')
                    ->limit(5)
                    ->get();

                return [
                    'todaySales' => $todaySales->count ?? 0,
                    'todaySalesAmount' => (float) ($todaySales->total ?? 0),
                    'pendingTransfers' => $pendingTransfers,
                    'lowStockItems' => $lowStockItems,
                    'pendingPurchases' => $pendingPurchases,
                    'salesTrend' => $trendData,
                    'topProducts' => $topProducts,
                    'lowStockProducts' => $lowStockProducts,
                ];
            });

            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            \Log::error('Manager stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement',
                'data' => [
                    'todaySales' => 0,
                    'todaySalesAmount' => 0,
                    'pendingTransfers' => 0,
                    'lowStockItems' => 0,
                    'pendingPurchases' => 0,
                    'salesTrend' => [],
                    'topProducts' => [],
                    'lowStockProducts' => [],
                ],
            ], 500);
        }
    }

    /**
     * Données graphique 7 derniers jours - endpoint dédié (utilise pos_orders)
     */
    public function last7Days(): JsonResponse
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            $cacheKey = "last7days_{$tenantId}_" . now()->format('Y-m-d-H-i');
            
            $data = Cache::remember($cacheKey, 30, function () use ($tenantId) {
                // Utiliser pos_orders pour les ventes POS
                $salesTrend = \DB::table('pos_orders')
                    ->where('tenant_id', $tenantId)
                    ->where(function($q) {
                        $q->whereIn('status', ['completed', 'paid', 'served', 'validated'])
                          ->orWhere('payment_status', 'confirmed');
                    })
                    ->whereDate('created_at', '>=', now()->subDays(6)->startOfDay())
                    ->selectRaw("DATE(created_at) as date, COUNT(*) as count, COALESCE(SUM(total), 0) as revenue")
                    ->groupBy(\DB::raw('DATE(created_at)'))
                    ->orderBy('date')
                    ->get();

                $days = [];
                $totals = [];
                $counts = [];
                
                for ($i = 6; $i >= 0; $i--) {
                    $date = now()->subDays($i)->format('Y-m-d');
                    $dayData = $salesTrend->firstWhere('date', $date);
                    
                    $days[] = now()->subDays($i)->locale('fr')->isoFormat('ddd');
                    $totals[] = (float) ($dayData->revenue ?? 0);
                    $counts[] = $dayData->count ?? 0;
                }

                return [
                    'days' => $days,
                    'totals' => $totals,
                    'counts' => $counts,
                    'total_revenue' => array_sum($totals),
                    'total_count' => array_sum($counts),
                ];
            });

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            \Log::error('Last 7 days error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => ['days' => [], 'totals' => [], 'counts' => []],
            ], 500);
        }
    }
}
