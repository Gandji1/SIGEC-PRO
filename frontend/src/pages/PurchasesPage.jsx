import React, { useState, useEffect, useCallback, memo } from 'react';
import { useParams } from 'react-router-dom';
import { Package, Plus, CheckCircle, XCircle, AlertCircle, RefreshCw, ShoppingCart, AlertTriangle } from 'lucide-react';
import apiClient from '../services/apiClient';
import ExportButton from '../components/ExportButton';
import { PageLoader, ErrorMessage, EmptyState } from '../components/LoadingFallback';

export default function PurchasesPage() {
  const { id: purchaseIdFromUrl } = useParams();
  const [purchases, setPurchases] = useState([]);
  const [products, setProducts] = useState([]);
  const [suggestedOrders, setSuggestedOrders] = useState({ products_to_order: [], by_supplier: [], urgent_count: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [showSuggested, setShowSuggested] = useState(false);
  const [selectedPurchase, setSelectedPurchase] = useState(null);

  const [showForm, setShowForm] = useState(false);
  const [formData, setFormData] = useState({
    supplier_name: '',
    supplier_phone: '',
    items: [{ product_id: null, quantity: 0, unit_price: 0 }]
  });

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      // Chargement parallèle avec suggestions de commande - utiliser allSettled pour résilience
      const [prodRes, purRes, suggestedRes] = await Promise.allSettled([
        apiClient.get('/products?per_page=100'),
        apiClient.get('/purchases?per_page=50'),
        apiClient.get('/stocks/suggested-orders')
      ]);
      
      // Extraire les données avec fallback sécurisé
      const prodData = prodRes.status === 'fulfilled' ? (prodRes.value.data?.data || prodRes.value.data || []) : [];
      const purData = purRes.status === 'fulfilled' ? (purRes.value.data?.data || purRes.value.data || []) : [];
      const sugData = suggestedRes.status === 'fulfilled' ? suggestedRes.value.data : { products_to_order: [], by_supplier: [], urgent_count: 0 };
      
      setProducts(Array.isArray(prodData) ? prodData : []);
      const purchasesList = Array.isArray(purData) ? purData : [];
      setPurchases(purchasesList);
      setSuggestedOrders(sugData || { products_to_order: [], by_supplier: [], urgent_count: 0 });
      
      // Si un ID de commande est dans l'URL, sélectionner automatiquement cette commande
      if (purchaseIdFromUrl && purchasesList.length > 0) {
        const targetPurchase = purchasesList.find(p => String(p.id) === String(purchaseIdFromUrl));
        if (targetPurchase) {
          setSelectedPurchase(targetPurchase);
        }
      }
      
      // Vérifier si les requêtes principales ont échoué
      if (prodRes.status === 'rejected' && purRes.status === 'rejected') {
        setError('Impossible de charger les données. Vérifiez votre connexion.');
      }
    } catch (err) {
      console.error('Error fetching data:', err);
      setError(err.message || 'Erreur de chargement');
    } finally {
      setLoading(false);
    }
  }, [purchaseIdFromUrl]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const addItem = () => {
    setFormData({
      ...formData,
      items: [...formData.items, { product_id: null, quantity: 0, unit_price: 0 }]
    });
  };

  const removeItem = (idx) => {
    setFormData({
      ...formData,
      items: formData.items.filter((_, i) => i !== idx)
    });
  };

  const updateItem = (idx, field, value) => {
    const newItems = [...formData.items];
    newItems[idx][field] = value;
    setFormData({ ...formData, items: newItems });
  };

  const createPurchase = async () => {
    if (!formData.supplier_name || formData.items.length === 0) {
      setError('Veuillez remplir tous les champs');
      return;
    }

    try {
      setLoading(true);
      await apiClient.post('/purchases', formData);
      setSuccess('Commande d\'achat créée!');
      setShowForm(false);
      setFormData({
        supplier_name: '',
        supplier_phone: '',
        items: [{ product_id: null, quantity: 0, unit_price: 0 }]
      });
      fetchData();
      setTimeout(() => setSuccess(null), 3000);
    } catch (err) {
      console.error('Error creating purchase:', err);
      setError(err.response?.data?.message || err.message || 'Erreur lors de la création');
    } finally {
      setLoading(false);
    }
  };

  const confirmPurchase = async (id) => {
    try {
      await apiClient.post(`/purchases/${id}/confirm`);
      setSuccess('Achat confirmé!');
      fetchData();
      setTimeout(() => setSuccess(null), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la confirmation');
    }
  };

  const receivePurchase = async (id) => {
    try {
      await apiClient.post(`/purchases/${id}/receive`, { items: [] });
      setSuccess('Achat reçu! CMP calculé.');
      fetchData();
      setTimeout(() => setSuccess(null), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la réception');
    }
  };

  const cancelPurchase = async (id) => {
    try {
      await apiClient.post(`/purchases/${id}/cancel`);
      setSuccess('Achat annulé!');
      fetchData();
      setTimeout(() => setSuccess(null), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de l\'annulation');
    }
  };

  const formatCurrency = (val) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XAF', maximumFractionDigits: 0 }).format(val || 0);

  if (loading && purchases.length === 0) {
    return <PageLoader message="Chargement des achats..." />;
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-6">
      <div className="max-w-6xl mx-auto">
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
          <h1 className="text-2xl sm:text-3xl font-bold text-white flex items-center gap-2">
            <Package className="w-7 h-7 sm:w-8 sm:h-8 text-blue-400" />
            Achats
          </h1>
          <div className="flex items-center gap-3">
            {suggestedOrders.urgent_count > 0 && (
              <button
                onClick={() => setShowSuggested(!showSuggested)}
                className={`flex items-center gap-2 px-4 py-2 rounded-lg font-medium transition ${
                  showSuggested ? 'bg-orange-600 text-white' : 'bg-orange-500/20 text-orange-400 hover:bg-orange-500/30'
                }`}
              >
                <AlertTriangle size={18} />
                <span>{suggestedOrders.urgent_count} à commander</span>
              </button>
            )}
            <button
              onClick={fetchData}
              disabled={loading}
              className="p-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition"
            >
              <RefreshCw size={18} className={loading ? 'animate-spin' : ''} />
            </button>
            <ExportButton
              data={purchases.map(p => ({
                reference: p.reference,
                date: new Date(p.created_at).toLocaleDateString('fr-FR'),
                supplier: p.supplier_name,
                total: p.total_amount,
                status: p.status
              }))}
              columns={[
                { key: 'reference', header: 'Référence' },
                { key: 'date', header: 'Date' },
                { key: 'supplier', header: 'Fournisseur' },
                { key: 'total', header: 'Total', type: 'currency' },
                { key: 'status', header: 'Statut' }
              ]}
              filename="achats"
              title="Liste des Achats"
              variant="secondary"
            />
            <button
              onClick={() => setShowForm(!showForm)}
              className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium flex items-center gap-2"
            >
              <Plus size={20} /> Nouvel Achat
            </button>
          </div>
        </div>

        {error && (
          <div className="mb-4 bg-red-900 border border-red-700 text-red-100 px-4 py-3 rounded-lg flex items-center gap-2">
            <AlertCircle size={20} /> {error}
          </div>
        )}

        {success && (
          <div className="mb-4 bg-green-900 border border-green-700 text-green-100 px-4 py-3 rounded-lg">
            ✓ {success}
          </div>
        )}

        {/* Produits à commander */}
        {showSuggested && suggestedOrders.products_to_order.length > 0 && (
          <div className="bg-gradient-to-r from-orange-900/50 to-red-900/50 rounded-xl border border-orange-700 p-5 mb-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-bold text-white flex items-center gap-2">
                <ShoppingCart size={22} className="text-orange-400" />
                Produits à Commander
              </h2>
              <span className="text-orange-300 text-sm">
                Total estimé: <strong>{formatCurrency(suggestedOrders.total_amount)}</strong>
              </span>
            </div>
            
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-orange-300 border-b border-orange-700">
                    <th className="pb-2 font-medium">Produit</th>
                    <th className="pb-2 font-medium">Stock actuel</th>
                    <th className="pb-2 font-medium">Min requis</th>
                    <th className="pb-2 font-medium">Qté à commander</th>
                    <th className="pb-2 font-medium">Prix unit.</th>
                    <th className="pb-2 font-medium">Total</th>
                    <th className="pb-2 font-medium">Statut</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-orange-800/50">
                  {suggestedOrders.products_to_order.map((item, idx) => (
                    <tr key={idx} className="text-white">
                      <td className="py-2 font-medium">{item.product_name}</td>
                      <td className="py-2">
                        <span className={item.current_stock <= 0 ? 'text-red-400 font-bold' : 'text-yellow-400'}>
                          {item.current_stock} {item.unit}
                        </span>
                      </td>
                      <td className="py-2 text-gray-400">{item.min_stock} {item.unit}</td>
                      <td className="py-2 font-bold text-green-400">{item.quantity_to_order} {item.unit}</td>
                      <td className="py-2 text-gray-300">{formatCurrency(item.unit_price)}</td>
                      <td className="py-2 font-medium">{formatCurrency(item.total_price)}</td>
                      <td className="py-2">
                        <span className={`px-2 py-1 rounded text-xs font-bold ${
                          item.status === 'URGENT' ? 'bg-red-600 text-white' : 'bg-yellow-600 text-white'
                        }`}>
                          {item.status}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            
            {suggestedOrders.by_supplier && suggestedOrders.by_supplier.length > 0 && (
              <div className="mt-4 pt-4 border-t border-orange-700">
                <h3 className="text-sm font-semibold text-orange-300 mb-2">Par fournisseur:</h3>
                <div className="flex flex-wrap gap-2">
                  {suggestedOrders.by_supplier.map((sup, idx) => (
                    <span key={idx} className="bg-slate-700 px-3 py-1 rounded-full text-sm text-white">
                      {sup.supplier_name}: {sup.items_count} produits • {formatCurrency(sup.total_amount)}
                    </span>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}

        {/* Form */}
        {showForm && (
          <div className="bg-slate-800 rounded-lg border border-slate-700 p-6 mb-6">
            <h2 className="text-lg font-bold text-white mb-4">Create Purchase Order</h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
              <div>
                <label className="block text-white text-sm mb-2">Supplier Name</label>
                <input
                  type="text"
                  value={formData.supplier_name}
                  onChange={(e) => setFormData({ ...formData, supplier_name: e.target.value })}
                  className="w-full px-4 py-2 bg-slate-700 text-white border border-slate-600 rounded-lg focus:border-blue-500 outline-none"
                  placeholder="Supplier name..."
                />
              </div>

              <div>
                <label className="block text-white text-sm mb-2">Supplier Phone</label>
                <input
                  type="tel"
                  value={formData.supplier_phone}
                  onChange={(e) => setFormData({ ...formData, supplier_phone: e.target.value })}
                  className="w-full px-4 py-2 bg-slate-700 text-white border border-slate-600 rounded-lg focus:border-blue-500 outline-none"
                  placeholder="Phone number..."
                />
              </div>
            </div>

            {/* Items */}
            <div className="mb-4">
              <h3 className="text-white font-medium mb-2">Items</h3>
              {formData.items.map((item, idx) => (
                <div key={idx} className="flex gap-2 mb-2">
                  <select
                    value={item.product_id || ''}
                    onChange={(e) => updateItem(idx, 'product_id', Number(e.target.value))}
                    className="flex-1 px-3 py-2 bg-slate-700 text-white border border-slate-600 rounded text-sm focus:border-blue-500 outline-none"
                  >
                    <option value="">Select product...</option>
                    {products.map(prod => (
                      <option key={prod.id} value={prod.id}>
                        {prod.name}
                      </option>
                    ))}
                  </select>
                  <input
                    type="number"
                    min="1"
                    value={item.quantity}
                    onChange={(e) => updateItem(idx, 'quantity', Number(e.target.value))}
                    placeholder="Qty"
                    className="w-24 px-3 py-2 bg-slate-700 text-white border border-slate-600 rounded text-sm focus:border-blue-500 outline-none"
                  />
                  <input
                    type="number"
                    min="0"
                    value={item.unit_price}
                    onChange={(e) => updateItem(idx, 'unit_price', Number(e.target.value))}
                    placeholder="Price"
                    className="w-32 px-3 py-2 bg-slate-700 text-white border border-slate-600 rounded text-sm focus:border-blue-500 outline-none"
                  />
                  <button
                    onClick={() => removeItem(idx)}
                    className="px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded text-sm"
                  >
                    ✕
                  </button>
                </div>
              ))}
              <button
                onClick={addItem}
                className="text-blue-400 hover:text-blue-300 text-sm font-medium"
              >
                + Add Item
              </button>
            </div>

            <button
              onClick={createPurchase}
              disabled={loading}
              className="w-full px-4 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 text-white rounded-lg font-bold"
            >
              {loading ? 'Creating...' : 'Create Purchase Order'}
            </button>
          </div>
        )}

        {/* Purchases List */}
        <div className="space-y-4">
          {purchases.length > 0 ? (
            purchases.map(pur => (
              <div key={pur.id} className="bg-slate-800 rounded-lg border border-slate-700 p-6 hover:border-slate-600 transition">
                <div className="flex justify-between items-start mb-4">
                  <div>
                    <h3 className="text-lg font-bold text-white">PO-{String(pur.id).padStart(3, '0')}</h3>
                    <p className="text-slate-400 text-sm">
                      {pur.supplier_name} • {pur.supplier_phone}
                    </p>
                  </div>
                  <span className={`px-3 py-1 rounded-full text-xs font-medium ${
                    pur.status === 'submitted' ? 'bg-orange-600 text-orange-100' :
                    pur.status === 'pending' ? 'bg-yellow-600 text-yellow-100' :
                    pur.status === 'confirmed' ? 'bg-blue-600 text-blue-100' :
                    pur.status === 'shipped' ? 'bg-purple-600 text-purple-100' :
                    pur.status === 'delivered' ? 'bg-indigo-600 text-indigo-100' :
                    pur.status === 'received' ? 'bg-teal-600 text-teal-100' :
                    pur.status === 'paid' ? 'bg-green-600 text-green-100' :
                    pur.status === 'cancelled' ? 'bg-red-600 text-red-100' :
                    'bg-slate-600 text-slate-200'
                  }`}>
                    {pur.status === 'submitted' ? 'SOUMISE' :
                     pur.status === 'confirmed' ? 'CONFIRMÉE' :
                     pur.status === 'shipped' ? 'PRÉPARÉE' :
                     pur.status === 'delivered' ? 'LIVRÉE - À RÉCEPTIONNER' :
                     pur.status === 'received' ? 'RÉCEPTIONNÉE' :
                     pur.status === 'paid' ? 'PAYÉE' :
                     pur.status === 'cancelled' ? 'ANNULÉE' :
                     pur.status.toUpperCase()}
                  </span>
                </div>

                <div className="bg-slate-700 rounded p-4 mb-4">
                  <h4 className="text-white font-medium mb-2">Items:</h4>
                  <div className="space-y-1 text-slate-300 text-sm">
                    {pur.items?.map((item, idx) => (
                      <p key={idx}>
                        • {item.product?.name}: {item.quantity} × {item.unit_price?.toLocaleString()} = {(item.quantity * item.unit_price).toLocaleString()}
                      </p>
                    ))}
                  </div>
                </div>

                <div className="flex gap-2">
                  {(pur.status === 'submitted' || pur.status === 'pending') && (
                    <div className="flex-1 text-center py-2 text-slate-400 text-sm">
                      En attente de confirmation fournisseur...
                    </div>
                  )}
                  {(pur.status === 'confirmed' || pur.status === 'shipped') && (
                    <div className="flex-1 text-center py-2 text-blue-400 text-sm">
                      En cours de préparation/livraison par le fournisseur...
                    </div>
                  )}
                  {pur.status === 'delivered' && (
                    <button
                      onClick={() => receivePurchase(pur.id)}
                      className="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium flex items-center justify-center gap-2"
                    >
                      <CheckCircle size={18} /> Réceptionner (MAJ Stock + CMP)
                    </button>
                  )}
                  {pur.status === 'received' && (
                    <div className="flex-1 text-center py-2 text-teal-400 text-sm">
                      ✓ Réceptionné - Stock mis à jour
                    </div>
                  )}
                  {pur.status === 'paid' && (
                    <div className="flex-1 text-center py-2 text-green-400 text-sm">
                      ✓ Payé et clôturé
                    </div>
                  )}
                </div>
              </div>
            ))
          ) : (
            <div className="bg-slate-800 rounded-lg border border-slate-700 p-8 text-center">
              <p className="text-slate-400">No purchases yet</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
