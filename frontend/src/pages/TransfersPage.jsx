import React, { useState, useEffect, useCallback } from 'react';
import { TrendingUp, Send, CheckCircle, XCircle, AlertCircle, RefreshCw, Plus, ChevronDown, ChevronUp } from 'lucide-react';
import apiClient from '../services/apiClient';

export default function TransfersPage() {
  const [transfers, setTransfers] = useState([]);
  const [warehouses, setWarehouses] = useState([]);
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [filter, setFilter] = useState('all'); // all, pending, approved, validated
  const [expandedItems, setExpandedItems] = useState({});

  const [showForm, setShowForm] = useState(false);
  const [formData, setFormData] = useState({
    from_warehouse_id: null,
    to_warehouse_id: null,
    items: [{ product_id: null, quantity: 0 }],
    notes: ''
  });

  const fetchData = useCallback(async () => {
    try {
      // Charger les transferts d'abord (priorité)
      const tfRes = await apiClient.get('/transfers?per_page=50');
      const tfData = tfRes.data?.data || tfRes.data || [];
      setTransfers(Array.isArray(tfData) ? tfData : []);
      setLoading(false);
      
      // Charger les données secondaires en arrière-plan
      const [whRes, stockRes] = await Promise.allSettled([
        apiClient.get('/warehouses'),
        apiClient.get('/stocks?per_page=100'),
      ]);
      
      const whData = whRes.status === 'fulfilled' ? (whRes.value.data?.data || whRes.value.data || []) : [];
      const stockData = stockRes.status === 'fulfilled' ? (stockRes.value.data?.data || stockRes.value.data || []) : [];
      
      setWarehouses(Array.isArray(whData) ? whData : []);
      setProducts(Array.isArray(stockData) ? stockData : []);
    } catch (err) {
      console.error('Error fetching data:', err);
      setError(err.message || 'Erreur de chargement');
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const toggleExpand = (id) => {
    setExpandedItems(prev => ({ ...prev, [id]: !prev[id] }));
  };

  const addItem = () => {
    setFormData({
      ...formData,
      items: [...formData.items, { product_id: null, quantity: 0 }]
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

  const createTransfer = async () => {
    if (!formData.from_warehouse_id || !formData.to_warehouse_id) {
      setError('Sélectionner les deux entrepôts');
      return;
    }

    try {
      setLoading(true);
      const res = await apiClient.post('/transfers', formData);

      setSuccess('Transfert créé avec succès!');
      setShowForm(false);
      setFormData({
        from_warehouse_id: null,
        to_warehouse_id: null,
        items: [{ product_id: null, quantity: 0 }],
        notes: ''
      });
      fetchData();
      setTimeout(() => setSuccess(null), 3000);
    } catch (err) {
      console.error('Error creating transfer:', err);
      setError(err.response?.data?.message || err.message || 'Erreur lors de la création du transfert');
    } finally {
      setLoading(false);
    }
  };

  const approveTransfer = async (id) => {
    try {
      await apiClient.post(`/transfers/${id}/approve`);
      setSuccess('Transfert approuvé!');
      fetchData();
      setTimeout(() => setSuccess(null), 3000);
    } catch (err) {
      console.error('Error approving transfer:', err);
      setError(err.response?.data?.message || err.message || 'Erreur lors de l\'approbation');
    }
  };

  const cancelTransfer = async (id) => {
    try {
      await apiClient.post(`/transfers/${id}/cancel`);
      setSuccess('Transfert annulé!');
      fetchData();
      setTimeout(() => setSuccess(null), 3000);
    } catch (err) {
      console.error('Error canceling transfer:', err);
      setError(err.response?.data?.message || err.message || 'Erreur lors de l\'annulation');
    }
  };

  const filteredTransfers = transfers.filter(tf => {
    if (filter === 'all') return true;
    return tf.status === filter;
  });

  const getStatusStyle = (status) => {
    switch (status) {
      case 'pending': return 'bg-yellow-100 text-yellow-800';
      case 'approved': return 'bg-blue-100 text-blue-800';
      case 'validated': return 'bg-green-100 text-green-800';
      case 'cancelled': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getStatusLabel = (status) => {
    switch (status) {
      case 'pending': return 'En attente';
      case 'approved': return 'Approuvé';
      case 'validated': return 'Validé';
      case 'cancelled': return 'Annulé';
      default: return status;
    }
  };

  const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }) + ' ' + 
           d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900">
        <div className="w-12 h-12 border-4 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900 p-6">
      <div className="max-w-6xl mx-auto">
        {/* Header */}
        <div className="flex justify-between items-center mb-6">
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">📦 Transferts de Stock</h1>
            <p className="text-gray-500 dark:text-gray-400 text-sm">{transfers.length} transfert(s)</p>
          </div>
          <div className="flex gap-2">
            <button onClick={fetchData} className="p-2 bg-white dark:bg-gray-800 border dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">
              <RefreshCw size={20} className={loading ? 'animate-spin' : ''} />
            </button>
            <button
              onClick={() => setShowForm(!showForm)}
              className="flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium"
            >
              <Plus size={18} /> Nouveau Transfert
            </button>
          </div>
        </div>

        {/* Filtres */}
        <div className="flex gap-2 mb-4 overflow-x-auto pb-2">
          {[
            { id: 'all', label: 'Tous' },
            { id: 'pending', label: 'En attente' },
            { id: 'approved', label: 'Approuvés' },
            { id: 'validated', label: 'Validés' },
          ].map(f => (
            <button
              key={f.id}
              onClick={() => setFilter(f.id)}
              className={`px-4 py-2 rounded-lg font-medium whitespace-nowrap transition ${
                filter === f.id 
                  ? 'bg-orange-500 text-white' 
                  : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 border dark:border-gray-700'
              }`}
            >
              {f.label}
            </button>
          ))}
        </div>

        {error && (
          <div className="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-lg flex items-center gap-2">
            <AlertCircle size={20} /> {error}
            <button onClick={() => setError(null)} className="ml-auto">✕</button>
          </div>
        )}

        {success && (
          <div className="mb-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded-lg">
            ✓ {success}
          </div>
        )}

        {/* Formulaire */}
        {showForm && (
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <h2 className="text-lg font-bold text-gray-900 dark:text-white mb-4">Créer un transfert</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">De</label>
                <select
                  value={formData.from_warehouse_id || ''}
                  onChange={(e) => setFormData({ ...formData, from_warehouse_id: Number(e.target.value) })}
                  className="w-full px-3 py-2 border dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                >
                  <option value="">Sélectionner...</option>
                  {warehouses.map(wh => (
                    <option key={wh.id} value={wh.id}>{wh.name}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vers</label>
                <select
                  value={formData.to_warehouse_id || ''}
                  onChange={(e) => setFormData({ ...formData, to_warehouse_id: Number(e.target.value) })}
                  className="w-full px-3 py-2 border dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                >
                  <option value="">Sélectionner...</option>
                  {warehouses.map(wh => (
                    <option key={wh.id} value={wh.id}>{wh.name}</option>
                  ))}
                </select>
              </div>
            </div>

            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Articles</label>
              {formData.items.map((item, idx) => (
                <div key={idx} className="flex gap-2 mb-2">
                  <select
                    value={item.product_id || ''}
                    onChange={(e) => updateItem(idx, 'product_id', Number(e.target.value))}
                    className="flex-1 px-3 py-2 border dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                  >
                    <option value="">Produit...</option>
                    {products.filter(p => p.product?.name).map(prod => (
                      <option key={prod.id} value={prod.product_id || prod.id}>
                        {prod.product?.name} (Dispo: {prod.quantity})
                      </option>
                    ))}
                  </select>
                  <input
                    type="number"
                    min="1"
                    value={item.quantity}
                    onChange={(e) => updateItem(idx, 'quantity', Number(e.target.value))}
                    placeholder="Qté"
                    className="w-20 px-3 py-2 border dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                  />
                  <button onClick={() => removeItem(idx)} className="px-3 py-2 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50">✕</button>
                </div>
              ))}
              <button onClick={addItem} className="text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300 text-sm font-medium">+ Ajouter article</button>
            </div>

            <div className="flex gap-2">
              <button
                onClick={createTransfer}
                disabled={loading}
                className="flex-1 px-4 py-2 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white rounded-lg font-medium shadow-sm"
              >
                Créer le transfert
              </button>
              <button onClick={() => setShowForm(false)} className="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600">
                Annuler
              </button>
            </div>
          </div>
        )}

        {/* Liste des transferts - Grille de cartes plus larges */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          {filteredTransfers.length > 0 ? (
            filteredTransfers.map(tf => {
              const visibleItems = tf.items?.slice(0, 2) || [];
              const hiddenCount = (tf.items?.length || 0) - 2;
              const isExpanded = expandedItems[tf.id];
              const allItems = isExpanded ? tf.items : visibleItems;

              return (
                <div key={tf.id} className="bg-gradient-to-br from-white via-gray-50 to-slate-50 dark:from-gray-800 dark:via-gray-850 dark:to-slate-900 rounded-2xl shadow-md border border-gray-200/80 dark:border-gray-600/50 overflow-hidden hover:shadow-xl hover:border-orange-400 dark:hover:border-orange-500 transition-all duration-300 flex flex-col min-h-[240px]">
                  {/* Header avec numéro et statut */}
                  <div className="px-5 py-3 border-b border-gray-200/60 dark:border-gray-700/60 bg-white/50 dark:bg-gray-900/30 backdrop-blur-sm flex justify-between items-center">
                    <span className="font-bold text-gray-900 dark:text-white">TRF-{String(tf.id).padStart(3, '0')}</span>
                    <span className={`px-3 py-1 rounded-full text-xs font-bold ${
                      tf.status === 'pending' ? 'bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30' :
                      tf.status === 'approved' ? 'bg-sky-100 dark:bg-sky-500/20 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-500/30' :
                      tf.status === 'validated' ? 'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30' :
                      'bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30'
                    }`}>
                      {getStatusLabel(tf.status)}
                    </span>
                  </div>

                  {/* Date + Direction avec flèche */}
                  <div className="px-5 py-3 bg-gradient-to-r from-orange-100/80 via-amber-50/60 to-yellow-50/40 dark:from-orange-500/10 dark:via-amber-500/10 dark:to-yellow-500/5 border-b border-orange-200/50 dark:border-orange-500/20">
                    <div className="text-xs text-gray-600 dark:text-gray-400 mb-1 font-medium">{formatDate(tf.created_at)}</div>
                    <div className="flex items-center gap-3">
                      <span className="font-bold text-orange-700 dark:text-orange-300">{tf.from_warehouse?.name || 'Source'}</span>
                      <span className="text-orange-500 dark:text-orange-400 text-lg">→</span>
                      <span className="font-bold text-orange-700 dark:text-orange-300">{tf.to_warehouse?.name || 'Destination'}</span>
                    </div>
                  </div>

                  {/* Items avec design amélioré */}
                  <div className="p-5 flex-1 bg-white/30 dark:bg-gray-900/20">
                    <div className="space-y-2.5">
                      {allItems?.filter(item => item.product?.name).map((item, idx) => (
                        <div key={idx} className="flex items-center gap-3 group">
                          <span className="w-8 h-8 bg-gradient-to-br from-orange-400 to-orange-600 dark:from-orange-500 dark:to-orange-700 text-white rounded-lg flex items-center justify-center text-sm font-bold shadow-md flex-shrink-0">
                            {item.quantity}
                          </span>
                          <span className="text-gray-800 dark:text-gray-100 font-medium group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors">{item.product?.name}</span>
                        </div>
                      ))}
                    </div>

                    {hiddenCount > 0 && (
                      <button
                        onClick={() => toggleExpand(tf.id)}
                        className="mt-4 w-full py-2 text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300 text-sm font-bold flex items-center justify-center gap-1.5 bg-orange-100/60 dark:bg-orange-500/10 rounded-xl hover:bg-orange-200/60 dark:hover:bg-orange-500/20 transition-all border border-orange-200/50 dark:border-orange-500/20"
                      >
                        {isExpanded ? (
                          <><ChevronUp size={16} /> Réduire</>
                        ) : (
                          <><ChevronDown size={16} /> +{hiddenCount} article(s)</>
                        )}
                      </button>
                    )}
                  </div>

                  {/* Actions / Status Footer */}
                  {tf.status === 'pending' && (
                    <div className="px-5 py-3 border-t border-gray-200/60 dark:border-gray-700/60 bg-gray-100/50 dark:bg-gray-900/40 flex gap-3">
                      <button
                        onClick={() => approveTransfer(tf.id)}
                        className="flex-1 px-4 py-2.5 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-xl text-sm font-bold flex items-center justify-center gap-2 shadow-md hover:shadow-lg transition-all"
                      >
                        <CheckCircle size={16} /> Approuver
                      </button>
                      <button
                        onClick={() => cancelTransfer(tf.id)}
                        className="px-4 py-2.5 bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 hover:bg-rose-200 dark:hover:bg-rose-500/30 rounded-xl text-sm font-bold border border-rose-200 dark:border-rose-500/30"
                      >
                        <XCircle size={16} />
                      </button>
                    </div>
                  )}

                  {tf.status === 'validated' && (
                    <div className="px-5 py-3 border-t border-emerald-200/60 dark:border-emerald-500/20 bg-gradient-to-r from-emerald-100/80 to-green-50/60 dark:from-emerald-500/15 dark:to-green-500/10 text-emerald-700 dark:text-emerald-300 font-bold flex items-center justify-center gap-2">
                      <CheckCircle size={18} /> Transfert effectué
                    </div>
                  )}

                  {tf.status === 'approved' && (
                    <div className="px-5 py-3 border-t border-sky-200/60 dark:border-sky-500/20 bg-gradient-to-r from-sky-100/80 to-blue-50/60 dark:from-sky-500/15 dark:to-blue-500/10 text-sky-700 dark:text-sky-300 font-bold flex items-center justify-center gap-2">
                      <CheckCircle size={18} /> En cours
                    </div>
                  )}

                  {tf.status === 'cancelled' && (
                    <div className="px-5 py-3 border-t border-rose-200/60 dark:border-rose-500/20 bg-gradient-to-r from-rose-100/80 to-red-50/60 dark:from-rose-500/15 dark:to-red-500/10 text-rose-700 dark:text-rose-300 font-bold flex items-center justify-center gap-2">
                      <XCircle size={18} /> Annulé
                    </div>
                  )}
                </div>
              );
            })
          ) : (
            <div className="col-span-full bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 rounded-2xl shadow-md border border-gray-200 dark:border-gray-700 p-8 text-center">
              <p className="text-gray-500 dark:text-gray-400">Aucun transfert {filter !== 'all' ? `avec le statut "${getStatusLabel(filter)}"` : ''}</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
