import React, { useState, useEffect, useCallback, memo } from 'react';
import { useParams } from 'react-router-dom';
import { useTenantStore } from '../stores/tenantStore';
import apiClient from '../services/apiClient';
import { Package, Truck, CheckCircle, XCircle, Clock, RefreshCw, Eye, Send, CreditCard, History, TrendingUp, AlertTriangle } from 'lucide-react';
import { PageLoader, ErrorMessage, EmptyState } from '../components/LoadingFallback';

const StatusBadge = ({ status }) => {
  const configs = {
    pending: { bg: 'bg-yellow-100', text: 'text-yellow-700', label: 'En attente' },
    submitted: { bg: 'bg-orange-100', text: 'text-orange-700', label: 'Soumise' },
    confirmed: { bg: 'bg-blue-100', text: 'text-blue-700', label: 'Confirmée' },
    shipped: { bg: 'bg-purple-100', text: 'text-purple-700', label: 'Préparée' },
    delivered: { bg: 'bg-indigo-100', text: 'text-indigo-700', label: 'Livrée' },
    received: { bg: 'bg-teal-100', text: 'text-teal-700', label: 'Réceptionnée' },
    paid: { bg: 'bg-green-100', text: 'text-green-700', label: 'Payée' },
    cancelled: { bg: 'bg-red-100', text: 'text-red-700', label: 'Annulée' },
  };
  const config = configs[status] || configs.pending;
  return <span className={`px-2 py-1 rounded-full text-xs font-medium ${config.bg} ${config.text}`}>{config.label}</span>;
};

