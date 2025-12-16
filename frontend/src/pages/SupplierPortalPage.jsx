import React, { useState, useEffect, useCallback, memo } from 'react';
import { useParams } from 'react-router-dom';
import { useTenantStore } from '../stores/tenantStore';
import apiClient from '../services/apiClient';
import { Package, Truck, CheckCircle, XCircle, Clock, RefreshCw, Eye, Send, CreditCard, History, TrendingUp, AlertTriangle, Users, Printer, Download } from 'lucide-react';

// Skeleton optimisé
const OrderSkeleton = memo(() => (
  <div className="animate-pulse space-y-4">
    {[1, 2, 3].map(i => (
      <div key={i} className="bg-white rounded-lg p-4 shadow">
        <div className="h-4 bg-gray-200 rounded w-1/3 mb-2"></div>
        <div className="h-3 bg-gray-100 rounded w-1/2 mb-2"></div>
        <div className="h-3 bg-gray-100 rounded w-1/4"></div>
      </div>
    ))}
  </div>
));

// Badge de statut
const StatusBadge = memo(({ status }) => {
  const configs = {
    pending: { bg: 'bg-yellow-100', text: 'text-yellow-700', label: 'En attente', icon: Clock },
    submitted: { bg: 'bg-orange-100', text: 'text-orange-700', label: 'Soumise', icon: Clock },
    confirmed: { bg: 'bg-blue-100', text: 'text-blue-700', label: 'Approuvée', icon: CheckCircle },
    shipped: { bg: 'bg-purple-100', text: 'text-purple-700', label: 'Préparée', icon: Package },
    delivered: { bg: 'bg-indigo-100', text: 'text-indigo-700', label: 'Servie', icon: Truck },
    received: { bg: 'bg-teal-100', text: 'text-teal-700', label: 'Réceptionnée', icon: CheckCircle },
    paid: { bg: 'bg-green-100', text: 'text-green-700', label: 'Validée', icon: CreditCard },
    cancelled: { bg: 'bg-red-100', text: 'text-red-700', label: 'Annulée', icon: XCircle },
  };
  const config = configs[status] || configs.pending;
  const Icon = config.icon;
  return (
    <span className={`inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium ${config.bg} ${config.text}`}>
      <Icon className="w-3 h-3" />
      {config.label}
    </span>
  );
});

