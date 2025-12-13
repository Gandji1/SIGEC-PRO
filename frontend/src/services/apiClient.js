import axios from 'axios';

// URL de base - /api relatif fonctionne avec le proxy Vite
const API_URL = import.meta.env.VITE_API_URL || '/api';

const apiClient = axios.create({
  baseURL: API_URL,
  timeout: 8000, // Réduit de 15s à 8s pour fail-fast
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Cache simple en mémoire pour les requêtes GET
const requestCache = new Map();
const CACHE_TTL = 30000; // 30 secondes

// Add tenant header and token to all requests
apiClient.interceptors.request.use((config) => {
  // Get tenant_id (optimisé - une seule lecture)
  let tenantId = localStorage.getItem('tenant_id');
  if (!tenantId) {
    const tenantStr = localStorage.getItem('tenant');
    if (tenantStr) {
      try {
        tenantId = JSON.parse(tenantStr)?.id;
        if (tenantId) localStorage.setItem('tenant_id', tenantId); // Cache pour prochaine fois
      } catch {
        // Silent fail
      }
    }
  }
  
  if (tenantId) {
    config.headers['X-Tenant-ID'] = tenantId;
  }
  
  const token = localStorage.getItem('token');
  if (token) {
    config.headers['Authorization'] = `Bearer ${token}`;
  }
  
  return config;
});

// Handle responses avec retry automatique
apiClient.interceptors.response.use(
  (response) => {
    // Cache les réponses GET réussies
    if (response.config.method === 'get' && response.config.cache !== false) {
      const cacheKey = response.config.url + JSON.stringify(response.config.params || {});
      requestCache.set(cacheKey, {
        data: response,
        timestamp: Date.now(),
      });
    }
    return response;
  },
  async (error) => {
    const config = error.config;
    
    // Retry une fois pour les erreurs réseau (pas les 4xx/5xx)
    if (!config._retry && !error.response && error.code !== 'ECONNABORTED') {
      config._retry = true;
      return apiClient(config);
    }
    
    return Promise.reject(error);
  }
);

/**
 * GET avec cache optionnel
 */
export const cachedGet = async (url, params = {}, ttl = CACHE_TTL) => {
  const cacheKey = url + JSON.stringify(params);
  const cached = requestCache.get(cacheKey);
  
  if (cached && (Date.now() - cached.timestamp) < ttl) {
    return cached.data;
  }
  
  const response = await apiClient.get(url, { params });
  requestCache.set(cacheKey, { data: response, timestamp: Date.now() });
  return response;
};

/**
 * Invalider le cache pour une URL
 */
export const invalidateCache = (urlPattern) => {
  for (const key of requestCache.keys()) {
    if (key.includes(urlPattern)) {
      requestCache.delete(key);
    }
  }
};

/**
 * Vider tout le cache
 */
export const clearCache = () => {
  requestCache.clear();
};

export default apiClient;
