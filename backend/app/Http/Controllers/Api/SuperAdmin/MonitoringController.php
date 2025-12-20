<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\System\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class MonitoringController extends Controller
{
    /**
     * État général du système
     */
    public function index(Request $request): JsonResponse
    {
        $data =  [
                'server' => $this->getServerMetrics(),
                'database' => $this->getDatabaseMetrics(),
                'application' => $this->getApplicationMetrics(),
                'tenants' => $this->getTenantsHealth(),
            ];
        

        return response()->json(['data' => $data]);
    }

    /**
     * Métriques serveur
     */
    protected function getServerMetrics(): array
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];
        
        // Mémoire
        $memInfo = [];
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/meminfo')) {
            $memData = file_get_contents('/proc/meminfo');
            preg_match_all('/(\w+):\s+(\d+)/', $memData, $matches);
            $memInfo = array_combine($matches[1], $matches[2]);
        }

        $memTotal = ($memInfo['MemTotal'] ?? 0) / 1024; // MB
        $memFree = ($memInfo['MemAvailable'] ?? $memInfo['MemFree'] ?? 0) / 1024;
        $memUsed = $memTotal - $memFree;
        $memPercent = $memTotal > 0 ? round(($memUsed / $memTotal) * 100, 1) : 0;

        // Disque
        $diskTotal = @disk_total_space('/') ?: disk_total_space(storage_path());
        $diskFree = @disk_free_space('/') ?: disk_free_space(storage_path());
        $diskUsed = $diskTotal - $diskFree;
        $diskPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0;

        return [
            'cpu' => [
                'load_1m' => $load[0] ?? 0,
                'load_5m' => $load[1] ?? 0,
                'load_15m' => $load[2] ?? 0,
                'cores' => $this->getCpuCores(),
            ],
            'memory' => [
                'total_mb' => round($memTotal),
                'used_mb' => round($memUsed),
                'free_mb' => round($memFree),
                'percent' => $memPercent,
                'status' => $memPercent > 90 ? 'critical' : ($memPercent > 75 ? 'warning' : 'ok'),
            ],
            'disk' => [
                'total_gb' => round($diskTotal / 1024 / 1024 / 1024, 2),
                'used_gb' => round($diskUsed / 1024 / 1024 / 1024, 2),
                'free_gb' => round($diskFree / 1024 / 1024 / 1024, 2),
                'percent' => $diskPercent,
                'status' => $diskPercent > 90 ? 'critical' : ($diskPercent > 80 ? 'warning' : 'ok'),
            ],
            'uptime' => $this->getUptime(),
            'php_version' => PHP_VERSION,
            'os' => PHP_OS_FAMILY,
        ];
    }

    /**
     * Métriques base de données
     */
    protected function getDatabaseMetrics(): array
    {
        try {
            $driver = DB::connection()->getDriverName();
            $startTime = microtime(true);
            DB::select('SELECT 1');
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            // Taille DB
            $dbSize = 0;
            if ($driver === 'pgsql') {
                $result = DB::select("SELECT pg_database_size(current_database()) as size");
                $dbSize = round(($result[0]->size ?? 0) / 1024 / 1024, 2);
            } elseif ($driver === 'mysql') {
                $dbName = DB::connection()->getDatabaseName();
                $result = DB::select("SELECT SUM(data_length + index_length) as size FROM information_schema.tables WHERE table_schema = ?", [$dbName]);
                $dbSize = round(($result[0]->size ?? 0) / 1024 / 1024, 2);
            }

            // Connexions actives
            $connections = 0;
            if ($driver === 'pgsql') {
                $result = DB::select("SELECT count(*) as count FROM pg_stat_activity");
                $connections = $result[0]->count ?? 0;
            } elseif ($driver === 'mysql') {
                $result = DB::select("SHOW STATUS LIKE 'Threads_connected'");
                $connections = $result[0]->Value ?? 0;
            }

            return [
                'driver' => $driver,
                'status' => 'ok',
                'response_time_ms' => $responseTime,
                'size_mb' => $dbSize,
                'connections' => $connections,
            ];
        } catch (\Exception $e) {
            return [
                'driver' => 'unknown',
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Métriques application
     */
    protected function getApplicationMetrics(): array
    {
        $now = now();

        // Requêtes aujourd'hui
        $requestsToday = SystemLog::whereDate('created_at', $now->toDateString())->count();

        // Erreurs dernières 24h
        $errors24h = SystemLog::whereIn('level', ['error', 'critical'])
            ->where('created_at', '>=', $now->subHours(24))
            ->count();

        // Jobs en attente
        $pendingJobs = 0;
        $failedJobs = 0;
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            // Tables n'existent pas
        }

        // Cache
        $cacheStatus = 'ok';
        try {
            Cache::put('health_check', true, 10);
            if (!Cache::get('health_check')) {
                $cacheStatus = 'error';
            }
        } catch (\Exception $e) {
            $cacheStatus = 'error';
        }

        return [
            'requests_today' => $requestsToday,
            'errors_24h' => $errors24h,
            'queue' => [
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
                'status' => $failedJobs > 10 ? 'warning' : 'ok',
            ],
            'cache' => [
                'driver' => config('cache.default'),
                'status' => $cacheStatus,
            ],
            'session' => [
                'driver' => config('session.driver'),
            ],
        ];
    }

    /**
     * Santé des tenants
     */
    protected function getTenantsHealth(): array
    {
        $tenants = Tenant::select('id', 'name', 'status', 'last_activity_at', 'storage_used_mb')
            ->withCount('users')
            ->get();

        $healthy = 0;
        $warning = 0;
        $critical = 0;

        foreach ($tenants as $tenant) {
            if ($tenant->status !== 'active') {
                $critical++;
            } elseif ($tenant->storage_used_mb > 400) { // > 80% de 500MB
                $warning++;
            } else {
                $healthy++;
            }
        }

        return [
            'total' => $tenants->count(),
            'healthy' => $healthy,
            'warning' => $warning,
            'critical' => $critical,
            'by_status' => $tenants->groupBy('status')->map->count(),
        ];
    }

    /**
     * Requêtes lentes
     */
    public function slowQueries(Request $request): JsonResponse
    {
        // Cette fonctionnalité nécessite la configuration du slow query log
        // Pour l'instant, retourner un placeholder
        return response()->json([
            'data' => [
                'message' => 'Slow query logging not configured',
                'queries' => [],
            ],
        ]);
    }

    /**
     * Alertes actives
     */
    public function alerts(Request $request): JsonResponse
    {
        $alerts = [];

        // Vérifier les métriques critiques
        $metrics = $this->getServerMetrics();

        if (($metrics['memory']['percent'] ?? 0) > 90) {
            $alerts[] = [
                'type' => 'critical',
                'category' => 'memory',
                'message' => "Mémoire critique: {$metrics['memory']['percent']}% utilisée",
                'timestamp' => now()->toISOString(),
            ];
        }

        if (($metrics['disk']['percent'] ?? 0) > 90) {
            $alerts[] = [
                'type' => 'critical',
                'category' => 'disk',
                'message' => "Espace disque critique: {$metrics['disk']['percent']}% utilisé",
                'timestamp' => now()->toISOString(),
            ];
        }

        // Erreurs récentes
        $recentErrors = SystemLog::whereIn('level', ['error', 'critical'])
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();

        if ($recentErrors > 10) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'errors',
                'message' => "{$recentErrors} erreurs dans les 5 dernières minutes",
                'timestamp' => now()->toISOString(),
            ];
        }

        // Jobs échoués
        try {
            $failedJobs = DB::table('failed_jobs')->count();
            if ($failedJobs > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'category' => 'queue',
                    'message' => "{$failedJobs} jobs en échec",
                    'timestamp' => now()->toISOString(),
                ];
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return response()->json(['data' => $alerts]);
    }

    /**
     * Historique des métriques
     */
    public function history(Request $request): JsonResponse
    {
        $hours = $request->query('hours', 24);

        // Logs par heure
        $logsByHour = SystemLog::where('created_at', '>=', now()->subHours($hours))
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m-%d %H:00") as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour');

        // Erreurs par heure
        $errorsByHour = SystemLog::whereIn('level', ['error', 'critical'])
            ->where('created_at', '>=', now()->subHours($hours))
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m-%d %H:00") as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour');

        return response()->json([
            'data' => [
                'period_hours' => $hours,
                'logs_by_hour' => $logsByHour,
                'errors_by_hour' => $errorsByHour,
            ],
        ]);
    }

    protected function getCpuCores(): int
    {
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/cpuinfo')) {
            $cpuInfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuInfo, $matches);
            return count($matches[0]);
        }
        return 1;
    }

    protected function getUptime(): string
    {
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/uptime')) {
            $uptime = (float) file_get_contents('/proc/uptime');
            $days = floor($uptime / 86400);
            $hours = floor(($uptime % 86400) / 3600);
            return "{$days}j {$hours}h";
        }
        return 'N/A';
    }
}
