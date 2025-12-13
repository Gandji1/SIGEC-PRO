<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pour mettre en cache les réponses API
 * Améliore les performances pour les endpoints fréquemment consultés
 */
class CacheResponse
{
    /**
     * Durée du cache par défaut en secondes (5 minutes)
     */
    protected int $defaultTtl = 300;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $ttl = null): Response
    {
        // Ne pas mettre en cache les requêtes non-GET
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // Ne pas mettre en cache si l'utilisateur n'est pas authentifié
        if (!$request->user()) {
            return $next($request);
        }

        // Générer une clé de cache unique
        $cacheKey = $this->generateCacheKey($request);
        $cacheTtl = $ttl ?? $this->defaultTtl;

        // Vérifier si la réponse est en cache
        if (Cache::has($cacheKey)) {
            $cachedResponse = Cache::get($cacheKey);
            return response()->json($cachedResponse['data'], $cachedResponse['status'])
                ->header('X-Cache', 'HIT')
                ->header('X-Cache-Key', substr($cacheKey, 0, 20));
        }

        // Exécuter la requête
        $response = $next($request);

        // Mettre en cache uniquement les réponses réussies
        if ($response->getStatusCode() === 200) {
            $responseData = [
                'data' => json_decode($response->getContent(), true),
                'status' => $response->getStatusCode(),
            ];
            Cache::put($cacheKey, $responseData, $cacheTtl);
        }

        return $response->header('X-Cache', 'MISS');
    }

    /**
     * Générer une clé de cache unique basée sur la requête
     */
    protected function generateCacheKey(Request $request): string
    {
        $user = $request->user();
        $tenantId = $user->tenant_id ?? 'global';
        $path = $request->path();
        $query = $request->query();
        
        // Trier les paramètres pour une clé cohérente
        ksort($query);
        $queryString = http_build_query($query);

        return "api_cache:{$tenantId}:{$path}:{$queryString}";
    }

    /**
     * Invalider le cache pour un tenant
     */
    public static function invalidateForTenant(int $tenantId): void
    {
        // Utiliser un pattern pour supprimer toutes les clés du tenant
        $pattern = "api_cache:{$tenantId}:*";
        
        // Note: Cette méthode nécessite Redis pour fonctionner avec les patterns
        // Pour le cache fichier, on peut utiliser des tags
        if (config('cache.default') === 'redis') {
            $keys = Cache::getRedis()->keys($pattern);
            foreach ($keys as $key) {
                Cache::forget(str_replace(config('cache.prefix') . ':', '', $key));
            }
        }
    }

    /**
     * Invalider le cache pour un endpoint spécifique
     */
    public static function invalidateEndpoint(int $tenantId, string $endpoint): void
    {
        $pattern = "api_cache:{$tenantId}:{$endpoint}";
        Cache::forget($pattern);
    }
}
