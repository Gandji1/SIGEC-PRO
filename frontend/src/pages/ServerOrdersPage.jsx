import React, { useState, useEffect, useCallback, memo } from 'react';
import { useTenantStore } from '../stores/tenantStore';
import apiClient from '../services/apiClient';
import { Clock, CheckCircle, CreditCard, RefreshCw, Send, ChevronDown, ChevronUp } from 'lucide-react';

const StatusBadge = memo(({ status, type }) => {
  const configs = {
    preparation: {
      pending: { bg: 'bg-yellow-100', text: 'text-yellow-700', label: 'En attente' },
      approved: { bg: 'bg-blue-100', text: 'text-blue-700', label: 'Approuvée' },
      preparing: { bg: 'bg-orange-100', text: 'text-orange-700', label: 'En préparation' },
      ready: { bg: 'bg-green-100', text: 'text-green-700', label: 'Prête' },
      served: { bg: 'bg-purple-100', text: 'text-purple-700', label: 'Servie' },
    },
    payment: {
      pending: { bg: 'bg-gray-100', text: 'text-gray-700', label: 'Non payée' },
      processing: { bg: 'bg-yellow-100', text: 'text-yellow-700', label: 'Paiement en cours' },
      confirmed: { bg: 'bg-green-100', text: 'text-green-700', label: 'Payée' },
    }
  };
  const config = configs[type]?.[status] || { bg: 'bg-gray-100', text: 'text-gray-700', label: status };
  return <span className={`px-2 py-1 rounded-full text-xs font-medium ${config.bg} ${config.text}`}>{config.label}</span>;
});

// Composant pour afficher les articles avec expansion
const OrderItemsList = memo(({ order }) => {
  const [expanded, setExpanded] = useState(false);
  const items = order.items || [];
  const itemsToShow = expanded ? items : items.slice(0, 2);
  const hasMore = items.length > 2;

  return (
    <div className="px-4 py-2 text-sm text-gray-600">
      {itemsToShow.map((item, i) => (
        <div key={i} className="flex items-center mb-1">
          <span className="inline-flex items-center justify-center min-w-[24px] h-6 bg-blue-600 text-white text-xs font-bold rounded-full mr-2 px-1">
            {item.quantity_ordered || item.quantity || 1}
          </span>
          <span className="truncate">{item.product?.name || item.name}</span>
        </div>
      ))}
      {hasMore && (
        <button 
          onClick={() => setExpanded(!expanded)}
          className="w-full text-center py-1 text-blue-600 hover:text-blue-800 text-xs font-medium flex items-center justify-center gap-1"
        >
          {expanded ? (
            <><ChevronUp size={14} /> Réduire</>
          ) : (
            <><ChevronDown size={14} /> +{items.length - 2} autres</>
          )}
        </button>
      )}
    </div>
  );
});

