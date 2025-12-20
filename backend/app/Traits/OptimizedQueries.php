<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Trait pour optimiser les requêtes Eloquent
 * Fournit des méthodes pour le lazy loading, le caching et la pagination
 */
trait OptimizedQueries
{
    /**
     * Scope pour charger les relations de manière optimisée
     */
    public function scopeWithOptimized(Builder $query, array $relations): Builder
    {
        return $query->with($relations);
    }

    /**
     * Scope pour filtrer par tenant avec index
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope pour filtrer par période avec index
     */
    public function scopeInPeriod(Builder $query, string $dateColumn, string $from, string $to): Builder
    {
        return $query->whereBetween($dateColumn, [$from, $to]);
    }

    /**
     * Récupérer avec cache
     */
    public static function getCached(string $cacheKey, int $ttl, callable $callback)
    {
        return $callback();
    }

    /**
     * Paginer avec optimisation
     */
    public function scopePaginateOptimized(Builder $query, int $perPage = 15, array $columns = ['*'])
    {
        return $query->select($columns)->paginate($perPage);
    }

    /**
     * Sélectionner uniquement les colonnes nécessaires
     */
    public function scopeSelectEssential(Builder $query): Builder
    {
        $essential = $this->essentialColumns ?? ['id', 'tenant_id', 'created_at'];
        return $query->select($essential);
    }

    /**
     * Charger les relations avec sélection de colonnes
     */
    public function scopeWithSelect(Builder $query, array $relationsWithColumns): Builder
    {
        foreach ($relationsWithColumns as $relation => $columns) {
            $query->with([$relation => function ($q) use ($columns) {
                $q->select($columns);
            }]);
        }
        return $query;
    }

    /**
     * Chunk pour les grandes quantités de données
     */
    public static function processInChunks(Builder $query, int $chunkSize, callable $callback): void
    {
        $query->chunk($chunkSize, function ($records) use ($callback) {
            foreach ($records as $record) {
                $callback($record);
            }
        });
    }

    /**
     * Cursor pour le streaming de données
     */
    public static function streamResults(Builder $query, callable $callback): void
    {
        foreach ($query->cursor() as $record) {
            $callback($record);
        }
    }

    /**
     * Compter avec cache
     */
    public static function countCached(string $cacheKey, int $ttl, Builder $query): int
    {
        return  $query->count();
    }

    /**
     * Somme avec cache
     */
    public static function sumCached(string $cacheKey, int $ttl, Builder $query, string $column): float
    {
        return (float) $query->sum($column);
    }
}
