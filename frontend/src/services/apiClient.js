import axios from "axios";
import corsProxy from "./corsProxy";

// URL de base - /api relatif fonctionne avec le proxy Vite
const API_URL = import.meta.env.VITE_API_URL || "/api";

const apiClient = axios.create({
  baseURL: API_URL,
  timeout: 30000, // 30 secondes pour les requêtes lentes
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
  // Configuration CORS pour éviter les erreurs de pré-vérification
  withCredentials: true,
});

// Configuration pour les environnements de développement
if (import.meta.env.DEV) {
  // En développement, on peut utiliser un proxy ou une configuration spéciale
  apiClient.defaults.withCredentials = false;

  // Appliquer le middleware de proxy CORS
  corsProxy.setupCorsProxyMiddleware(apiClient);
}

// Cache simple en mémoire pour les requêtes GET
const requestCache = new Map();
const CACHE_TTL = 300000; // 5 minutes pour réduire les appels API

// Add tenant header and token to all requests
apiClient.interceptors.request.use((config) => {
  // Get tenant_id from multiple sources
  let tenantId = localStorage.getItem("tenant_id");
  
  if (!tenantId) {
    const tenantStr = localStorage.getItem("tenant");
    if (tenantStr) {
      try {
        const tenant = JSON.parse(tenantStr);
        tenantId = tenant?.id;
      } catch {
        // Silent fail
      }
    }
  }
  
  // Also try from user object
  if (!tenantId) {
    const userStr = localStorage.getItem("user");
    if (userStr) {
      try {
        const user = JSON.parse(userStr);
        tenantId = user?.tenant_id;
      } catch {
        // Silent fail
      }
    }
  }

  if (tenantId) {
    config.headers["X-Tenant-ID"] = String(tenantId);
  }

  const token = localStorage.getItem("token");
  if (token) {
    config.headers["Authorization"] = `Bearer ${token}`;
  }

  return config;
});

// Handle responses avec retry automatique
apiClient.interceptors.response.use(
  (response) => {
    // Cache les réponses GET réussies
    if (response.config.method === "get" && response.config.cache !== false) {
      const cacheKey =
        response.config.url + JSON.stringify(response.config.params || {});
      requestCache.set(cacheKey, {
        data: response,
        timestamp: Date.now(),
      });
    }
    return response;
  },
  async (error) => {
    const config = error.config;

    if (import.meta.env.DEV && error.response?.status === 422) {
      const url = error.response?.config?.baseURL
        ? `${error.response.config.baseURL}${error.response.config.url}`
        : error.response?.config?.url;
      const data = error.response?.data;
      console.error('[API 422] Validation error', {
        url,
        method: error.response?.config?.method,
        message: data?.message,
        errors: data?.errors,
        data,
      });
    }

    // Retry une fois pour les erreurs réseau (pas les 4xx/5xx)
    if (!config._retry && !error.response && error.code !== "ECONNABORTED") {
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

  if (cached && Date.now() - cached.timestamp < ttl) {
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