export default function SupplierPortalPage() {
  const { id: orderIdFromUrl } = useParams();
  const { user } = useTenantStore();
  const [dashboard, setDashboard] = useState(null);
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [selectedOrder, setSelectedOrder] = useState(null);
  const [showDetailModal, setShowDetailModal] = useState(false);
  const [showConfirmModal, setShowConfirmModal] = useState(false);
  const [confirmData, setConfirmData] = useState({ expected_delivery_date: '', notes: '' });
  const [message, setMessage] = useState({ type: '', text: '' });

  const fetchDashboard = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get('/supplier-portal/dashboard');
      const data = res.data?.data || res.data || {};
      setDashboard(data);
      const ordersList = Array.isArray(data?.orders) ? data.orders : [];
      setOrders(ordersList);
      setMessage({ type: '', text: '' });
      
      // Si un ID de commande est dans l'URL, ouvrir automatiquement le détail
      if (orderIdFromUrl && ordersList.length > 0) {
        const targetOrder = ordersList.find(o => String(o.id) === String(orderIdFromUrl));
        if (targetOrder) {
          setSelectedOrder(targetOrder);
          setShowDetailModal(true);
        }
      }
    } catch (err) {
      console.error('Erreur portail fournisseur:', err);
      
      // Gérer les différents cas d'erreur
      if (err.response?.status === 404) {
        // Fournisseur non configuré - afficher un état vide plutôt qu'une erreur
        setDashboard({
          supplier: { name: user?.name, email: user?.email },
          orders: [],
          stats: { total_orders: 0, pending_orders: 0, confirmed_orders: 0, delivered_orders: 0, paid_orders: 0 },
          message: 'Votre compte fournisseur sera lié automatiquement lors de la prochaine commande.'
        });
        setOrders([]);
      } else if (err.response?.status === 403) {
        setMessage({ type: 'error', text: 'Accès non autorisé au portail fournisseur.' });
      } else {
        setMessage({ type: 'error', text: err.response?.data?.message || 'Erreur de chargement des données' });
      }
    } finally {
      setLoading(false);
    }
  }, [user, orderIdFromUrl]);

  useEffect(() => {
    fetchDashboard();
  }, [fetchDashboard]);

  const handleConfirmOrder = async () => {
    if (!selectedOrder) return;
    setActionLoading(true);
    try {
      await apiClient.post(`/supplier-portal/orders/${selectedOrder.id}/confirm`, confirmData);
      setMessage({ type: 'success', text: 'Commande confirmée!' });
      setShowConfirmModal(false);
      fetchDashboard();
    } catch (err) {
      setMessage({ type: 'error', text: err.response?.data?.message || 'Erreur' });
    } finally {
      setActionLoading(false);
      setTimeout(() => setMessage({ type: '', text: '' }), 3000);
    }
  };

  const handleShipOrder = async (orderId) => {
    if (!window.confirm('Marquer cette commande comme préparée?')) return;
    setActionLoading(true);
    try {
      await apiClient.post(`/supplier-portal/orders/${orderId}/ship`);
      setMessage({ type: 'success', text: 'Commande préparée!' });
      fetchDashboard();
    } catch (err) {
      setMessage({ type: 'error', text: err.response?.data?.message || 'Erreur' });
    } finally {
      setActionLoading(false);
      setTimeout(() => setMessage({ type: '', text: '' }), 3000);
    }
  };

  const handleDeliverOrder = async (orderId) => {
    if (!window.confirm('Confirmer la livraison de cette commande? Le tenant sera notifié pour réception.')) return;
    setActionLoading(true);
    try {
      await apiClient.post(`/supplier-portal/orders/${orderId}/deliver`);
      setMessage({ type: 'success', text: 'Commande livrée! Le tenant a été notifié.' });
      fetchDashboard();
    } catch (err) {
      setMessage({ type: 'error', text: err.response?.data?.message || 'Erreur' });
    } finally {
      setActionLoading(false);
      setTimeout(() => setMessage({ type: '', text: '' }), 3000);
    }
  };

  const handleValidatePayment = async (orderId) => {
    if (!window.confirm('Confirmer la réception du paiement?')) return;
    setActionLoading(true);
    try {
      await apiClient.post(`/supplier-portal/orders/${orderId}/validate-payment`);
      setMessage({ type: 'success', text: 'Paiement validé!' });
      fetchDashboard();
    } catch (err) {
      setMessage({ type: 'error', text: err.response?.data?.message || 'Erreur' });
    } finally {
      setActionLoading(false);
      setTimeout(() => setMessage({ type: '', text: '' }), 3000);
    }
  };

  const handleRejectOrder = async (orderId) => {
    const reason = window.prompt('Raison du rejet:');
    if (!reason) return;
    setActionLoading(true);
    try {
      await apiClient.post(`/supplier-portal/orders/${orderId}/reject`, { reason });
      setMessage({ type: 'success', text: 'Commande rejetée' });
      fetchDashboard();
    } catch (err) {
      setMessage({ type: 'error', text: err.response?.data?.message || 'Erreur' });
    } finally {
      setActionLoading(false);
      setTimeout(() => setMessage({ type: '', text: '' }), 3000);
    }
  };

  const formatPrice = (p) => new Intl.NumberFormat('fr-FR').format(p || 0) + ' FCFA';
  const formatDate = (d) => d ? new Date(d).toLocaleDateString('fr-FR') : '-';

  if (loading) {
    return (
      <div className="p-6 animate-pulse">
        <div className="h-8 bg-gray-200 rounded w-64 mb-6"></div>
        <div className="grid grid-cols-4 gap-4 mb-6">
          {[1,2,3,4].map(i => <div key={i} className="h-24 bg-gray-200 rounded-xl"></div>)}
        </div>
        <div className="h-96 bg-gray-200 rounded-xl"></div>
      </div>
    );
  }

  const stats = dashboard?.stats || {};

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <Package className="text-blue-600" /> Portail Fournisseur
          </h1>
          <p className="text-gray-600">Bienvenue, {dashboard?.supplier?.name || user?.name}</p>
        </div>
        <button 
          onClick={fetchDashboard} 
          disabled={loading}
          className="p-2 bg-white border rounded-lg hover:bg-gray-50"
        >
          <RefreshCw className={`w-5 h-5 ${loading ? 'animate-spin' : ''}`} />
        </button>
      </div>

      {/* Message d'erreur */}
      {message.text && (
        <div className={`p-3 rounded-lg ${message.type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
          {message.text}
        </div>
      )}
      
      {/* Message informatif du dashboard */}
      {dashboard?.message && (
        <div className="p-4 rounded-lg bg-blue-50 text-blue-700 border border-blue-200">
          <p className="font-medium">ℹ️ {dashboard.message}</p>
        </div>
      )}

      {/* KPIs */}
      <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
        <div className="bg-white rounded-xl p-3 border shadow-sm">
          <p className="text-xs text-gray-500">Total</p>
          <p className="text-xl font-bold text-gray-800">{stats.total_orders || 0}</p>
        </div>
        <div className="bg-yellow-50 rounded-xl p-3 border border-yellow-200">
          <p className="text-xs text-yellow-700">En Attente</p>
          <p className="text-xl font-bold text-yellow-800">{stats.pending_orders || 0}</p>
        </div>
        <div className="bg-blue-50 rounded-xl p-3 border border-blue-200">
          <p className="text-xs text-blue-700">Confirmées</p>
          <p className="text-xl font-bold text-blue-800">{stats.confirmed_orders || 0}</p>
        </div>
        <div className="bg-indigo-50 rounded-xl p-3 border border-indigo-200">
          <p className="text-xs text-indigo-700">Livrées</p>
          <p className="text-xl font-bold text-indigo-800">{stats.delivered_orders || 0}</p>
        </div>
        <div className="bg-green-50 rounded-xl p-3 border border-green-200">
          <p className="text-xs text-green-700">Payées</p>
          <p className="text-xl font-bold text-green-800">{stats.paid_orders || 0}</p>
        </div>
        <div className="bg-emerald-50 rounded-xl p-3 border border-emerald-200">
          <p className="text-xs text-emerald-700">CA en attente</p>
          <p className="text-lg font-bold text-emerald-800">{formatPrice(stats.pending_payment)}</p>
        </div>
      </div>

      {/* Liste des commandes */}
      <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div className="px-4 py-3 border-b bg-gray-50">
          <h2 className="font-semibold text-gray-800">Mes Commandes</h2>
        </div>
        
        {orders.length === 0 ? (
          <div className="p-8 text-center text-gray-500">
            Aucune commande pour le moment
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                  <th className="px-4 py-3 text-left">Référence</th>
                  <th className="px-4 py-3 text-left">Date</th>
                  <th className="px-4 py-3 text-left">Statut</th>
                  <th className="px-4 py-3 text-right">Total</th>
                  <th className="px-4 py-3 text-center">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y">
                {orders.map(order => (
                  <tr key={order.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 font-medium">{order.reference}</td>
                    <td className="px-4 py-3 text-sm text-gray-600">{formatDate(order.created_at)}</td>
                    <td className="px-4 py-3"><StatusBadge status={order.status} /></td>
                    <td className="px-4 py-3 text-right font-medium">{formatPrice(order.total)}</td>
                    <td className="px-4 py-3">
                      <div className="flex justify-center gap-2">
                        <button
                          onClick={() => { setSelectedOrder(order); setShowDetailModal(true); }}
                          className="p-1.5 text-gray-600 hover:bg-gray-100 rounded"
                          title="Voir détails"
                        >
                          <Eye size={16} />
                        </button>
                        
                        {(order.status === 'pending' || order.status === 'submitted') && (
                          <>
                            <button
                              onClick={() => { setSelectedOrder(order); setShowConfirmModal(true); }}
                              className="p-1.5 text-green-600 hover:bg-green-50 rounded"
                              title="Approuver"
                            >
                              <CheckCircle size={16} />
                            </button>
                            <button
                              onClick={() => handleRejectOrder(order.id)}
                              className="p-1.5 text-red-600 hover:bg-red-50 rounded"
                              title="Rejeter"
                            >
                              <XCircle size={16} />
                            </button>
                          </>
                        )}
                        
                        {order.status === 'confirmed' && (
                          <>
                            <button
                              onClick={() => handleShipOrder(order.id)}
                              className="p-1.5 text-purple-600 hover:bg-purple-50 rounded"
                              title="Préparer"
                            >
                              <Package size={16} />
                            </button>
                            <button
                              onClick={() => handleDeliverOrder(order.id)}
                              className="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded"
                              title="Livrer directement"
                            >
                              <Truck size={16} />
                            </button>
                          </>
                        )}

                        {order.status === 'shipped' && (
                          <button
                            onClick={() => handleDeliverOrder(order.id)}
                            className="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded"
                            title="Livrer"
                          >
                            <Send size={16} />
                          </button>
                        )}

                        {order.status === 'delivered' && (
                          <button
                            onClick={() => handleValidatePayment(order.id)}
                            className="p-1.5 text-green-600 hover:bg-green-50 rounded"
                            title="Valider paiement"
                          >
                            <CreditCard size={16} />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Modal Détails */}
      {showDetailModal && selectedOrder && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[80vh] overflow-y-auto">
            <div className="px-6 py-4 border-b flex justify-between items-center">
              <h2 className="text-xl font-bold">Commande {selectedOrder.reference}</h2>
              <button onClick={() => setShowDetailModal(false)} className="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <div className="p-6">
              <div className="grid grid-cols-2 gap-4 mb-6">
                <div>
                  <p className="text-sm text-gray-500">Statut</p>
                  <StatusBadge status={selectedOrder.status} />
                </div>
                <div>
                  <p className="text-sm text-gray-500">Total</p>
                  <p className="font-bold text-lg">{formatPrice(selectedOrder.total)}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">Date commande</p>
                  <p>{formatDate(selectedOrder.created_at)}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">Livraison prévue</p>
                  <p>{formatDate(selectedOrder.expected_delivery_date)}</p>
                </div>
              </div>
              
              <h3 className="font-semibold mb-3">Articles</h3>
              <table className="w-full text-sm">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-3 py-2 text-left">Produit</th>
                    <th className="px-3 py-2 text-right">Qté</th>
                    <th className="px-3 py-2 text-right">Prix U.</th>
                    <th className="px-3 py-2 text-right">Total</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {selectedOrder.items?.map((item, idx) => (
                    <tr key={idx}>
                      <td className="px-3 py-2">{item.product?.name || `Produit #${item.product_id}`}</td>
                      <td className="px-3 py-2 text-right">{item.quantity}</td>
                      <td className="px-3 py-2 text-right">{formatPrice(item.unit_price)}</td>
                      <td className="px-3 py-2 text-right font-medium">{formatPrice(item.total)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* Modal Confirmation */}
      {showConfirmModal && selectedOrder && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
            <h2 className="text-xl font-bold mb-4">Confirmer la commande</h2>
            <p className="text-gray-600 mb-4">Commande {selectedOrder.reference} - {formatPrice(selectedOrder.total)}</p>
            
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-1">Date de livraison prévue</label>
                <input
                  type="date"
                  value={confirmData.expected_delivery_date}
                  onChange={(e) => setConfirmData({...confirmData, expected_delivery_date: e.target.value})}
                  min={new Date().toISOString().split('T')[0]}
                  className="w-full border rounded-lg px-3 py-2"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Notes (optionnel)</label>
                <textarea
                  value={confirmData.notes}
                  onChange={(e) => setConfirmData({...confirmData, notes: e.target.value})}
                  className="w-full border rounded-lg px-3 py-2"
                  rows={3}
                  placeholder="Informations complémentaires..."
                />
              </div>
            </div>

            <div className="flex gap-3 mt-6">
              <button
                onClick={() => setShowConfirmModal(false)}
                className="flex-1 border border-gray-300 py-2 rounded-lg font-medium"
              >
                Annuler
              </button>
              <button
                onClick={handleConfirmOrder}
                disabled={actionLoading}
                className="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg font-medium"
              >
                {actionLoading ? 'Confirmation...' : 'Confirmer'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
