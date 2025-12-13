import React, { useState, useEffect, useCallback, memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTenantStore } from '../stores/tenantStore';
import { useLanguageStore } from '../stores/languageStore';
import { usePermission } from '../hooks/usePermission';
import { usePrint } from '../hooks/usePrint';
import { usePosOrdersRealtime } from '../hooks/useWebSocket';
import apiClient from '../services/apiClient';
import { CheckCircle, Clock, AlertCircle, CreditCard, ChefHat, Utensils, RefreshCw, History, Users, TrendingUp, Printer, Download, FileText } from 'lucide-react';

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
const StatusBadge = memo(({ status, type = 'preparation' }) => {
  const configs = {
    preparation: {
      pending: { bg: 'bg-yellow-100', text: 'text-yellow-700', label: 'En attente', icon: Clock },
      approved: { bg: 'bg-blue-100', text: 'text-blue-700', label: 'Approuvée', icon: CheckCircle },
      preparing: { bg: 'bg-orange-100', text: 'text-orange-700', label: 'En préparation', icon: ChefHat },
      ready: { bg: 'bg-green-100', text: 'text-green-700', label: 'Prête', icon: Utensils },
      served: { bg: 'bg-purple-100', text: 'text-purple-700', label: 'Servie', icon: CheckCircle },
    },
    payment: {
      pending: { bg: 'bg-gray-100', text: 'text-gray-700', label: 'Non payée', icon: Clock },
      processing: { bg: 'bg-yellow-100', text: 'text-yellow-700', label: 'Paiement en attente', icon: CreditCard },
      confirmed: { bg: 'bg-green-100', text: 'text-green-700', label: 'Payée', icon: CheckCircle },
      failed: { bg: 'bg-red-100', text: 'text-red-700', label: 'Échec', icon: AlertCircle },
    }
  };

  const config = configs[type]?.[status] || configs.preparation.pending;
  const Icon = config.icon;

  return (
    <span className={`inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium ${config.bg} ${config.text}`}>
      <Icon className="w-3 h-3" />
      {config.label}
    </span>
  );
});

