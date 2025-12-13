<?php

namespace App\Http\Controllers\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PlatformController extends Controller
{
    /**
     * Statistiques globales de la plateforme
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = Cache::remember('platform_stats', 300, function () {
            // Calculer la taille DB selon le driver
            $dbSize = 0;
            try {
                $driver = DB::connection()->getDriverName();
                if ($driver === 'pgsql') {
                    $dbSize = round(DB::select("SELECT pg_database_size(current_database()) / 1024 / 1024 as size")[0]->size ?? 0, 2);
                } elseif ($driver === 'mysql') {
                    $dbName = DB::connection()->getDatabaseName();
                    $result = DB::select("SELECT SUM(data_length + index_length) / 1024 / 1024 as size FROM information_schema.tables WHERE table_schema = ?", [$dbName]);
                    $dbSize = round($result[0]->size ?? 0, 2);
                }
            } catch (\Exception $e) {
                $dbSize = 0;
            }

            return [
                'active_tenants' => Tenant::where('status', 'active')->count(),
                'suspended_tenants' => Tenant::where('status', 'suspended')->count(),
                'total_tenants' => Tenant::count(),
                'total_users' => User::count(),
                'monthly_revenue' => 0,
                'api_response_time' => '< 100',
                'uptime' => '99.9',
                'db_size' => $dbSize,
                'requests_today' => AuditLog::whereDate('created_at', today())->count(),
            ];
        });

        return response()->json(['data' => $stats]);
    }

    /**
     * Logs système
     */
    public function logs(Request $request): JsonResponse
    {
        $perPage = min($request->query('per_page', 50), 100);
        $level = $request->query('level');
        $type = $request->query('type');

        $query = AuditLog::with(['user:id,name,email', 'tenant:id,name'])
            ->orderBy('created_at', 'desc');

        if ($level && $level !== 'all') {
            $query->where('level', $level);
        }

        if ($type && $type !== 'all') {
            $query->where('type', $type);
        }

        $logs = $query->paginate($perPage);

        // Transformer les données
        $logs->getCollection()->transform(function ($log) {
            return [
                'id' => $log->id,
                'level' => $log->level ?? 'info',
                'type' => $log->type ?? 'system',
                'action' => $log->action,
                'message' => $log->message,
                'details' => $log->details,
                'ip_address' => $log->ip_address,
                'user_email' => $log->user->email ?? null,
                'tenant_name' => $log->tenant->name ?? null,
                'created_at' => $log->created_at,
            ];
        });

        return response()->json($logs);
    }

    /**
     * Créer un log système
     */
    public static function log(string $action, string $level = 'info', ?string $type = 'system', ?array $details = null): void
    {
        try {
            AuditLog::create([
                'tenant_id' => auth()->user()?->tenant_id,
                'user_id' => auth()->id(),
                'action' => $action,
                'level' => $level,
                'type' => $type,
                'details' => $details ? json_encode($details) : null,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to create audit log: ' . $e->getMessage());
        }
    }
}
