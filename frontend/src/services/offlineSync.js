import { openDB } from 'idb';

const DB_NAME = 'sigec-offline';
const DB_VERSION = 1;
const STORES = {
  PENDING_SALES: 'pending_sales',
  PRODUCTS: 'products',
  CACHE: 'cache',
};

export class OfflineSyncService {
  static async initDB() {
    return openDB(DB_NAME, DB_VERSION, {
      upgrade(db) {
        if (!db.objectStoreNames.contains(STORES.PENDING_SALES)) {
          db.createObjectStore(STORES.PENDING_SALES, { keyPath: 'id', autoIncrement: true });
        }
        if (!db.objectStoreNames.contains(STORES.PRODUCTS)) {
          db.createObjectStore(STORES.PRODUCTS, { keyPath: 'id' });
        }
        if (!db.objectStoreNames.contains(STORES.CACHE)) {
          db.createObjectStore(STORES.CACHE, { keyPath: 'key' });
        }
      },
    });
  }

  // Sauvegarder une vente en attente
  static async savePendingSale(saleData) {
    const db = await this.initDB();
    return db.add(STORES.PENDING_SALES, {
      ...saleData,
      createdAt: Date.now(),
      synced: false,
    });
  }

  // Obtenir toutes les ventes en attente
  static async getPendingSales() {
    const db = await this.initDB();
    return db.getAll(STORES.PENDING_SALES);
  }

  // Synchroniser les ventes en attente
  static async syncPendingSales(apiClient) {
    const pendingSales = await this.getPendingSales();
    const results = [];

    for (const sale of pendingSales) {
      try {
        const response = await apiClient.post('/sales', sale);
        const saleId = response.data.id;

        // Compléter la vente
        await apiClient.post(`/sales/${saleId}/complete`, {
          amount_paid: sale.total,
          payment_method: sale.payment_method,
        });

        // Marquer comme synchronisé
        const db = await this.initDB();
        await db.delete(STORES.PENDING_SALES, sale.id);

        results.push({ success: true, id: sale.id });
      } catch (error) {
        results.push({ success: false, id: sale.id, error: error.message });
      }
    }

    return results;
  }

  // Sauvegarder les produits pour accès hors ligne
  static async cacheProducts(products) {
    const db = await this.initDB();
    const tx = db.transaction(STORES.PRODUCTS, 'readwrite');
    
    for (const product of products) {
      await tx.store.put(product);
    }
    
    await tx.done();
  }

  // Obtenir les produits en cache
  static async getCachedProducts() {
    const db = await this.initDB();
    return db.getAll(STORES.PRODUCTS);
  }

  // Vérifier la connectivité
  static isOnline() {
    return navigator.onLine;
  }

  // Surveiller les changements de connectivité
  static onOnlineStatusChange(callback) {
    window.addEventListener('online', () => callback(true));
    window.addEventListener('offline', () => callback(false));
  }

  // Nettoyer les anciennes données
  static async cleanup() {
    const db = await this.initDB();
    const allSales = await db.getAll(STORES.PENDING_SALES);
    
    // Supprimer les ventes plus anciennes que 7 jours
    for (const sale of allSales) {
      if (Date.now() - sale.createdAt > 7 * 24 * 60 * 60 * 1000) {
        await db.delete(STORES.PENDING_SALES, sale.id);
      }
    }
  }
}