// Carte de commande
const OrderCard = memo(({ order, onApprove, onServe, onValidatePayment, onPrint, loading }) => {
  const [expanded, setExpanded] = React.useState(false);
  const formatPrice = (price) => new Intl.NumberFormat('fr-FR').format(price || 0) + ' FCFA';
  const formatDate = (date) => new Date(date).toLocaleString('fr-FR', { 
    day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' 
  });

  const itemsToShow = expanded ? order.items : order.items?.slice(0, 3);
  const hasMoreItems = order.items?.length > 3;

  return (
    <div className="bg-white rounded-lg shadow-md border border-gray-100 overflow-hidden hover:shadow-lg transition-shadow">
      {/* Header */}
      <div className="px-4 py-3 bg-gray-50 border-b flex justify-between items-center">
        <div>
          <span className="font-bold text-gray-800">{order.reference}</span>
          {order.table_number && (
            <span className="ml-2 text-sm text-gray-500">Table {order.table_number}</span>
          )}
        </div>
        <div className="flex gap-2">
          <StatusBadge status={order.preparation_status} type="preparation" />
          <StatusBadge status={order.payment_status} type="payment" />
        </div>
      </div>

      {/* Items */}
      <div className="p-4">
        <div className="space-y-2 mb-4">
          {itemsToShow?.map((item, idx) => (
            <div key={idx} className="flex justify-between items-center text-sm">
              <div className="flex items-center">
                <span className="inline-flex items-center justify-center min-w-[28px] h-7 bg-blue-600 text-white text-sm font-bold rounded-full mr-2 px-2">
                  {item.quantity_ordered || item.quantity || 1}
                </span>
                <span className="text-gray-700">{item.product?.name || 'Produit'}</span>
              </div>
              <span className="text-gray-600 font-medium ml-2">{formatPrice(item.line_total)}</span>
            </div>
          ))}
          {hasMoreItems && (
            <button 
              onClick={() => setExpanded(!expanded)}
              className="w-full text-center py-1 text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center justify-center gap-1"
            >
              {expanded ? (
                <>▲ Réduire</>
              ) : (
                <>▼ Voir {order.items.length - 3} autres articles</>
              )}
            </button>
          )}
        </div>

        {/* Total */}
        <div className="flex justify-between items-center pt-3 border-t">
          <div className="text-sm text-gray-500">
            <span>Par: {order.created_by_user?.name || 'Serveur'}</span>
            <span className="ml-2">• {formatDate(order.created_at)}</span>
          </div>
          <div className="text-lg font-bold text-gray-800">{formatPrice(order.total)}</div>
        </div>
      </div>

      {/* Actions */}
      <div className="px-4 py-3 bg-gray-50 border-t flex gap-2 flex-wrap">
        {/* Bouton Approuver */}
        {order.preparation_status === 'pending' && (
          <button
            onClick={() => onApprove(order.id)}
            disabled={loading}
            className="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition disabled:opacity-50"
          >
            ✓ Approuver
          </button>
        )}

        {/* Bouton Servir (déduction stock) - après approbation */}
        {['approved', 'preparing', 'ready'].includes(order.preparation_status) && (
          <button
            onClick={() => onServe(order.id)}
            disabled={loading}
            className="flex-1 bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition disabled:opacity-50"
          >
            🍽️ Servir (Stock -)
          </button>
        )}

        {/* Bouton Valider Paiement - après service, le gérant peut valider directement */}
        {order.preparation_status === 'served' && order.payment_status !== 'confirmed' && (
          <button
            onClick={() => onValidatePayment(order.id)}
            disabled={loading}
            className="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition disabled:opacity-50"
          >
            💰 Valider Paiement
          </button>
        )}

        {/* Statut final + Impression */}
        {order.payment_status === 'confirmed' && (
          <>
            <div className="flex-1 text-center py-2 text-green-600 font-medium">
              ✓ Commande payée
            </div>
            <button
              onClick={() => onPrint(order)}
              className="flex items-center gap-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition"
            >
              <Printer size={14} />
              Imprimer
            </button>
          </>
        )}

        {/* Bouton impression pour toutes les commandes */}
        {order.payment_status !== 'confirmed' && (
          <button
            onClick={() => onPrint(order)}
            className="flex items-center gap-1 bg-gray-500 hover:bg-gray-600 text-white px-3 py-2 rounded-lg text-sm font-medium transition"
          >
            <Printer size={14} />
          </button>
        )}
      </div>
    </div>
  );
});

