import { useState, useEffect, useCallback, useRef } from 'react';
import { useCacheStore, CACHE_KEYS } from '../stores/cacheStore';
import apiClient from '../services/apiClient';

/**
 * Hook pour charger des données avec cache intelligent
 * Évite les requêtes redondantes et affiche les données instantanément
 * 
 * @param {string} cacheKey - Clé unique du cache
 * @param {string} endpoint - Endpoint API
 * @param {object} options - Options de configuration
 * @returns {object} { data, loading, error, refresh, isFromCache }
 */
export function useCachedData(cacheKey, endpoint, options = {}) {
  const {
    maxAge = 5 * 60 * 1000, // 5 minutes par défaut
    params = {},
    enabled = true,
    transform = (data) => data,
    onSuccess,
    onError,
  } = options;

  const { get: getCache, set: setCache, isLoading, setLoading } = useCacheStore();
  
  const [data, setData] = useState(() => getCache(cacheKey, maxAge));
  const [error, setError] = useState(null);
  const [loading, setLoadingState] = useState(!data);
  const [isFromCache, setIsFromCache] = useState(!!data);
  
  const mountedRef = useRef(true);
  const fetchingRef = useRef(false);

  const fetchData = useCallback(async (force = false) => {
    // Éviter les requêtes dupliquées
    if (fetchingRef.current && !force) return;
    
    // Vérifier le cache d'abord (sauf si force refresh)
    if (!force) {
      const cached = getCache(cacheKey, maxAge);
      if (cached) {
        setData(cached);
        setIsFromCache(true);
        setLoadingState(false);
        return cached;
      }
    }

    // Vérifier si déjà en cours de chargement global
    if (isLoading(cacheKey) && !force) return;

    fetchingRef.current = true;
    setLoading(cacheKey, true);
    setLoadingState(true);
    setError(null);

    try {
      const queryString = new URLSearchParams(params).toString();
      const url = queryString ? `${endpoint}?${queryString}` : endpoint;
      
      const response = await apiClient.get(url);
      const rawData = response.data?.data || response.data || [];
      const transformedData = transform(rawData);

      if (mountedRef.current) {
        setData(transformedData);
        setCache(cacheKey, transformedData);
        setIsFromCache(false);
        onSuccess?.(transformedData);
      }

      return transformedData;
    } catch (err) {
      if (mountedRef.current) {
        setError(err.message || 'Erreur de chargement');
        onError?.(err);
      }
      return null;
    } finally {
      fetchingRef.current = false;
      setLoading(cacheKey, false);
      if (mountedRef.current) {
        setLoadingState(false);
      }
    }
  }, [cacheKey, endpoint, params, maxAge, getCache, setCache, isLoading, setLoading, transform, onSuccess, onError]);

  // Charger les données au montage
  useEffect(() => {
    mountedRef.current = true;
    
    if (enabled) {
      fetchData();
    }

    return () => {
      mountedRef.current = false;
    };
  }, [enabled, cacheKey]);

  // Fonction de rafraîchissement forcé
  const refresh = useCallback(() => fetchData(true), [fetchData]);

  return {
    data,
    loading,
    error,
    refresh,
    isFromCache,
  };
}

/**
 * Hooks pré-configurés pour les données courantes
 */
export function useProducts(options = {}) {
  return useCachedData(
    CACHE_KEYS.PRODUCTS,
    '/products',
    { params: { per_page: 500, status: 'active' }, ...options }
  );
}

export function useCustomers(options = {}) {
  return useCachedData(
    CACHE_KEYS.CUSTOMERS,
    '/customers',
    { params: { per_page: 500 }, ...options }
  );
}

export function useSuppliers(options = {}) {
  return useCachedData(
    CACHE_KEYS.SUPPLIERS,
    '/suppliers',
    { params: { per_page: 500 }, ...options }
  );
}

export function useWarehouses(options = {}) {
  return useCachedData(
    CACHE_KEYS.WAREHOUSES,
    '/warehouses',
    { params: { per_page: 100 }, ...options }
  );
}

export default useCachedData;
