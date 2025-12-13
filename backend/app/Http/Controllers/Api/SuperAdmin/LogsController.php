<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\System\SystemLog;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LogsController extends Controller
{
    /**
     * Liste des logs système
     */
    public function index(Request $request): JsonResponse
    {
        $query = SystemLog::with(['user:id,name,email', 'tenant:id,name']);

        // Filtres
        if ($request->has('level') && $request->level !== 'all') {
            $query->where('level', $request->level);
        }

        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = min($request->query('per_page', 50), 100);
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($logs);
    }

    /**
     * Détail d'un log
     */
    public function show(SystemLog $log): JsonResponse
    {
        $log->load(['user', 'tenant']);

        return response()->json(['data' => $log]);
    }

    /**
     * Statistiques des logs
     */
    public function stats(Request $request): JsonResponse
    {
        $hours = $request->query('hours', 24);
        $since = now()->subHours($hours);

        $byLevel = SystemLog::where('created_at', '>=', $since)
            ->selectRaw('level, COUNT(*) as count')
            ->groupBy('level')
            ->pluck('count', 'level');

        $byType = SystemLog::where('created_at', '>=', $since)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        // Compatibilité SQLite/MySQL pour l'extraction de l'heure
        $driver = \DB::connection()->getDriverName();
        $hourExpr = $driver === 'sqlite' 
            ? "strftime('%H', created_at)" 
            : 'HOUR(created_at)';
        
        $byHour = SystemLog::where('created_at', '>=', $since)
            ->selectRaw("{$hourExpr} as hour, COUNT(*) as count")
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour');

        $criticalCount = SystemLog::where('created_at', '>=', $since)
            ->whereIn('level', ['error', 'critical'])
            ->count();

        return response()->json([
            'data' => [
                'period_hours' => $hours,
                'total' => SystemLog::where('created_at', '>=', $since)->count(),
                'critical' => $criticalCount,
                'by_level' => $byLevel,
                'by_type' => $byType,
                'by_hour' => $byHour,
            ],
        ]);
    }

    /**
     * Logs d'audit (actions utilisateurs)
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $query = AuditLog::with(['user:id,name,email', 'tenant:id,name']);

        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        $perPage = min($request->query('per_page', 50), 100);
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($logs);
    }

    /**
     * Erreurs récentes
     */
    public function errors(Request $request): JsonResponse
    {
        $hours = $request->query('hours', 24);

        $errors = SystemLog::whereIn('level', ['error', 'critical'])
            ->where('created_at', '>=', now()->subHours($hours))
            ->with(['user:id,name,email', 'tenant:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json(['data' => $errors]);
    }

    /**
     * Purger les anciens logs
     */
    public function purge(Request $request): JsonResponse
    {
        $days = $request->input('days', 90);

        $deleted = SystemLog::where('created_at', '<', now()->subDays($days))
            ->whereNotIn('level', ['error', 'critical'])
            ->delete();

        SystemLog::log(
            "Logs purgés: {$deleted} entrées supprimées",
            'info',
            'system',
            "Logs de plus de {$days} jours supprimés"
        );

        return response()->json([
            'success' => true,
            'message' => "{$deleted} logs supprimés",
        ]);
    }

    /**
     * Exporter les logs
     */
    public function export(Request $request): JsonResponse
    {
        $query = SystemLog::with(['user:id,name,email', 'tenant:id,name']);

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        $logs = $query->orderBy('created_at', 'desc')->limit(10000)->get();

        // Format CSV
        $csv = "Date,Niveau,Type,Action,Message,Utilisateur,Tenant,IP\n";
        foreach ($logs as $log) {
            $csv .= sprintf(
                "%s,%s,%s,\"%s\",\"%s\",%s,%s,%s\n",
                $log->created_at,
                $log->level,
                $log->type,
                str_replace('"', '""', $log->action ?? ''),
                str_replace('"', '""', $log->message ?? ''),
                $log->user?->email ?? '',
                $log->tenant?->name ?? '',
                $log->ip_address ?? ''
            );
        }

        return response()->json([
            'data' => [
                'content' => base64_encode($csv),
                'filename' => 'logs_' . now()->format('Y-m-d_His') . '.csv',
                'mime_type' => 'text/csv',
            ],
        ]);
    }
}
