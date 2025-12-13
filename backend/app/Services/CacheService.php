<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service de cache centralisé pour les performances
 */
class CacheService
{
    /**
     * Durées de cache en secondes
     */
    const TTL_SHORT = 60;        // 1 minute
    const TTL_MEDIUM = 300;      // 5 minutes
    const TTL_LONG = 3600;       // 1 heure
    const TTL_DAY = 86400;       // 1 jour

    /**
     * Préfixes de cache
     */
    const PREFIX_STATS = 'stats';
    const PREFIX_DASHBOARD = 'dashboard';
    const PREFIX_ACCOUNTING = 'accounting';
    const PREFIX_PRODUCTS = 'products';

    /**
     * Générer une clé de cache
     */
    public static function key(string $prefix, int $tenantId, string $identifier = ''): string
    {
        return "{$prefix}:{$tenantId}:{$identifier}";
    }

    /**
     * Récupérer ou calculer une valeur
     */
    public static function remember(string $key, int $ttl, callable $callback)
    {
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Invalider le cache d'un tenant
     */
    public static function invalidateTenant(int $tenantId): void
    {
        $prefixes = [self::PREFIX_STATS, self::PREFIX_DASHBOARD, self::PREFIX_ACCOUNTING, self::PREFIX_PRODUCTS];
        
        foreach ($prefixes as $prefix) {
            $pattern = "{$prefix}:{$tenantId}:*";
            self::forgetPattern($pattern);
        }
    }

    /**
     * Invalider un préfixe spécifique pour un tenant
     */
    public static function invalidatePrefix(string $prefix, int $tenantId): void
    {
        $pattern = "{$prefix}:{$tenantId}:*";
        self::forgetPattern($pattern);
    }

    /**
     * Supprimer les clés correspondant à un pattern
     */
    protected static function forgetPattern(string $pattern): void
    {
        $driver = config('cache.default');
        
        if ($driver === 'redis') {
            $redis = Cache::getRedis();
            $keys = $redis->keys(config('cache.prefix') . ':' . $pattern);
            foreach ($keys as $key) {
                $redis->del($key);
            }
        } elseif ($driver === 'file') {
            // Pour le cache fichier, on ne peut pas utiliser de patterns
            // On utilise des tags si disponibles
            Cache::flush(); // Solution de repli
        }
    }

    /**
     * Statistiques du dashboard avec cache
     */
    public static function getDashboardStats(int $tenantId, string $period = 'month'): array
    {
        $key = self::key(self::PREFIX_DASHBOARD, $tenantId, "stats:{$period}");
        
        return self::remember($key, self::TTL_MEDIUM, function () use ($tenantId, $period) {
            $from = match($period) {
                'day' => now()->startOfDay(),
                'week' => now()->startOfWeek(),
                'month' => now()->startOfMonth(),
                'year' => now()->startOfYear(),
                default => now()->startOfMonth(),
            };

            return [
                'sales' => DB::table('sales')
                    ->where('tenant_id', $tenantId)
                    ->where('created_at', '>=', $from)
                    ->where('status', 'completed')
                    ->sum('total'),
                'orders' => DB::table('pos_orders')
                    ->where('tenant_id', $tenantId)
                    ->where('created_at', '>=', $from)
                    ->count(),
                'customers' => DB::table('customers')
                    ->where('tenant_id', $tenantId)
                    ->count(),
                'products' => DB::table('products')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->count(),
            ];
        });
    }

    /**
     * Produits avec cache
     */
    public static function getProducts(int $tenantId, int $page = 1, int $perPage = 50): array
    {
        $key = self::key(self::PREFIX_PRODUCTS, $tenantId, "list:{$page}:{$perPage}");
        
        return self::remember($key, self::TTL_MEDIUM, function () use ($tenantId, $page, $perPage) {
            return DB::table('products')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->select(['id', 'name', 'sku', 'selling_price', 'stock_quantity', 'category_id'])
                ->orderBy('name')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get()
                ->toArray();
        });
    }

    /**
     * Statistiques comptables avec cache
     */
    public static function getAccountingStats(int $tenantId, string $from, string $to): array
    {
        $key = self::key(self::PREFIX_ACCOUNTING, $tenantId, "stats:{$from}:{$to}");
        
        return self::remember($key, self::TTL_LONG, function () use ($tenantId, $from, $to) {
            $sales = DB::table('sales')
                ->where('tenant_id', $tenantId)
                ->whereBetween('sale_date', [$from, $to])
                ->where('status', 'completed')
                ->sum('total');

            $purchases = DB::table('purchases')
                ->where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$from, $to])
                ->whereIn('status', ['received', 'completed'])
                ->sum('total');

            $expenses = DB::table('expenses')
                ->where('tenant_id', $tenantId)
                ->whereBetween('expense_date', [$from, $to])
                ->sum('amount');

            return [
                'revenue' => $sales,
                'purchases' => $purchases,
                'expenses' => $expenses,
                'gross_profit' => $sales - $purchases,
                'net_profit' => $sales - $purchases - $expenses,
            ];
        });
    }

    /**
     * Warmup du cache pour un tenant
     */
    public static function warmup(int $tenantId): void
    {
        // Pré-charger les statistiques courantes
        self::getDashboardStats($tenantId, 'day');
        self::getDashboardStats($tenantId, 'month');
        self::getProducts($tenantId);
        
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();
        self::getAccountingStats($tenantId, $from, $to);
    }
}