// Carte de commande - MOBILE OPTIMIZED
const OrderCard = memo(({ order, onApprove, onDeliver, onValidatePayment, onReject, loading, formatPrice, formatDate }) => {
  const [expanded, setExpanded] = React.useState(false);
  const itemsToShow = expanded ? order.items : order.items?.slice(0, 2);
  const hasMoreItems = order.items?.length > 2;

  return (
    <div className="bg-white rounded-lg shadow-md border border-gray-100 overflow-hidden hover:shadow-lg transition-shadow">
      {/* Header */}
      <div className="px-2 sm:px-4 py-2 sm:py-3 bg-gray-50 border-b flex justify-between items-center">
        <div className="min-w-0">
          <span className="font-bold text-gray-800 text-xs sm:text-sm">{order.reference}</span>
          <span className="ml-1 sm:ml-2 text-[10px] sm:text-sm text-gray-500 truncate">{order.tenant?.name || ''}</span>
        </div>
        <StatusBadge status={order.status} />
      </div>

      {/* Items */}
      <div className="p-2 sm:p-4">
        <div className="space-y-1.5 sm:space-y-2 mb-2 sm:mb-4">
          {itemsToShow?.map((item, idx) => (
            <div key={idx} className="flex justify-between items-center text-xs sm:text-sm">
              <div className="flex items-center min-w-0">
                <span className="inline-flex items-center justify-center min-w-[20px] sm:min-w-[28px] h-5 sm:h-7 bg-blue-600 text-white text-[10px] sm:text-sm font-bold rounded-full mr-1.5 sm:mr-2 px-1 sm:px-2 flex-shrink-0">
                  {item.quantity_ordered || item.quantity || 1}
                </span>
                <span className="text-gray-700 truncate">{item.product?.name || 'Produit'}</span>
              </div>
              <span className="text-gray-600 font-medium ml-1 sm:ml-2 flex-shrink-0 text-[10px] sm:text-sm">{formatPrice(item.line_total || item.total)}</span>
            </div>
          ))}
          {hasMoreItems && (
            <button 
              onClick={() => setExpanded(!expanded)}
              className="w-full text-center py-0.5 sm:py-1 text-blue-600 hover:text-blue-800 text-[10px] sm:text-sm font-medium flex items-center justify-center gap-1"
            >
              {expanded ? (
                <>▲ Réduire</>
              ) : (
                <>▼ +{order.items.length - 2} articles</>
              )}
            </button>
          )}
        </div>

        {/* Total */}
        <div className="flex justify-between items-center pt-2 sm:pt-3 border-t">
          <div className="text-[10px] sm:text-sm text-gray-500">
            <span>{formatDate(order.created_at)}</span>
          </div>
          <div className="text-sm sm:text-lg font-bold text-gray-800">{formatPrice(order.total)}</div>
        </div>
      </div>

      {/* Actions */}
      <div className="px-2 sm:px-4 py-2 sm:py-3 bg-gray-50 border-t flex gap-1.5 sm:gap-2 flex-wrap">
        {/* Bouton Approuver - MOBILE OPTIMIZED */}
        {(order.status === 'pending' || order.status === 'submitted') && (
          <>
            <button
              onClick={() => onApprove(order)}
              disabled={loading}
              className="flex-1 bg-green-600 hover:bg-green-700 text-white px-2 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition disabled:opacity-50"
            >
              ✓ Approuver
            </button>
            <button
              onClick={() => onReject(order.id)}
              disabled={loading}
              className="bg-red-100 hover:bg-red-200 text-red-700 px-2 sm:px-3 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition disabled:opacity-50"
            >
              ✕
            </button>
          </>
        )}

        {/* Bouton Servir */}
        {order.status === 'confirmed' && (
          <button
            onClick={() => onDeliver(order.id)}
            disabled={loading}
            className="flex-1 bg-purple-600 hover:bg-purple-700 text-white px-2 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition disabled:opacity-50"
          >
            🚚 Servir
          </button>
        )}

        {/* Bouton Livrer */}
        {order.status === 'shipped' && (
          <button
            onClick={() => onDeliver(order.id)}
            disabled={loading}
            className="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white px-2 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition disabled:opacity-50"
          >
            📦 Livrer
          </button>
        )}

        {/* Bouton Valider Paiement */}
        {(order.status === 'delivered' || order.status === 'received') && (
          <button
            onClick={() => onValidatePayment(order.id)}
            disabled={loading}
            className="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white px-2 sm:px-4 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition disabled:opacity-50"
          >
            💰 Valider
          </button>
        )}

        {/* Statut final */}
        {order.status === 'paid' && (
          <div className="flex-1 text-center py-1.5 sm:py-2 text-green-600 font-medium text-xs sm:text-sm">
            ✓ Terminée
          </div>
        )}
      </div>
    </div>
  );
});

