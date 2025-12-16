import api from './apiClient';

/**
 * Service pour la gestion du stock délégué aux serveurs (Option B)
 */
const serverStockService = {
  // Liste des stocks délégués
  getAll: (params = {}) => api.get('/server-stock', { params }),

  // Mon stock (serveur)
  getMyStock: () => api.get('/server-stock/my-stock'),

  // Déléguer du stock à un serveur (gérant)
  delegate: (data) => api.post('/server-stock/delegate', data),

  // Enregistrer une vente depuis le stock délégué
  recordSale: (data) => api.post('/server-stock/sale', data),

  // Déclarer une perte
  declareLoss: (data) => api.post('/server-stock/loss', data),

  // Historique des mouvements
  getMovements: (params = {}) => api.get('/server-stock/movements', { params }),

  // Statistiques (gérant)
  getStatistics: (params = {}) => api.get('/server-stock/statistics', { params }),

  // Réconciliation
  startReconciliation: () => api.post('/server-stock/reconciliation/start'),
  submitReconciliation: (id, data) => api.post(`/server-stock/reconciliation/${id}/submit`, data),
  getPendingReconciliations: () => api.get('/server-stock/reconciliation/pending'),
  validateReconciliation: (id, data) => api.post(`/server-stock/reconciliation/${id}/validate`, data),
  disputeReconciliation: (id, data) => api.post(`/server-stock/reconciliation/${id}/dispute`, data),

  // Info tenant (mode et option)
  getTenantMode: () => api.get('/tenant/mode'),
};

export default serverStockService;
