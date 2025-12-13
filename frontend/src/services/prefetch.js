import apiClient from './apiClient';
import { useCacheStore, CACHE_KEYS } from '../stores/cacheStore';

/**
 * Service de préchargement intelligent
 * Charge les données critiques en arrière-plan pour un affichage instantané
 */

// File d'attente de préchargement
let prefetchQueue = [];
let isPrefetching = false;

/**
 * Ajoute une requête à la file de préchargement
 */
function queuePrefetch(cacheKey, endpoint, params = {}) {
  // Éviter les doublons
  if (prefetchQueue.some(item => item.cacheKey === cacheKey)) return;
  
  prefetchQueue.push({ cacheKey, endpoint, params });
  processPrefetchQueue();
}

/**
 * Traite la file de préchargement
 */
async function processPrefetchQueue() {
  if (isPrefetching || prefetchQueue.length === 0) return;
  
  isPrefetching = true;
  const store = useCacheStore.getState();

  while (prefetchQueue.length > 0) {
    const { cacheKey, endpoint, params } = prefetchQueue.shift();
    
    // Vérifier si déjà en cache
    if (store.get(cacheKey)) continue;

    try {
      const queryString = new URLSearchParams(params).toString();
      const url = queryString ? `${endpoint}?${queryString}` : endpoint;
      
      const response = await apiClient.get(url);
      const data = response.data?.data || response.data || [];
      
      store.set(cacheKey, data);
    } catch (err) {
      console.warn(`Prefetch failed for ${cacheKey}:`, err.message);
    }

    // Petit délai pour ne pas surcharger
    await new Promise(resolve => setTimeout(resolve, 50));
  }

  isPrefetching = false;
}

/**
 * Précharge les données essentielles après connexion
 * Appelé une fois après le login
 */
export async function prefetchEssentialData() {
  // Données critiques à précharger immédiatement
  const essentialEndpoints = [
    { key: CACHE_KEYS.PRODUCTS_ACTIVE, endpoint: '/products', params: { per_page: 500, status: 'active' } },
    { key: CACHE_KEYS.WAREHOUSES, endpoint: '/warehouses', params: { per_page: 100 } },
    { key: CACHE_KEYS.DASHBOARD_STATS, endpoint: '/dashboard/stats', params: {} },
  ];

  // Charger en parallèle
  const store = useCacheStore.getState();
  
  await Promise.allSettled(
    essentialEndpoints.map(async ({ key, endpoint, params }) => {
      if (store.get(key)) return; // Déjà en cache
      
      try {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        
        const response = await apiClient.get(url);
        const data = response.data?.data || response.data || [];
        
        store.set(key, data);
      } catch (err) {
        // Silencieux - ce n'est que du préchargement
      }
    })
  );
}

/**
 * Précharge les données secondaires en arrière-plan
 * Appelé après le chargement initial
 */
export function prefetchSecondaryData() {
  // Données secondaires à précharger en différé
  queuePrefetch(CACHE_KEYS.CUSTOMERS, '/customers', { per_page: 500 });
  queuePrefetch(CACHE_KEYS.SUPPLIERS, '/suppliers', { per_page: 500 });
  queuePrefetch(CACHE_KEYS.LOW_STOCK, '/stocks/low-stock', {});
}

/**
 * Précharge les données pour une page spécifique
 */
export function prefetchForPage(pageName) {
  switch (pageName) {
    case 'pos':
      queuePrefetch(CACHE_KEYS.PRODUCTS_ACTIVE, '/products', { per_page: 500, status: 'active' });
      queuePrefetch(CACHE_KEYS.CUSTOMERS, '/customers', { per_page: 500 });
      break;
    case 'products':
      queuePrefetch(CACHE_KEYS.PRODUCTS, '/products', { per_page: 500 });
      queuePrefetch(CACHE_KEYS.CATEGORIES, '/categories', {});
      break;
    case 'accounting':
      queuePrefetch(CACHE_KEYS.CHART_OF_ACCOUNTS, '/chart-of-accounts', {});
      break;
    case 'inventory':
      queuePrefetch(CACHE_KEYS.WAREHOUSES, '/warehouses', { per_page: 100 });
      queuePrefetch(CACHE_KEYS.LOW_STOCK, '/stocks/low-stock', {});
      break;
    default:
      break;
  }
}

/**
 * Invalide le cache après une mutation
 */
export function invalidateCacheAfterMutation(entityType) {
  const store = useCacheStore.getState();
  
  switch (entityType) {
    case 'product':
      store.invalidatePrefix('products');
      break;
    case 'customer':
      store.invalidate(CACHE_KEYS.CUSTOMERS);
      break;
    case 'supplier':
      store.invalidate(CACHE_KEYS.SUPPLIERS);
      break;
    case 'stock':
      store.invalidate(CACHE_KEYS.LOW_STOCK);
      store.invalidatePrefix('stocks');
      break;
    case 'sale':
      store.invalidate(CACHE_KEYS.DASHBOARD_STATS);
      store.invalidate(CACHE_KEYS.LOW_STOCK);
      break;
    default:
      break;
  }
}

export default {
  prefetchEssentialData,
  prefetchSecondaryData,
  prefetchForPage,
  invalidateCacheAfterMutation,
};
