import { create } from 'zustand';

/**
 * Store de cache intelligent pour données statiques
 * Réduit les appels API en gardant les données en mémoire
 */
const CACHE_DURATION = 5 * 60 * 1000; // 5 minutes par défaut

export const useCacheStore = create((set, get) => ({
  // Cache des données
  cache: {},
  
  // Timestamps des dernières mises à jour
  timestamps: {},
  
  // Données en cours de chargement (évite les requêtes dupliquées)
  loading: {},

  /**
   * Récupère une donnée du cache si valide
   * @param {string} key - Clé du cache
   * @param {number} maxAge - Durée de validité en ms
   * @returns {any|null} Données ou null si expiré/inexistant
   */
  get: (key, maxAge = CACHE_DURATION) => {
    const { cache, timestamps } = get();
    const timestamp = timestamps[key];
    
    if (!timestamp || !cache[key]) return null;
    if (Date.now() - timestamp > maxAge) return null;
    
    return cache[key];
  },

  /**
   * Stocke une donnée dans le cache
   * @param {string} key - Clé du cache
   * @param {any} data - Données à stocker
   */
  set: (key, data) => {
    set((state) => ({
      cache: { ...state.cache, [key]: data },
      timestamps: { ...state.timestamps, [key]: Date.now() },
    }));
  },

  /**
   * Marque une clé comme en cours de chargement
   */
  setLoading: (key, isLoading) => {
    set((state) => ({
      loading: { ...state.loading, [key]: isLoading },
    }));
  },

  /**
   * Vérifie si une clé est en cours de chargement
   */
  isLoading: (key) => get().loading[key] || false,

  /**
   * Invalide une entrée du cache
   */
  invalidate: (key) => {
    set((state) => {
      const { [key]: _, ...restCache } = state.cache;
      const { [key]: __, ...restTimestamps } = state.timestamps;
      return { cache: restCache, timestamps: restTimestamps };
    });
  },

  /**
   * Invalide toutes les entrées commençant par un préfixe
   */
  invalidatePrefix: (prefix) => {
    set((state) => {
      const newCache = {};
      const newTimestamps = {};
      
      Object.keys(state.cache).forEach((key) => {
        if (!key.startsWith(prefix)) {
          newCache[key] = state.cache[key];
          newTimestamps[key] = state.timestamps[key];
        }
      });
      
      return { cache: newCache, timestamps: newTimestamps };
    });
  },

  /**
   * Vide tout le cache
   */
  clear: () => set({ cache: {}, timestamps: {}, loading: {} }),
}));

// Clés de cache prédéfinies
export const CACHE_KEYS = {
  PRODUCTS: 'products',
  PRODUCTS_ACTIVE: 'products_active',
  CUSTOMERS: 'customers',
  SUPPLIERS: 'suppliers',
  WAREHOUSES: 'warehouses',
  CATEGORIES: 'categories',
  USERS: 'users',
  DASHBOARD_STATS: 'dashboard_stats',
  LOW_STOCK: 'low_stock',
  CHART_OF_ACCOUNTS: 'chart_of_accounts',
};

export default useCacheStore;