export default function SupplierPortalPage() {
  const { id: orderIdFromUrl } = useParams();
  const { user } = useTenantStore();
  const [dashboard, setDashboard] = useState(null);
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [filter, setFilter] = useState('pending'); // pending, confirmed, delivered, paid, all
  const [activeTab, setActiveTab] = useState('orders'); // orders, history
  const [selectedOrder, setSelectedOrder] = useState(null);
  const [showConfirmModal, setShowConfirmModal] = useState(false);
  const [confirmData, setConfirmData] = useState({ expected_delivery_date: '', notes: '' });
  const [message, setMessage] = useState({ type: '', text: '' });
  const [history, setHistory] = useState([]);
  const [historySummary, setHistorySummary] = useState({ total_orders: 0, total_ca: 0 });
  const [expandedClient, setExpandedClient] = useState(null);

  const formatPrice = (p) => new Intl.NumberFormat('fr-FR').format(p || 0) + ' FCFA';
  const formatDate = (d) => d ? new Date(d).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '-';

  // Fetch dashboard et commandes
  const fetchDashboard = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get('/supplier-portal/dashboard');
      const data = res.data?.data || res.data || {};
      setDashboard(data);
      const ordersList = Array.isArray(data?.orders) ? data.orders : [];
      setOrders(ordersList);
      
      // Construire l'historique par client
      const paidOrders = ordersList.filter(o => o.status === 'paid');
      const byClient = {};
      paidOrders.forEach(order => {
        const clientName = order.tenant?.name || 'Client inconnu';
        if (!byClient[clientName]) {
          byClient[clientName] = { client_name: clientName, orders: [], total_ca: 0, total_orders: 0 };
        }
        byClient[clientName].orders.push(order);
        byClient[clientName].total_ca += parseFloat(order.total) || 0;
        byClient[clientName].total_orders++;
      });
      setHistory(Object.values(byClient));
      setHistorySummary({
        total_orders: paidOrders.length,
        total_ca: paidOrders.reduce((sum, o) => sum + (parseFloat(o.total) || 0), 0)
      });
      
      setMessage({ type: '', text: '' });
    } catch (err) {
      console.error('Erreur portail fournisseur:', err);
      if (err.response?.status === 404) {
        setDashboard({
          supplier: { name: user?.name, email: user?.email },
          orders: [],
          stats: { total_orders: 0, pending_orders: 0, confirmed_orders: 0, delivered_orders: 0, paid_orders: 0 },
          message: 'Votre compte fournisseur sera lié automatiquement lors de la prochaine commande.'
        });
        setOrders([]);
      } else {
        setMessage({ type: 'error', text: err.response?.data?.message || 'Erreur de chargement des données' });
      }
    } finally {
      setLoading(false);
    }
  }, [user]);

  useEffect(() => {
    fetchDashboard();
    const interval = setInterval(fetchDashboard, 30000);
    return () => clearInterval(interval);
  }, [fetchDashboard]);

  // Filtrer les commandes selon le filtre actif
  const filteredOrders = orders.filter(order => {
    if (filter === 'all') return true;
    if (filter === 'pending') return ['pending', 'submitted'].includes(order.status);
    if (filter === 'confirmed') return order.status === 'confirmed';
    if (filter === 'delivered') return ['shipped', 'delivered', 'received'].includes(order.status);
    if (filter === 'paid') return order.status === 'paid';
    return true;
  });

  // Actions
  const handleConfirmOrder = async () => {
    if (!selectedOrder) return;
    setActionLoading(true);
    try {
      await apiClient.post(`/supplier-portal/orders/${selectedOrder.id}/confirm`, confirmData);
      setMessage({ type: 'success', text: 'Commande approuvée!' });
      setShowConfirmModal(false);
      fetchDashboard();
    } catch (err) {
      setMessage({ type: 'error', text: err.response?.data?.message || 'Erreur' });
    } finally {
      setActionLoading(false);
      setTimeout(() => setMessage({ type: '', text: '' }), 3000);
    }
  };

  const handleDeliverOrder = async (orderId) => {
    if (!window.confirm('Confirmer la livraison de cette commande?')) return;
    setActionLoading(true);
    try {
      await apiClient.post(`/supplier-portal/orders/${orderId}/deliver`);
      setMessage({ type: 'success', text: 'Commande servie!' });
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

  const stats = dashboard?.stats || {};
  const counts = {
    pending: orders.filter(o => ['pending', 'submitted'].includes(o.status)).length,
    confirmed: orders.filter(o => o.status === 'confirmed').length,
    delivered: orders.filter(o => ['shipped', 'delivered', 'received'].includes(o.status)).length,
    paid: orders.filter(o => o.status === 'paid').length,
  };

  return (
    <div className="min-h-screen p-3 sm:p-6">
      {/* Header - MOBILE OPTIMIZED */}
      <div className="mb-4 sm:mb-6 flex justify-between items-center gap-2">
        <div className="min-w-0">
          <h1 className="text-lg sm:text-2xl md:text-3xl font-bold text-gray-800 flex items-center gap-2">
            <Package size={20} className="text-blue-600 flex-shrink-0" />
            <span className="truncate">Portail Fournisseur</span>
          </h1>
          <p className="text-xs sm:text-sm text-gray-600 truncate">{dashboard?.supplier?.name || user?.name}</p>
        </div>
        <div className="flex gap-2 items-center flex-shrink-0">
          <div className="hidden sm:flex gap-2">
            <span className="bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full text-xs font-medium">
              {counts.pending} attente
            </span>
            <span className="bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full text-xs font-medium">
              {counts.delivered} valider
            </span>
          </div>
          <button 
            onClick={fetchDashboard} 
            disabled={loading}
            className="p-1.5 sm:p-2 bg-white border rounded-lg hover:bg-gray-50 transition"
          >
            <RefreshCw className={`w-4 h-4 sm:w-5 sm:h-5 ${loading ? 'animate-spin' : ''}`} />
          </button>
        </div>
      </div>

      {/* Alerts */}
      {message.text && (
        <div className={`mb-4 sm:mb-6 p-2 sm:p-4 rounded-lg text-sm ${message.type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'}`}>
          {message.text}
        </div>
      )}

      {/* Onglets principaux - MOBILE OPTIMIZED */}
      <div className="mb-4 sm:mb-6 flex gap-1 sm:gap-4 border-b overflow-x-auto">
        <button
          onClick={() => setActiveTab('orders')}
          className={`px-2 sm:px-4 py-2 sm:py-3 font-medium transition border-b-2 text-xs sm:text-sm whitespace-nowrap ${
            activeTab === 'orders' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'
          }`}
        >
          <Package className="w-3 h-3 sm:w-4 sm:h-4 inline mr-1" />
          Commandes
        </button>
        <button
          onClick={() => setActiveTab('history')}
          className={`px-2 sm:px-4 py-2 sm:py-3 font-medium transition border-b-2 text-xs sm:text-sm whitespace-nowrap ${
            activeTab === 'history' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700'
          }`}
        >
          <History className="w-3 h-3 sm:w-4 sm:h-4 inline mr-1" />
          Historique
        </button>
      </div>

      {/* Filtres - MOBILE OPTIMIZED */}
      {activeTab === 'orders' && (
        <div className="mb-4 sm:mb-6 flex gap-1.5 sm:gap-2 flex-wrap">
          <button
            onClick={() => setFilter('pending')}
            className={`px-2 sm:px-4 py-1.5 sm:py-2 rounded-lg font-medium transition text-xs sm:text-sm ${
              filter === 'pending' ? 'bg-yellow-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'
            }`}
          >
            Attente ({counts.pending})
          </button>
          <button
            onClick={() => setFilter('confirmed')}
            className={`px-2 sm:px-4 py-1.5 sm:py-2 rounded-lg font-medium transition text-xs sm:text-sm ${
              filter === 'confirmed' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'
            }`}
          >
            Approuv. ({counts.confirmed})
          </button>
          <button
            onClick={() => setFilter('delivered')}
            className={`px-2 sm:px-4 py-1.5 sm:py-2 rounded-lg font-medium transition text-xs sm:text-sm ${
              filter === 'delivered' ? 'bg-purple-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'
            }`}
          >
            Servies ({counts.delivered})
          </button>
          <button
            onClick={() => setFilter('paid')}
            className={`px-2 sm:px-4 py-1.5 sm:py-2 rounded-lg font-medium transition text-xs sm:text-sm ${
              filter === 'paid' ? 'bg-green-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'
            }`}
          >
            Valid. ({counts.paid})
          </button>
          <button
            onClick={() => setFilter('all')}
            className={`px-2 sm:px-4 py-1.5 sm:py-2 rounded-lg font-medium transition text-xs sm:text-sm ${
              filter === 'all' ? 'bg-gray-800 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'
            }`}
          >
            Toutes ({orders.length})
          </button>
        </div>
      )}

      {/* Contenu selon l'onglet actif */}
      {activeTab === 'orders' ? (
        <>
          {/* Liste des commandes */}
          {loading ? (
            <OrderSkeleton />
          ) : filteredOrders.length === 0 ? (
            <div className="bg-white rounded-lg shadow p-8 text-center">
              <div className="text-6xl mb-4">📦</div>
              <h3 className="text-xl font-semibold text-gray-700 mb-2">Aucune commande</h3>
              <p className="text-gray-500">Aucune commande dans cette catégorie.</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {filteredOrders.map(order => (
                <OrderCard
                  key={order.id}
                  order={order}
                  onApprove={(o) => { setSelectedOrder(o); setShowConfirmModal(true); }}
                  onDeliver={handleDeliverOrder}
                  onValidatePayment={handleValidatePayment}
                  onReject={handleRejectOrder}
                  loading={actionLoading}
                  formatPrice={formatPrice}
                  formatDate={formatDate}
                />
              ))}
            </div>
          )}

          {/* Info workflow */}
          <div className="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h4 className="font-semibold text-blue-800 mb-2">📋 Workflow des commandes</h4>
            <div className="flex flex-wrap gap-4 text-sm text-blue-700">
              <span>1. Gérant crée la commande</span>
              <span>→</span>
              <span>2. Fournisseur approuve</span>
              <span>→</span>
              <span>3. Fournisseur sert/livre</span>
              <span>→</span>
              <span>4. Fournisseur valide paiement</span>
            </div>
          </div>
        </>
      ) : (
        <>
          {/* Historique par client */}
          {loading ? (
            <OrderSkeleton />
          ) : (
            <>
              {/* Résumé global */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div className="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                  <div className="flex items-center gap-3">
                    <Users className="w-8 h-8 text-blue-500" />
                    <div>
                      <p className="text-sm text-gray-500">Clients</p>
                      <p className="text-2xl font-bold">{history.length}</p>
                    </div>
                  </div>
                </div>
                <div className="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                  <div className="flex items-center gap-3">
                    <CheckCircle className="w-8 h-8 text-green-500" />
                    <div>
                      <p className="text-sm text-gray-500">Commandes validées</p>
                      <p className="text-2xl font-bold">{historySummary.total_orders}</p>
                    </div>
                  </div>
                </div>
                <div className="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                  <div className="flex items-center gap-3">
                    <TrendingUp className="w-8 h-8 text-purple-500" />
                    <div>
                      <p className="text-sm text-gray-500">Chiffre d'affaires total</p>
                      <p className="text-2xl font-bold">{formatPrice(historySummary.total_ca)}</p>
                    </div>
                  </div>
                </div>
              </div>

              {/* Liste par client */}
              {history.length === 0 ? (
                <div className="bg-white rounded-lg shadow p-8 text-center">
                  <div className="text-6xl mb-4">📊</div>
                  <h3 className="text-xl font-semibold text-gray-700 mb-2">Aucun historique</h3>
                  <p className="text-gray-500">Aucune commande validée pour le moment.</p>
                </div>
              ) : (
                <div className="space-y-4">
                  {history.map((clientData, idx) => (
                    <div key={idx} className="bg-white rounded-lg shadow overflow-hidden">
                      {/* Header client */}
                      <div 
                        className="p-4 bg-gray-50 border-b cursor-pointer hover:bg-gray-100 transition"
                        onClick={() => setExpandedClient(expandedClient === idx ? null : idx)}
                      >
                        <div className="flex justify-between items-center">
                          <div className="flex items-center gap-3">
                            <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                              <Users className="w-5 h-5 text-blue-600" />
                            </div>
                            <div>
                              <h3 className="font-bold text-gray-800">{clientData.client_name}</h3>
                              <p className="text-sm text-gray-500">{clientData.total_orders} commandes</p>
                            </div>
                          </div>
                          <div className="text-right">
                            <p className="text-lg font-bold text-green-600">{formatPrice(clientData.total_ca)}</p>
                            <p className="text-xs text-gray-500">
                              {expandedClient === idx ? '▲ Masquer' : '▼ Voir détails'}
                            </p>
                          </div>
                        </div>
                      </div>

                      {/* Détails des commandes */}
                      {expandedClient === idx && (
                        <div className="p-4">
                          <table className="w-full text-sm">
                            <thead>
                              <tr className="text-left text-gray-500 border-b">
                                <th className="pb-2">Référence</th>
                                <th className="pb-2">Produits</th>
                                <th className="pb-2">Montant</th>
                                <th className="pb-2">Date</th>
                              </tr>
                            </thead>
                            <tbody>
                              {clientData.orders.map((order) => (
                                <tr key={order.id} className="border-b last:border-0 hover:bg-gray-50">
                                  <td className="py-2 font-mono text-xs">{order.reference}</td>
                                  <td className="py-2 max-w-[200px]">
                                    <div className="text-xs text-gray-700">
                                      {order.items?.length > 0 ? (
                                        <div className="flex flex-col gap-0.5">
                                          {order.items.slice(0, 3).map((item, i) => (
                                            <div key={i} className="flex items-center gap-1">
                                              <span className="font-semibold text-orange-600">{item.quantity_ordered || item.quantity}</span>
                                              <span>×</span>
                                              <span>{item.product?.name}</span>
                                            </div>
                                          ))}
                                          {order.items.length > 3 && (
                                            <div className="text-orange-500">+{order.items.length - 3} autre(s)</div>
                                          )}
                                        </div>
                                      ) : (
                                        <span className="text-gray-400">{order.items?.length || 0} article(s)</span>
                                      )}
                                    </div>
                                  </td>
                                  <td className="py-2 font-medium">{formatPrice(order.total)}</td>
                                  <td className="py-2 text-xs text-gray-500">{formatDate(order.created_at)}</td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </>
          )}
        </>
      )}

      {/* Modal Confirmation */}
      {showConfirmModal && selectedOrder && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
            <h2 className="text-xl font-bold mb-4">Approuver la commande</h2>
            <p className="text-gray-600 mb-4">Commande {selectedOrder.reference} - {formatPrice(selectedOrder.total)}</p>
            
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-1">Date de livraison prévue</label>
                <input
                  type="date"
                  value={confirmData.expected_delivery_date}
                  onChange={(e) => setConfirmData({...confirmData, expected_delivery_date: e.target.value})}
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
                {actionLoading ? 'Approbation...' : 'Approuver'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