export default function ManagerOrdersPage() {
  const navigate = useNavigate();
  const { user } = useTenantStore();
  const { t } = useLanguageStore();
  const { can } = usePermission();
  const { printReceipt } = usePrint();
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [filter, setFilter] = useState('pending'); // pending, all, payment
  const [activeTab, setActiveTab] = useState('orders'); // orders, history
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [counts, setCounts] = useState({ pending_approval: 0, pending_payment: 0 });
  
  // Historique par serveur
  const [history, setHistory] = useState([]);
  const [historySummary, setHistorySummary] = useState({ total_orders: 0, total_ca: 0, servers_count: 0 });
  const [historyLoading, setHistoryLoading] = useState(false);
  const [expandedServer, setExpandedServer] = useState(null);
  
  // Temps réel - rafraîchir automatiquement quand une commande change
  usePosOrdersRealtime(useCallback(() => {
    fetchOrders();
  }, []));

  // Fetch orders
  const fetchOrders = useCallback(async () => {
    setLoading(true);
    try {
      let endpoint = '/pos/orders';
      if (filter === 'pending') {
        endpoint = '/pos/orders/pending/manager';
      } else if (filter === 'payment') {
        endpoint = '/pos/orders?payment_status=processing';
      }
      
      const res = await apiClient.get(endpoint);
      setOrders(res.data?.data || []);
      if (res.data?.counts) {
        setCounts(res.data.counts);
      }
      setError('');
    } catch (err) {
      console.error('[ManagerOrders] Error:', err);
      setError(err.response?.data?.message || 'Erreur de chargement');
      setOrders([]);
    } finally {
      setLoading(false);
    }
  }, [filter]);

  // Fetch historique par serveur
  const fetchHistory = useCallback(async () => {
    setHistoryLoading(true);
    try {
      const res = await apiClient.get('/pos/orders/history/by-server');
      setHistory(res.data?.data || []);
      setHistorySummary(res.data?.summary || { total_orders: 0, total_ca: 0, servers_count: 0 });
    } catch (err) {
      console.error('[History] Error:', err);
    } finally {
      setHistoryLoading(false);
    }
  }, []);

  useEffect(() => {
    if (activeTab === 'orders') {
      fetchOrders();
      const interval = setInterval(fetchOrders, 30000);
      return () => clearInterval(interval);
    } else {
      fetchHistory();
    }
  }, [fetchOrders, fetchHistory, activeTab]);

  // Impression de l'historique
  const printHistory = () => {
    const printContent = `
      <html>
        <head>
          <title>Historique des ventes par serveur</title>
          <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            h1 { text-align: center; margin-bottom: 10px; }
            .summary { display: flex; justify-content: space-around; margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 8px; }
            .summary-item { text-align: center; }
            .summary-item .value { font-size: 24px; font-weight: bold; }
            .summary-item .label { font-size: 12px; color: #666; }
            .server-section { margin-bottom: 20px; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
            .server-header { background: #f0f0f0; padding: 10px 15px; font-weight: bold; display: flex; justify-content: space-between; }
            table { width: 100%; border-collapse: collapse; font-size: 12px; }
            th, td { padding: 8px; text-align: left; border-bottom: 1px solid #eee; }
            th { background: #fafafa; font-weight: 600; }
            .total { font-weight: bold; color: #22c55e; }
            .date { font-size: 11px; color: #888; text-align: center; margin-bottom: 20px; }
          </style>
        </head>
        <body>
          <h1>📊 Historique des Ventes par Serveur</h1>
          <p class="date">Imprimé le ${new Date().toLocaleString('fr-FR')}</p>
          <div class="summary">
            <div class="summary-item">
              <div class="value">${historySummary.servers_count}</div>
              <div class="label">Serveurs</div>
            </div>
            <div class="summary-item">
              <div class="value">${historySummary.total_orders}</div>
              <div class="label">Commandes</div>
            </div>
            <div class="summary-item">
              <div class="value">${new Intl.NumberFormat('fr-FR').format(historySummary.total_ca)} FCFA</div>
              <div class="label">Chiffre d'affaires</div>
            </div>
          </div>
          ${history.map(serverData => `
            <div class="server-section">
              <div class="server-header">
                <span>${serverData.server_name} (${serverData.total_orders} commandes)</span>
                <span class="total">${new Intl.NumberFormat('fr-FR').format(serverData.total_ca)} FCFA</span>
              </div>
              <table>
                <thead>
                  <tr>
                    <th>Référence</th>
                    <th>Produits</th>
                    <th>Montant</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  ${serverData.orders.map(order => `
                    <tr>
                      <td>${order.reference}</td>
                      <td>${order.items?.map(i => i.product_name + ' ×' + i.quantity).join(', ') || order.items_count + ' articles'}</td>
                      <td>${new Intl.NumberFormat('fr-FR').format(order.total)} FCFA</td>
                      <td>${order.validated_at ? new Date(order.validated_at).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '-'}</td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          `).join('')}
        </body>
      </html>
    `;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.print();
  };

  // Export CSV
  const exportHistoryCSV = () => {
    let csv = 'Serveur;Référence;Produits;Quantités;Montant;Date Validation\n';
    history.forEach(serverData => {
      serverData.orders.forEach(order => {
        const products = order.items?.map(i => i.product_name).join(', ') || '';
        const quantities = order.items?.map(i => i.quantity).join(', ') || '';
        const date = order.validated_at ? new Date(order.validated_at).toLocaleString('fr-FR') : '';
        csv += `${serverData.server_name};${order.reference};${products};${quantities};${order.total};${date}\n`;
      });
    });
    
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `historique_ventes_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
  };

  // Actions
  const handleApprove = async (orderId) => {
    setActionLoading(true);
    try {
      await apiClient.post(`/pos/orders/${orderId}/approve`);
      setSuccess('Commande approuvée!');
      fetchOrders();
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de l\'approbation');
    } finally {
      setActionLoading(false);
      setTimeout(() => { setSuccess(''); setError(''); }, 3000);
    }
  };

  const handleServe = async (orderId) => {
    if (!window.confirm('Servir cette commande? Le stock sera déduit automatiquement.')) return;
    
    setActionLoading(true);
    try {
      await apiClient.post(`/pos/orders/${orderId}/serve`);
      setSuccess('Commande servie - Stock déduit!');
      fetchOrders();
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors du service');
    } finally {
      setActionLoading(false);
      setTimeout(() => { setSuccess(''); setError(''); }, 3000);
    }
  };

  const handleValidatePayment = async (orderId) => {
    if (!window.confirm('Valider ce paiement? Un mouvement de caisse sera créé.')) return;
    
    setActionLoading(true);
    try {
      await apiClient.post(`/pos/orders/${orderId}/validate-payment`);
      setSuccess('Paiement validé - Mouvement de caisse créé!');
      fetchOrders();
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la validation');
    } finally {
      setActionLoading(false);
      setTimeout(() => { setSuccess(''); setError(''); }, 3000);
    }
  };

  // Impression d'une commande
  const handlePrintOrder = (order) => {
    printReceipt(order, { 
      showTax: true, 
      title: order.payment_status === 'confirmed' ? 'FACTURE' : 'BON DE COMMANDE' 
    });
  };

  // Vérification des permissions
  const isManager = user?.role && ['gerant', 'manager', 'owner', 'admin'].includes(user.role);
  
  if (!isManager) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-red-600 mb-2">Accès Réservé aux Gérants</h1>
          <p className="text-gray-600 mb-6">Cette page est réservée aux gérants et managers.</p>
          <button onClick={() => navigate('/dashboard')} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            Retour au dashboard
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen p-6">
      {/* Header */}
      <div className="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-bold text-gray-800">🍽️ Gestion des Commandes POS</h1>
          <p className="text-gray-600">Approuvez, servez et validez les paiements des commandes</p>
        </div>
        <div className="flex gap-3 items-center">
          {/* Compteurs */}
          <div className="flex gap-2">
            <span className="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-sm font-medium">
              {counts.pending_approval} à approuver
            </span>
            <span className="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-medium">
              {counts.pending_payment} paiements
            </span>
          </div>
          <button 
            onClick={fetchOrders} 
            disabled={loading}
            className="p-2 bg-white border rounded-lg hover:bg-gray-50 transition"
          >
            <RefreshCw className={`w-5 h-5 ${loading ? 'animate-spin' : ''}`} />
          </button>
        </div>
      </div>

      {/* Alerts */}
      {error && <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">{error}</div>}
      {success && <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">{success}</div>}

      {/* Onglets principaux */}
      <div className="mb-6 flex gap-4 border-b">
        <button
          onClick={() => setActiveTab('orders')}
          className={`px-4 py-3 font-medium transition border-b-2 ${
            activeTab === 'orders' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'
          }`}
        >
          <Utensils className="w-4 h-4 inline mr-2" />
          Commandes en cours
        </button>
        <button
          onClick={() => setActiveTab('history')}
          className={`px-4 py-3 font-medium transition border-b-2 ${
            activeTab === 'history' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700'
          }`}
        >
          <History className="w-4 h-4 inline mr-2" />
          Historique par serveur
        </button>
      </div>

      {/* Filtres (seulement pour les commandes) */}
      {activeTab === 'orders' && (
        <div className="mb-6 flex gap-2">
          <button
            onClick={() => setFilter('pending')}
            className={`px-4 py-2 rounded-lg font-medium transition ${
              filter === 'pending' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'
            }`}
          >
            En attente
          </button>
          <button
            onClick={() => setFilter('payment')}
            className={`px-4 py-2 rounded-lg font-medium transition ${
              filter === 'payment' ? 'bg-green-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'
            }`}
          >
            Paiements à valider
          </button>
          <button
            onClick={() => setFilter('all')}
            className={`px-4 py-2 rounded-lg font-medium transition ${
              filter === 'all' ? 'bg-gray-800 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'
            }`}
          >
            Toutes
          </button>
        </div>
      )}

      {/* Contenu selon l'onglet actif */}
      {activeTab === 'orders' ? (
        <>
          {/* Liste des commandes */}
          {loading ? (
            <OrderSkeleton />
          ) : orders.length === 0 ? (
            <div className="bg-white rounded-lg shadow p-8 text-center">
              <div className="text-6xl mb-4">🎉</div>
              <h3 className="text-xl font-semibold text-gray-700 mb-2">Aucune commande en attente</h3>
              <p className="text-gray-500">Toutes les commandes ont été traitées.</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {orders.map(order => (
                <OrderCard
                  key={order.id}
                  order={order}
                  onApprove={handleApprove}
                  onServe={handleServe}
                  onValidatePayment={handleValidatePayment}
                  onPrint={handlePrintOrder}
                  loading={actionLoading}
                />
              ))}
            </div>
          )}

          {/* Info workflow */}
          <div className="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h4 className="font-semibold text-blue-800 mb-2">📋 Workflow des commandes</h4>
            <div className="flex flex-wrap gap-4 text-sm text-blue-700">
              <span>1. Serveur crée la commande</span>
              <span>→</span>
              <span>2. Gérant approuve</span>
              <span>→</span>
              <span>3. Gérant sert (stock déduit)</span>
              <span>→</span>
              <span>4. Gérant valide paiement (caisse créditée)</span>
            </div>
          </div>
        </>
      ) : (
        <>
          {/* Historique par serveur */}
          {historyLoading ? (
            <OrderSkeleton />
          ) : (
            <>
              {/* Boutons d'action */}
              <div className="flex justify-end gap-2 mb-4">
                <button
                  onClick={printHistory}
                  disabled={history.length === 0}
                  className="flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition"
                >
                  <Printer size={18} /> Imprimer
                </button>
                <button
                  onClick={exportHistoryCSV}
                  disabled={history.length === 0}
                  className="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition"
                >
                  <Download size={18} /> Exporter CSV
                </button>
              </div>

              {/* Résumé global */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div className="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                  <div className="flex items-center gap-3">
                    <Users className="w-8 h-8 text-blue-500" />
                    <div>
                      <p className="text-sm text-gray-500">Serveurs actifs</p>
                      <p className="text-2xl font-bold">{historySummary.servers_count}</p>
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
                      <p className="text-2xl font-bold">{new Intl.NumberFormat('fr-FR').format(historySummary.total_ca)} FCFA</p>
                    </div>
                  </div>
                </div>
              </div>

              {/* Liste par serveur */}
              {history.length === 0 ? (
                <div className="bg-white rounded-lg shadow p-8 text-center">
                  <div className="text-6xl mb-4">📊</div>
                  <h3 className="text-xl font-semibold text-gray-700 mb-2">Aucun historique</h3>
                  <p className="text-gray-500">Aucune commande validée ce mois-ci.</p>
                </div>
              ) : (
                <div className="space-y-4">
                  {history.map((serverData) => (
                    <div key={serverData.server_id} className="bg-white rounded-lg shadow overflow-hidden">
                      {/* Header serveur */}
                      <div 
                        className="p-4 bg-gray-50 border-b cursor-pointer hover:bg-gray-100 transition"
                        onClick={() => setExpandedServer(expandedServer === serverData.server_id ? null : serverData.server_id)}
                      >
                        <div className="flex justify-between items-center">
                          <div className="flex items-center gap-3">
                            <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                              <Users className="w-5 h-5 text-blue-600" />
                            </div>
                            <div>
                              <h3 className="font-bold text-gray-800">{serverData.server_name}</h3>
                              <p className="text-sm text-gray-500">{serverData.total_orders} commandes</p>
                            </div>
                          </div>
                          <div className="text-right">
                            <p className="text-lg font-bold text-green-600">
                              {new Intl.NumberFormat('fr-FR').format(serverData.total_ca)} FCFA
                            </p>
                            <p className="text-xs text-gray-500">
                              {expandedServer === serverData.server_id ? '▲ Masquer' : '▼ Voir détails'}
                            </p>
                          </div>
                        </div>
                      </div>

                      {/* Détails des commandes */}
                      {expandedServer === serverData.server_id && (
                        <div className="p-4">
                          <table className="w-full text-sm">
                            <thead>
                              <tr className="text-left text-gray-500 border-b">
                                <th className="pb-2">Référence</th>
                                <th className="pb-2 text-center">Qté</th>
                                <th className="pb-2">Produits</th>
                                <th className="pb-2">Montant</th>
                                <th className="pb-2">Approuvée</th>
                                <th className="pb-2">Servie</th>
                                <th className="pb-2">Validée</th>
                              </tr>
                            </thead>
                            <tbody>
                              {serverData.orders.map((order) => (
                                <tr key={order.id} className="border-b last:border-0 hover:bg-gray-50">
                                  <td className="py-2 font-mono text-xs">{order.reference}</td>
                                  <td className="py-2 text-center">
                                    <div className="text-xs text-gray-700">
                                      {order.items?.length > 0 ? (
                                        <div className="flex flex-col gap-0.5">
                                          {order.items.slice(0, 3).map((item, idx) => (
                                            <div key={idx} className="font-semibold text-orange-600">{item.quantity}</div>
                                          ))}
                                          {order.items.length > 3 && <div className="text-gray-400">...</div>}
                                        </div>
                                      ) : '-'}
                                    </div>
                                  </td>
                                  <td className="py-2 max-w-[200px]">
                                    <div className="text-xs text-gray-700">
                                      {order.items?.length > 0 ? (
                                        <div className="flex flex-col gap-0.5">
                                          {order.items.slice(0, 3).map((item, idx) => (
                                            <div key={idx} className="font-medium">{item.product_name || item.product?.name}</div>
                                          ))}
                                          {order.items.length > 3 && (
                                            <div className="text-orange-500">+{order.items.length - 3} autre(s)</div>
                                          )}
                                        </div>
                                      ) : (
                                        <span className="text-gray-400">{order.items_count} article(s)</span>
                                      )}
                                    </div>
                                  </td>
                                  <td className="py-2 font-medium">{new Intl.NumberFormat('fr-FR').format(order.total)} FCFA</td>
                                  <td className="py-2 text-xs text-gray-500">
                                    {order.approved_at ? new Date(order.approved_at).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '-'}
                                  </td>
                                  <td className="py-2 text-xs text-gray-500">
                                    {order.served_at ? new Date(order.served_at).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '-'}
                                  </td>
                                  <td className="py-2 text-xs text-gray-500">
                                    {order.validated_at ? new Date(order.validated_at).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '-'}
                                  </td>
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
    </div>
  );
}
