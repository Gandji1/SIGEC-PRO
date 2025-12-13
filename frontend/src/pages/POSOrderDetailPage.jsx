import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import apiClient from '../services/apiClient';
import { ArrowLeft, Printer, CreditCard, Trash2, Plus, Minus, Check, X } from 'lucide-react';

/**
 * Page de détail d'une commande POS
 */
export default function POSOrderDetailPage() {
  const navigate = useNavigate();
  const { orderId } = useParams();
  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(true);
  const [showPayment, setShowPayment] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState('especes');
  const [amountPaid, setAmountPaid] = useState(0);
  const [processing, setProcessing] = useState(false);

  useEffect(() => {
    fetchOrder();
  }, [orderId]);

  const fetchOrder = async () => {
    try {
      const res = await apiClient.get(`/approvisionnement/orders/${orderId}`);
      setOrder(res.data?.data || res.data);
      setAmountPaid(res.data?.total || res.data?.data?.total || 0);
    } catch (error) {
      console.error('Error:', error);
      alert('Commande non trouvée');
      navigate('/pos');
    } finally {
      setLoading(false);
    }
  };

  const handleUpdateItemQty = async (itemId, delta) => {
    try {
      const item = order.items.find(i => i.id === itemId);
      const newQty = item.quantity + delta;
      
      if (newQty <= 0) {
        await apiClient.delete(`/pos/orders/${orderId}/items/${itemId}`);
      } else {
        await apiClient.put(`/pos/orders/${orderId}/items/${itemId}`, { quantity: newQty });
      }
      fetchOrder();
    } catch (error) {
      alert('Erreur lors de la modification');
    }
  };

  const handleCancelOrder = async () => {
    if (!confirm('Annuler cette commande?')) return;
    try {
      await apiClient.post(`/approvisionnement/orders/${orderId}/cancel`);
      alert('Commande annulée');
      navigate('/pos');
    } catch (error) {
      alert('Erreur lors de l\'annulation');
    }
  };

  const handleCompletePayment = async () => {
    setProcessing(true);
    try {
      await apiClient.post(`/approvisionnement/orders/${orderId}/validate`, {
        payment_method: paymentMethod,
        amount_paid: parseFloat(amountPaid),
      });
      alert('Paiement effectué!');
      navigate('/pos');
    } catch (error) {
      alert(error.response?.data?.message || 'Erreur lors du paiement');
    } finally {
      setProcessing(false);
    }
  };

  const handlePrint = () => {
    window.print();
  };

  const formatCurrency = (val) => new Intl.NumberFormat('fr-FR', { 
    style: 'currency', currency: 'XAF', maximumFractionDigits: 0 
  }).format(val || 0);

  const statusColors = {
    pending: 'bg-yellow-100 text-yellow-700',
    preparing: 'bg-orange-100 text-orange-700',
    ready: 'bg-blue-100 text-blue-700',
    served: 'bg-green-100 text-green-700',
    completed: 'bg-purple-100 text-purple-700',
    cancelled: 'bg-red-100 text-red-700',
  };

  const statusLabels = {
    pending: 'En attente',
    preparing: 'En préparation',
    ready: 'Prêt',
    served: 'Servi',
    completed: 'Payé',
    cancelled: 'Annulé',
  };

  if (loading) {
    return (
      <div className="p-6 flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (!order) {
    return (
      <div className="p-6 text-center">
        <p className="text-gray-500">Commande non trouvée</p>
        <button onClick={() => navigate('/pos')} className="mt-4 text-blue-600 hover:underline">
          Retour au POS
        </button>
      </div>
    );
  }

  const change = parseFloat(amountPaid) - (order.total || 0);

  return (
    <div className="p-6 max-w-4xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div className="flex items-center gap-4">
          <button onClick={() => navigate(-1)} className="p-2 hover:bg-gray-100 rounded-lg">
            <ArrowLeft size={24} />
          </button>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Commande {order.reference}</h1>
            <p className="text-gray-500">
              {order.table_number ? `Table ${order.table_number}` : 'Comptoir'}
              {order.customer?.name && ` • ${order.customer.name}`}
            </p>
          </div>
        </div>
        <span className={`px-3 py-1 rounded-full text-sm font-medium ${statusColors[order.status]}`}>
          {statusLabels[order.status]}
        </span>
      </div>

      {/* Order Items */}
      <div className="bg-white rounded-xl shadow-sm overflow-hidden">
        <div className="p-4 border-b bg-gray-50">
          <h2 className="font-bold text-gray-900">Articles</h2>
        </div>
        <div className="divide-y">
          {order.items?.map((item) => (
            <div key={item.id} className="p-4 flex justify-between items-center">
              <div className="flex-1">
                <p className="font-medium">{item.product?.name || item.name}</p>
                <p className="text-sm text-gray-500">{formatCurrency(item.unit_price)} x {item.quantity}</p>
              </div>
              
              {order.status === 'pending' && (
                <div className="flex items-center gap-2 mr-4">
                  <button
                    onClick={() => handleUpdateItemQty(item.id, -1)}
                    className="p-1 bg-gray-100 rounded hover:bg-gray-200"
                  >
                    <Minus size={16} />
                  </button>
                  <span className="w-8 text-center font-medium">{item.quantity}</span>
                  <button
                    onClick={() => handleUpdateItemQty(item.id, 1)}
                    className="p-1 bg-gray-100 rounded hover:bg-gray-200"
                  >
                    <Plus size={16} />
                  </button>
                </div>
              )}
              
              <p className="font-bold text-lg">{formatCurrency(item.quantity * item.unit_price)}</p>
            </div>
          ))}
        </div>

        {/* Totals */}
        <div className="p-4 bg-gray-50 border-t space-y-2">
          <div className="flex justify-between text-gray-600">
            <span>Sous-total</span>
            <span>{formatCurrency(order.subtotal || order.total)}</span>
          </div>
          {order.tax > 0 && (
            <div className="flex justify-between text-gray-600">
              <span>TVA</span>
              <span>{formatCurrency(order.tax)}</span>
            </div>
          )}
          {order.discount > 0 && (
            <div className="flex justify-between text-green-600">
              <span>Remise</span>
              <span>-{formatCurrency(order.discount)}</span>
            </div>
          )}
          <div className="flex justify-between text-xl font-bold pt-2 border-t">
            <span>Total</span>
            <span>{formatCurrency(order.total)}</span>
          </div>
        </div>
      </div>

      {/* Payment Section */}
      {showPayment && order.status !== 'completed' && order.status !== 'cancelled' && (
        <div className="bg-white rounded-xl shadow-sm p-6 space-y-4">
          <h2 className="font-bold text-gray-900">Paiement</h2>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Mode de paiement</label>
            <div className="grid grid-cols-3 gap-2">
              {[
                { id: 'especes', label: '💵 Espèces' },
                { id: 'momo', label: '📱 MoMo' },
                { id: 'virement', label: '🏦 Virement' },
              ].map((m) => (
                <button
                  key={m.id}
                  onClick={() => setPaymentMethod(m.id)}
                  className={`py-3 px-4 rounded-lg border-2 font-medium transition ${
                    paymentMethod === m.id
                      ? 'border-blue-600 bg-blue-50 text-blue-700'
                      : 'border-gray-200 hover:border-gray-300'
                  }`}
                >
                  {m.label}
                </button>
              ))}
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Montant reçu</label>
            <input
              type="number"
              value={amountPaid}
              onChange={(e) => setAmountPaid(e.target.value)}
              className="w-full px-4 py-3 border rounded-lg text-xl font-bold text-center"
            />
          </div>

          {change > 0 && (
            <div className="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
              <p className="text-green-700">Monnaie à rendre</p>
              <p className="text-3xl font-bold text-green-600">{formatCurrency(change)}</p>
            </div>
          )}

          <button
            onClick={handleCompletePayment}
            disabled={processing || parseFloat(amountPaid) < order.total}
            className="w-full bg-green-600 hover:bg-green-700 text-white py-4 rounded-lg font-bold text-lg disabled:bg-gray-400 flex items-center justify-center gap-2"
          >
            {processing ? 'Traitement...' : <><Check size={24} /> Valider le Paiement</>}
          </button>
        </div>
      )}

      {/* Actions */}
      <div className="flex gap-3">
        {order.status !== 'completed' && order.status !== 'cancelled' && (
          <>
            {!showPayment ? (
              <button
                onClick={() => setShowPayment(true)}
                className="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-bold flex items-center justify-center gap-2"
              >
                <CreditCard size={20} /> Encaisser
              </button>
            ) : (
              <button
                onClick={() => setShowPayment(false)}
                className="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-lg font-bold"
              >
                Retour
              </button>
            )}
            <button
              onClick={handleCancelOrder}
              className="bg-red-100 hover:bg-red-200 text-red-700 py-3 px-6 rounded-lg font-bold flex items-center justify-center gap-2"
            >
              <X size={20} /> Annuler
            </button>
          </>
        )}
        <button
          onClick={handlePrint}
          className="bg-gray-100 hover:bg-gray-200 text-gray-700 py-3 px-6 rounded-lg font-bold flex items-center justify-center gap-2"
        >
          <Printer size={20} /> Imprimer
        </button>
      </div>
    </div>
  );
}
