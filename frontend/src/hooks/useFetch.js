import { useState, useEffect, useCallback, useRef } from 'react';
import apiClient from '../services/apiClient';

// Cache global en mémoire
const cache = new Map();
const CACHE_TTL = 30000; // 30 secondes

export function useFetch(url, options = {}) {
  const { 
    immediate = true, 
    cacheKey = url,
    cacheTTL = CACHE_TTL,
    transform = (data) => data 
  } = options;
  
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(immediate);
  const [error, setError] = useState(null);
  const abortRef = useRef(null);

  const fetchData = useCallback(async (forceRefresh = false) => {
    // Vérifier le cache
    if (!forceRefresh && cacheKey) {
      const cached = cache.get(cacheKey);
      if (cached && Date.now() - cached.time < cacheTTL) {
        setData(cached.data);
        setLoading(false);
        return cached.data;
      }
    }

    // Annuler la requête précédente
    if (abortRef.current) {
      abortRef.current.abort();
    }
    abortRef.current = new AbortController();

    setLoading(true);
    setError(null);

    try {
      const response = await apiClient.get(url, {
        signal: abortRef.current.signal
      });
      
      const result = transform(response.data?.data || response.data || []);
      
      // Mettre en cache
      if (cacheKey) {
        cache.set(cacheKey, { data: result, time: Date.now() });
      }
      
      setData(result);
      setLoading(false);
      return result;
    } catch (err) {
      if (err.name !== 'AbortError') {
        setError(err.message);
        setLoading(false);
      }
      return null;
    }
  }, [url, cacheKey, cacheTTL, transform]);

  const refresh = useCallback(() => fetchData(true), [fetchData]);

  useEffect(() => {
    if (immediate) {
      fetchData();
    }
    return () => {
      if (abortRef.current) {
        abortRef.current.abort();
      }
    };
  }, [immediate, fetchData]);

  return { data, loading, error, refresh, fetch: fetchData };
}

// Hook pour fetch multiple en parallèle
export function useMultiFetch(urls, options = {}) {
  const [data, setData] = useState({});
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchAll = async () => {
      const results = {};
      
      await Promise.all(
        Object.entries(urls).map(async ([key, url]) => {
          try {
            const cached = cache.get(url);
            if (cached && Date.now() - cached.time < CACHE_TTL) {
              results[key] = cached.data;
              return;
            }
            
            const response = await apiClient.get(url);
            const data = response.data?.data || response.data || [];
            cache.set(url, { data, time: Date.now() });
            results[key] = data;
          } catch (e) {
            results[key] = [];
          }
        })
      );
      
      setData(results);
      setLoading(false);
    };

    fetchAll();
  }, []);

  return { data, loading };
}

// Vider le cache
export function clearCache(key = null) {
  if (key) {
    cache.delete(key);
  } else {
    cache.clear();
  }
}