export default function ServerOrdersPage() {
  const { user } = useTenantStore();
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [showPaymentModal, setShowPaymentModal] = useState(false);
  const [selectedOrder, setSelectedOrder] = useState(null);
  const [paymentData, setPaymentData] = useState({ payment_method: 'cash', amount: 0 });
  const [message, setMessage] = useState({ type: '', text: '' });

  const fetchOrders = useCallback(async () => {
    try {
      const res = await apiClient.get('/pos/orders');
      setOrders(res.data?.data || []);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchOrders();
    const interval = setInterval(fetchOrders, 15000);
    return () => clearInterval(interval);
  }, [fetchOrders]);

  const openPaymentModal = (order) => {
    setSelectedOrder(order);
    setPaymentData({ payment_method: 'cash', amount: order.total });
    setShowPaymentModal(true);
  };

  const handleInitiatePayment = async () => {
    if (!selectedOrder) return;
    setActionLoading(true);
    try {
      await apiClient.post(`/pos/orders/${selectedOrder.id}/payment`, paymentData);
      setMessage({ type: 'success', text: 'Paiement envoyé au gérant pour validation!' });
      setShowPaymentModal(false);
      fetchOrders();
    } catch (err) {
      setMessage({ type: 'error', text: err.response?.data?.message || 'Erreur' });
    } finally {
      setActionLoading(false);
      setTimeout(() => setMessage({ type: '', text: '' }), 3000);
    }
  };

  const formatPrice = (p) => new Intl.NumberFormat('fr-FR').format(p || 0) + ' FCFA';

  return (
    <div className="min-h-screen p-6">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">📋 Mes Commandes</h1>
          <p className="text-gray-600">Suivez vos commandes et initiez les paiements</p>
        </div>
        <button onClick={fetchOrders} disabled={loading} className="p-2 bg-white border rounded-lg hover:bg-gray-50">
          <RefreshCw className={`w-5 h-5 ${loading ? 'animate-spin' : ''}`} />
        </button>
      </div>

      {message.text && (
        <div className={`mb-4 p-3 rounded-lg ${message.type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
          {message.text}
        </div>
      )}

      {loading ? (
        <div className="animate-pulse space-y-4">
          {[1,2,3].map(i => <div key={i} className="h-24 bg-white rounded-lg"></div>)}
        </div>
      ) : orders.length === 0 ? (
        <div className="bg-white rounded-lg p-8 text-center">
          <p className="text-gray-500">Aucune commande</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {orders.map(order => (
            <div key={order.id} className={`bg-white rounded-xl shadow-md border-l-4 overflow-hidden transition-all hover:shadow-lg ${
              order.payment_status === 'confirmed' ? 'border-green-500' : 
              order.payment_status === 'processing' ? 'border-yellow-500' : 'border-blue-500'
            }`}>
              {/* Header */}
              <div className="px-4 py-3 bg-gray-50 border-b">
                <div className="flex justify-between items-center">
                  <span className="font-bold text-gray-800">{order.reference?.split('-').pop()}</span>
                  {order.table_number && (
                    <span className="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs font-medium">
                      Table {order.table_number}
                    </span>
                  )}
                </div>
              </div>

              {/* Statuts */}
              <div className="px-4 py-2 flex gap-2 flex-wrap">
                <StatusBadge status={order.preparation_status} type="preparation" />
                <StatusBadge status={order.payment_status} type="payment" />
              </div>
              
              {/* Articles */}
              <OrderItemsList order={order} formatPrice={formatPrice} />

              {/* Footer avec total et actions */}
              <div className="px-4 py-3 bg-gray-50 border-t">
                <div className="flex justify-between items-center">
                  <span className="font-bold text-lg text-gray-800">{formatPrice(order.total)}</span>
                  
                  {/* Bouton initier paiement */}
                  {order.preparation_status === 'served' && order.payment_status === 'pending' && (
                    <button
                      onClick={() => openPaymentModal(order)}
                      className="flex items-center gap-1 bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium"
                    >
                      <CreditCard className="w-3 h-3" />
                      Payer
                    </button>
                  )}
                  
                  {order.payment_status === 'processing' && (
                    <span className="text-yellow-600 text-xs font-medium flex items-center gap-1">
                      <Clock className="w-3 h-3" /> Validation...
                    </span>
                  )}
                  
                  {order.payment_status === 'confirmed' && (
                    <span className="text-green-600 text-xs font-medium flex items-center gap-1">
                      <CheckCircle className="w-3 h-3" /> Payée
                    </span>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Modal Paiement */}
      {showPaymentModal && selectedOrder && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            <h2 className="text-xl font-bold mb-4">Initier le paiement</h2>
            <p className="text-gray-600 mb-4">Commande {selectedOrder.reference} - {formatPrice(selectedOrder.total)}</p>
            
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-1">Méthode de paiement</label>
                <select
                  value={paymentData.payment_method}
                  onChange={(e) => setPaymentData({...paymentData, payment_method: e.target.value})}
                  className="w-full border rounded-lg px-3 py-2"
                >
                  <option value="cash">Espèces</option>
                  <option value="momo">Mobile Money</option>
                  <option value="card">Carte bancaire</option>
                </select>
              </div>
              
              <div>
                <label className="block text-sm font-medium mb-1">Montant reçu</label>
                <input
                  type="number"
                  value={paymentData.amount}
                  onChange={(e) => setPaymentData({...paymentData, amount: parseFloat(e.target.value)})}
                  className="w-full border rounded-lg px-3 py-2"
                />
              </div>
            </div>

            <div className="flex gap-3 mt-6">
              <button
                onClick={() => setShowPaymentModal(false)}
                className="flex-1 border border-gray-300 py-2 rounded-lg font-medium"
              >
                Annuler
              </button>
              <button
                onClick={handleInitiatePayment}
                disabled={actionLoading}
                className="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg font-medium flex items-center justify-center gap-2"
              >
                <Send className="w-4 h-4" />
                {actionLoading ? 'Envoi...' : 'Envoyer au gérant'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
