<?php

namespace App\Domains\Dashboard\Services;

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Stock;
use App\Models\AccountingEntry;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    private int $tenant_id;
    private string $currency = 'XOF';

    public function __construct(int $tenant_id = null)
    {
        $this->tenant_id = $tenant_id ?? (auth()->guard('sanctum')->user()?->tenant_id ?? 0);
    }

    /**
     * Récupérer tous les KPIs du jour
     */
    public function getTodayKPIs(): array
    {
        $today = Carbon::today();

        return [
            'date' => $today->toDateString(),
            'currency' => $this->currency,
            'sales' => $this->getTodaySales($today),
            'purchases' => $this->getTodayPurchases($today),
            'cash_flow' => $this->getTodayCashFlow($today),
            'stock_alerts' => $this->getStockAlerts(),
            'critical_stocks' => $this->getCriticalStocks(),
            'pending_operations' => $this->getPendingOperations(),
            'user_sessions' => $this->getActiveSessions(),
        ];
    }

    /**
     * Ventes du jour - OPTIMISÉ avec agrégats SQL
     */
    private function getTodaySales(Carbon $date): array
    {
        // Requête agrégée unique au lieu de ->get() + calculs PHP
        $stats = \DB::table('sales')
            ->where('tenant_id', $this->tenant_id)
            ->where('status', 'completed')
            ->whereDate('completed_at', $date)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total_revenue, COALESCE(SUM(tax_amount), 0) as total_tax')
            ->first();

        // Items vendus via jointure (évite N+1)
        $totalItems = \DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.tenant_id', $this->tenant_id)
            ->where('sales.status', 'completed')
            ->whereDate('sales.completed_at', $date)
            ->sum('sale_items.quantity');

        // Groupement par méthode de paiement (une seule requête)
        $byMethod = \DB::table('sales')
            ->where('tenant_id', $this->tenant_id)
            ->where('status', 'completed')
            ->whereDate('completed_at', $date)
            ->groupBy('payment_method')
            ->selectRaw('payment_method, COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->pluck('total', 'payment_method')
            ->map(fn($total, $method) => ['count' => 1, 'total' => round($total, 2)])
            ->toArray();

        $count = $stats->count ?? 0;
        $totalRevenue = $stats->total_revenue ?? 0;

        return [
            'count' => $count,
            'total_revenue' => round($totalRevenue, 2),
            'total_tax' => round($stats->total_tax ?? 0, 2),
            'items_sold' => $totalItems,
            'average_transaction' => $count > 0 ? round($totalRevenue / $count, 2) : 0,
            'by_method' => $byMethod,
            'by_warehouse' => [], // Simplifié pour performance
        ];
    }

    /**
     * Achats du jour - OPTIMISÉ avec agrégats SQL
     */
    private function getTodayPurchases(Carbon $date): array
    {
        // Requête agrégée unique
        $stats = \DB::table('purchases')
            ->where('tenant_id', $this->tenant_id)
            ->where('status', 'received')
            ->whereDate('received_date', $date)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total_cost')
            ->first();

        // Items reçus via jointure (évite N+1)
        $itemCount = \DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.tenant_id', $this->tenant_id)
            ->where('purchases.status', 'received')
            ->whereDate('purchases.received_date', $date)
            ->sum('purchase_items.quantity_received');

        return [
            'count' => $stats->count ?? 0,
            'total_cost' => round($stats->total_cost ?? 0, 2),
            'items_received' => $itemCount,
            'suppliers' => $stats->count ?? 0,
        ];
    }

    /**
     * Flux de trésorerie du jour - OPTIMISÉ
     */
    private function getTodayCashFlow(Carbon $date): array
    {
        // Utiliser DB::table pour éviter l'overhead Eloquent
        $sales_revenue = \DB::table('sales')
            ->where('tenant_id', $this->tenant_id)
            ->where('status', 'completed')
            ->whereDate('completed_at', $date)
            ->whereIn('payment_method', ['cash', 'mobile_money'])
            ->sum('amount_paid') ?? 0;

        $purchase_expense = \DB::table('purchases')
            ->where('tenant_id', $this->tenant_id)
            ->where('status', 'received')
            ->whereDate('received_date', $date)
            ->where('payment_method', 'cash')
            ->sum('amount_paid') ?? 0;

        $net_cash = $sales_revenue - $purchase_expense;

        return [
            'cash_in' => round($sales_revenue, 2),
            'cash_out' => round($purchase_expense, 2),
            'net_cash' => round($net_cash, 2),
            'balance_sign' => $net_cash >= 0 ? 'positive' : 'negative',
        ];
    }

    /**
     * Alertes de stock - OPTIMISÉ avec limite
     */
    private function getStockAlerts(): array
    {
        // Compter d'abord (rapide)
        $count = \DB::table('stocks')
            ->where('tenant_id', $this->tenant_id)
            ->where('quantity', '<=', 10)
            ->count();

        // Charger seulement les 10 premiers avec eager loading optimisé
        $low_stock = Stock::where('tenant_id', $this->tenant_id)
            ->where('quantity', '<=', 10)
            ->with(['product:id,name', 'warehouse:id,name'])
            ->select(['id', 'product_id', 'warehouse_id', 'quantity'])
            ->limit(10)
            ->get();

        return [
            'low_stock_count' => $count,
            'items' => $low_stock->map(fn($s) => [
                'product_id' => $s->product_id,
                'product_name' => $s->product->name ?? 'N/A',
                'warehouse' => $s->warehouse?->name ?? 'Gros',
                'quantity' => $s->quantity,
                'warning_level' => round($s->quantity / 2, 0),
            ])->toArray(),
        ];
    }

    /**
     * Stock critique - OPTIMISÉ
     */
    private function getCriticalStocks(): array
    {
        // Compter d'abord
        $count = \DB::table('stocks')
            ->where('tenant_id', $this->tenant_id)
            ->where('quantity', '<=', 0)
            ->count();

        // Charger seulement les 10 premiers
        $critical = Stock::where('tenant_id', $this->tenant_id)
            ->where('quantity', '<=', 0)
            ->with(['product:id,name', 'warehouse:id,name'])
            ->select(['id', 'product_id', 'warehouse_id', 'quantity'])
            ->limit(10)
            ->get();

        return [
            'critical_count' => $count,
            'items' => $critical->map(fn($s) => [
                'product_id' => $s->product_id,
                'product_name' => $s->product->name ?? 'N/A',
                'warehouse' => $s->warehouse?->name ?? 'Gros',
                'quantity' => $s->quantity,
            ])->toArray(),
        ];
    }

    /**
     * Opérations en attente - OPTIMISÉ avec DB::table
     */
    private function getPendingOperations(): array
    {
        $pending_purchases = \DB::table('purchases')
            ->where('tenant_id', $this->tenant_id)
            ->where('status', 'pending')
            ->count();

        $pending_sales = \DB::table('sales')
            ->where('tenant_id', $this->tenant_id)
            ->where('status', 'draft')
            ->count();

        return [
            'pending_purchases' => $pending_purchases,
            'pending_sales' => $pending_sales,
            'total_pending' => $pending_purchases + $pending_sales,
        ];
    }

    /**
     * Sessions utilisateurs actives - OPTIMISÉ avec DB::table
     */
    private function getActiveSessions(): array
    {
        $activeUsers = \DB::table('users')
            ->where('tenant_id', $this->tenant_id)
            ->where('last_login_at', '>=', now()->subHours(8))
            ->count();

        $totalUsers = \DB::table('users')
            ->where('tenant_id', $this->tenant_id)
            ->count();

        return [
            'active_users' => $activeUsers,
            'total_users' => $totalUsers,
        ];
    }

    /**
     * Grouper par mode de paiement
     */
    private function groupByPaymentMethod($sales): array
    {
        return $sales->groupBy('payment_method')
            ->map(fn($group) => [
                'count' => $group->count(),
                'total' => round($group->sum('total'), 2),
            ])
            ->toArray();
    }

    /**
     * Grouper par entrepôt
     */
    private function groupByWarehouse($sales): array
    {
        // Join via warehouse_id from items or infer
        $grouped = [];
        foreach ($sales as $sale) {
            $warehouse = $sale->warehouse_id ?? 'default';
            if (!isset($grouped[$warehouse])) {
                $grouped[$warehouse] = [
                    'count' => 0,
                    'total' => 0,
                ];
            }
            $grouped[$warehouse]['count']++;
            $grouped[$warehouse]['total'] += $sale->total;
        }
        
        return $grouped;
    }

    /**
     * Rapport mensuel complet
     */
    public function getMonthlyReport(int $month, int $year): array
    {
        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end = $start->clone()->endOfMonth()->endOfDay();

        $sales = Sale::where('tenant_id', $this->tenant_id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->get();

        $purchases = Purchase::where('tenant_id', $this->tenant_id)
            ->where('status', 'received')
            ->whereBetween('received_date', [$start, $end])
            ->get();

        $total_revenue = $sales->sum('total');
        $total_cost = $purchases->sum('total');
        $gross_profit = $total_revenue - $total_cost;
        $margin_percent = $total_revenue > 0 ? ($gross_profit / $total_revenue) * 100 : 0;

        return [
            'period' => "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT),
            'sales' => [
                'count' => $sales->count(),
                'total_revenue' => round($total_revenue, 2),
                'average_transaction' => round($total_revenue / max(1, $sales->count()), 2),
            ],
            'purchases' => [
                'count' => $purchases->count(),
                'total_cost' => round($total_cost, 2),
            ],
            'profitability' => [
                'gross_profit' => round($gross_profit, 2),
                'margin_percent' => round($margin_percent, 2),
            ],
        ];
    }
}
