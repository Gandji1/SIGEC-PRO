import { useState, useEffect, memo } from 'react';
import { useNavigate } from 'react-router-dom';
import apiClient from '../services/apiClient';
import { ChefHat, Clock, Check, Bell, RefreshCw } from 'lucide-react';

const OrderSkeleton = memo(() => (
  <div className="animate-pulse grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    {[1,2,3,4,5,6].map(i => (
      <div key={i} className="h-48 bg-gray-200 rounded-xl"></div>
    ))}
  </div>
));

/**
 * Page Cuisine - Affichage des commandes à préparer
 */
export default function POSKitchenPage() {
  const navigate = useNavigate();
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('pending'); // pending, preparing, ready

  useEffect(() => {
    fetchOrders();
    // Rafraîchir toutes les 10 secondes
    const interval = setInterval(fetchOrders, 10000);
    return () => clearInterval(interval);
  }, [filter]);

  const fetchOrders = async () => {
    try {
      const res = await apiClient.get(`/pos/kitchen/orders?status=${filter}`);
      setOrders(res.data?.data || []);
    } catch (error) {
      console.error('Error:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleUpdateStatus = async (orderId, newStatus) => {
    try {
      await apiClient.post(`/pos/orders/${orderId}/status`, { status: newStatus });
      fetchOrders();
      // Notification sonore
      if (newStatus === 'ready') {
        new Audio('/sounds/bell.mp3').play().catch(() => {});
      }
    } catch (error) {
      alert('Erreur lors de la mise à jour');
    }
  };

  const getTimeElapsed = (createdAt) => {
    const diff = Math.floor((Date.now() - new Date(createdAt).getTime()) / 60000);
    if (diff < 1) return 'À l\'instant';
    if (diff < 60) return `${diff} min`;
    return `${Math.floor(diff / 60)}h ${diff % 60}min`;
  };

  const getUrgencyColor = (createdAt) => {
    const diff = Math.floor((Date.now() - new Date(createdAt).getTime()) / 60000);
    if (diff < 10) return 'border-green-400';
    if (diff < 20) return 'border-yellow-400';
    return 'border-red-400 animate-pulse';
  };

  const statusColors = {
    pending: 'bg-yellow-100 text-yellow-700',
    preparing: 'bg-orange-100 text-orange-700',
    ready: 'bg-green-100 text-green-700',
  };

  return (
    <div className="p-6 space-y-6 bg-gray-900 min-h-screen">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-white flex items-center gap-3">
            <ChefHat size={36} /> Statut Commandes
          </h1>
          <p className="text-gray-400 mt-1">Suivi des commandes en cours</p>
        </div>
        <div className="flex gap-2">
          <button onClick={() => navigate('/pos')} className="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
            ← Retour POS
          </button>
          <button onClick={fetchOrders} className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
            <RefreshCw size={18} className={loading ? 'animate-spin' : ''} /> Actualiser
          </button>
        </div>
      </div>

      {/* Filters */}
      <div className="flex gap-2">
        {[
          { id: 'pending', label: 'En attente', icon: Clock },
          { id: 'preparing', label: 'En préparation', icon: ChefHat },
          { id: 'ready', label: 'Prêt', icon: Check },
        ].map((f) => (
          <button
            key={f.id}
            onClick={() => setFilter(f.id)}
            className={`flex items-center gap-2 px-4 py-2 rounded-lg font-medium transition ${
              filter === f.id
                ? 'bg-blue-600 text-white'
                : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
            }`}
          >
            <f.icon size={18} />
            {f.label}
            {filter === f.id && orders.length > 0 && (
              <span className="bg-white text-blue-600 px-2 py-0.5 rounded-full text-sm font-bold">
                {orders.length}
              </span>
            )}
          </button>
        ))}
      </div>

      {/* Orders Grid */}
      {loading ? <OrderSkeleton /> : orders.length === 0 ? (
        <div className="text-center py-16">
          <ChefHat size={64} className="mx-auto text-gray-600 mb-4" />
          <p className="text-gray-400 text-xl">Aucune commande {filter === 'pending' ? 'en attente' : filter === 'preparing' ? 'en préparation' : 'prête'}</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {orders.map((order) => (
            <div
              key={order.id}
              className={`bg-gray-800 rounded-xl border-l-4 ${getUrgencyColor(order.created_at)} overflow-hidden`}
            >
              {/* Header */}
              <div className="bg-gray-700 px-4 py-3 flex justify-between items-center">
                <div>
                  <span className="font-mono font-bold text-white text-lg">{order.reference}</span>
                  {order.table_number && (
                    <span className="ml-2 bg-blue-600 text-white px-2 py-0.5 rounded text-sm">
                      Table {order.table_number}
                    </span>
                  )}
                </div>
                <div className="flex items-center gap-2 text-gray-400">
                  <Clock size={16} />
                  <span className="text-sm">{getTimeElapsed(order.created_at)}</span>
                </div>
              </div>

              {/* Items */}
              <div className="p-4 space-y-2 max-h-48 overflow-y-auto">
                {order.items?.map((item, idx) => (
                  <div key={idx} className="flex justify-between items-center text-white">
                    <div className="flex items-center gap-2">
                      <span className="bg-blue-600 text-white w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold">
                        {item.quantity_ordered || item.quantity || 1}
                      </span>
                      <span>{item.product?.name || item.name}</span>
                    </div>
                    {item.notes && (
                      <span className="text-xs text-yellow-400">📝 {item.notes}</span>
                    )}
                  </div>
                ))}
              </div>

              {/* Notes */}
              {order.notes && (
                <div className="px-4 py-2 bg-yellow-900/30 text-yellow-300 text-sm">
                  📝 {order.notes}
                </div>
              )}

              {/* Actions */}
              <div className="p-4 border-t border-gray-700 flex gap-2">
                {filter === 'pending' && (
                  <button
                    onClick={() => handleUpdateStatus(order.id, 'preparing')}
                    className="flex-1 bg-orange-600 hover:bg-orange-700 text-white py-2 rounded-lg font-medium flex items-center justify-center gap-2"
                  >
                    <ChefHat size={18} /> Commencer
                  </button>
                )}
                {filter === 'preparing' && (
                  <button
                    onClick={() => handleUpdateStatus(order.id, 'ready')}
                    className="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg font-medium flex items-center justify-center gap-2"
                  >
                    <Bell size={18} /> Prêt!
                  </button>
                )}
                {filter === 'ready' && (
                  <button
                    onClick={() => handleUpdateStatus(order.id, 'served')}
                    className="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg font-medium flex items-center justify-center gap-2"
                  >
                    <Check size={18} /> Servi
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Stats Footer */}
      <div className="fixed bottom-0 left-0 right-0 bg-gray-800 border-t border-gray-700 p-4">
        <div className="max-w-7xl mx-auto flex justify-around text-center">
          <div>
            <p className="text-2xl font-bold text-yellow-400">{orders.filter(o => o.status === 'pending').length}</p>
            <p className="text-gray-400 text-sm">En attente</p>
          </div>
          <div>
            <p className="text-2xl font-bold text-orange-400">{orders.filter(o => o.status === 'preparing').length}</p>
            <p className="text-gray-400 text-sm">En préparation</p>
          </div>
          <div>
            <p className="text-2xl font-bold text-green-400">{orders.filter(o => o.status === 'ready').length}</p>
            <p className="text-gray-400 text-sm">Prêt</p>
          </div>
        </div>
      </div>
    </div>
  );
}
