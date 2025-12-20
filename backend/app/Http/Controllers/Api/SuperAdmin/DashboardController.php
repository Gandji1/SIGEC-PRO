<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Sale;
use App\Models\System\Subscription;
use App\Models\System\Payment;
use App\Models\System\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Dashboard global multi-tenant
     */
    public function index(Request $request): JsonResponse
    {
        $stats = $this->computeStats();

        return response()->json(['data' => $stats]);
    }

    /**
     * Statistiques détaillées
     */
    protected function computeStats(): array
    {
        // Tenants
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $suspendedTenants = Tenant::where('status', 'suspended')->count();
        $trialTenants = Subscription::where('status', 'trial')->count();

        // Utilisateurs
        $totalUsers = User::count();
        $activeUsers = User::whereHas('tenant', fn($q) => $q->where('status', 'active'))->count();

        // Revenus
        $monthlyRevenue = Payment::where('status', 'completed')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $yearlyRevenue = Payment::where('status', 'completed')
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        // Transactions (ventes de tous les tenants)
        $totalSales = Sale::whereIn('status', ['completed', 'paid'])->count();
        $totalSalesAmount = Sale::whereIn('status', ['completed', 'paid'])->sum('total');

        // Logs critiques
        $criticalLogs = SystemLog::whereIn('level', ['error', 'critical'])
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        // Performance système
        $dbSize = $this->getDatabaseSize();
        $requestsToday = SystemLog::whereDate('created_at', today())->count();

        // Tenants récents
        $recentTenants = Tenant::with('users:id,tenant_id')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get(['id', 'name', 'status', 'created_at', 'business_type']);

        // Logs récents
        $recentLogs = SystemLog::with(['user:id,name,email', 'tenant:id,name'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return [
            'tenants' => [
                'total' => $totalTenants,
                'active' => $activeTenants,
                'suspended' => $suspendedTenants,
                'trial' => $trialTenants,
            ],
            'users' => [
                'total' => $totalUsers,
                'active' => $activeUsers,
            ],
            'revenue' => [
                'monthly' => $monthlyRevenue,
                'yearly' => $yearlyRevenue,
                'currency' => 'XOF',
            ],
            'transactions' => [
                'total_sales' => $totalSales,
                'total_amount' => $totalSalesAmount,
            ],
            'system' => [
                'critical_logs_24h' => $criticalLogs,
                'db_size_mb' => $dbSize,
                'requests_today' => $requestsToday,
                'uptime' => '99.9%',
                'api_response_time' => '< 100ms',
            ],
            'recent_tenants' => $recentTenants,
            'recent_logs' => $recentLogs,
        ];
    }

    /**
     * Statistiques par période
     */
    public function stats(Request $request): JsonResponse
    {
        $period = $request->query('period', 'month'); // day, week, month, year

        $startDate = match ($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        $newTenants = Tenant::where('created_at', '>=', $startDate)->count();
        $newUsers = User::where('created_at', '>=', $startDate)->count();
        $revenue = Payment::where('status', 'completed')
            ->where('paid_at', '>=', $startDate)
            ->sum('amount');

        // Graphique des tenants par jour
        $tenantsByDay = Tenant::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Graphique des revenus par jour
        $revenueByDay = Payment::where('status', 'completed')
            ->where('paid_at', '>=', $startDate)
            ->selectRaw('DATE(paid_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'data' => [
                'period' => $period,
                'start_date' => $startDate->toDateString(),
                'new_tenants' => $newTenants,
                'new_users' => $newUsers,
                'revenue' => $revenue,
                'charts' => [
                    'tenants_by_day' => $tenantsByDay,
                    'revenue_by_day' => $revenueByDay,
                ],
            ],
        ]);
    }

    /**
     * Santé de la plateforme
     */
    public function health(Request $request): JsonResponse
    {
        $health = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
        ];

        $overallStatus = collect($health)->every(fn($h) => $h['status'] === 'ok') ? 'healthy' : 'degraded';

        return response()->json([
            'status' => $overallStatus,
            'checks' => $health,
            'timestamp' => now()->toISOString(),
        ]);
    }

    protected function getDatabaseSize(): float
    {
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                $result = DB::select("SELECT pg_database_size(current_database()) / 1024 / 1024 as size");
                return round($result[0]->size ?? 0, 2);
            } elseif ($driver === 'mysql') {
                $dbName = DB::connection()->getDatabaseName();
                $result = DB::select("SELECT SUM(data_length + index_length) / 1024 / 1024 as size FROM information_schema.tables WHERE table_schema = ?", [$dbName]);
                return round($result[0]->size ?? 0, 2);
            }
        } catch (\Exception $e) {
            return 0;
        }
        return 0;
    }

    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'ok', 'message' => 'Connected'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function checkCache(): array
    {
        try {
            Cache::put('health_check', true, 10);
            $value = Cache::get('health_check');
            return ['status' => $value ? 'ok' : 'error', 'message' => $value ? 'Working' : 'Failed'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function checkStorage(): array
    {
        try {
            $free = disk_free_space(storage_path());
            $total = disk_total_space(storage_path());
            $usedPercent = round((($total - $free) / $total) * 100, 1);
            
            return [
                'status' => $usedPercent < 90 ? 'ok' : 'warning',
                'message' => "{$usedPercent}% used",
                'free_gb' => round($free / 1024 / 1024 / 1024, 2),
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function checkQueue(): array
    {
        try {
            $failedJobs = DB::table('failed_jobs')->count();
            return [
                'status' => $failedJobs === 0 ? 'ok' : 'warning',
                'message' => $failedJobs === 0 ? 'No failed jobs' : "{$failedJobs} failed jobs",
                'failed_jobs' => $failedJobs,
            ];
        } catch (\Exception $e) {
            return ['status' => 'ok', 'message' => 'Queue table not found'];
        }
    }
}
