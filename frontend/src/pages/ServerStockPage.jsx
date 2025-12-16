import React, { useState, useEffect } from 'react';
import { 
  Package, Plus, Minus, AlertTriangle, CheckCircle, 
  Clock, DollarSign, User, RefreshCw, Send, Eye,
  TrendingUp, ShoppingCart, XCircle
} from 'lucide-react';
import serverStockService from '../services/serverStockService';
import { useTenantStore } from '../stores/tenantStore';

/**
 * Page de gestion du stock délégué (Option B)
 * - Pour les SERVEURS: voir leur stock, enregistrer ventes, faire le point
 * - Pour les GÉRANTS: déléguer stock, voir réconciliations, valider
 */
export default function ServerStockPage() {
  const { user } = useTenantStore();
  const isManager = ['owner', 'admin', 'gerant', 'manager'].includes(user?.role);
  const isServer = ['pos_server', 'serveur', 'caissier'].includes(user?.role);

  const [activeTab, setActiveTab] = useState(isManager ? 'delegate' : 'my-stock');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  // États pour les données
  const [myStock, setMyStock] = useState([]);
  const [stockSummary, setStockSummary] = useState({});
  const [allStocks, setAllStocks] = useState([]);
  const [pendingReconciliations, setPendingReconciliations] = useState([]);
  const [statistics, setStatistics] = useState(null);
  const [movements, setMovements] = useState([]);

  // États pour les formulaires
  const [delegateForm, setDelegateForm] = useState({
    server_id: '',
    items: [{ product_id: '', quantity: 1, unit_price: '' }],
    notes: ''
  });
  const [reconciliationForm, setReconciliationForm] = useState({
    cash_collected: '',
    notes: ''
  });
  const [currentReconciliation, setCurrentReconciliation] = useState(null);

  // Données de référence
  const [servers, setServers] = useState([]);
  const [products, setProducts] = useState([]);

  useEffect(() => {
    loadInitialData();
  }, []);

  const loadInitialData = async () => {
    setLoading(true);
    try {
      if (isServer) {
        await loadMyStock();
      }
      if (isManager) {
        await Promise.all([
          loadAllStocks(),
          loadPendingReconciliations(),
          loadStatistics(),
          loadServers(),
          loadProducts()
        ]);
      }
    } catch (err) {
      setError('Erreur lors du chargement des données');
    } finally {
      setLoading(false);
    }
  };

  const loadMyStock = async () => {
    try {
      const response = await serverStockService.getMyStock();
      setMyStock(response.data.data || []);
      setStockSummary(response.data.summary || {});
    } catch (err) {
      console.error('Erreur chargement stock:', err);
    }
  };

  const loadAllStocks = async () => {
    try {
      const response = await serverStockService.getAll({ status: 'active' });
      setAllStocks(response.data.data || []);
    } catch (err) {
      console.error('Erreur chargement stocks:', err);
    }
  };

  const loadPendingReconciliations = async () => {
    try {
      const response = await serverStockService.getPendingReconciliations();
      setPendingReconciliations(response.data.data || []);
    } catch (err) {
      console.error('Erreur chargement réconciliations:', err);
    }
  };

  const loadStatistics = async () => {
    try {
      const response = await serverStockService.getStatistics();
      setStatistics(response.data);
    } catch (err) {
      console.error('Erreur chargement statistiques:', err);
    }
  };

  const loadServers = async () => {
    try {
      const response = await fetch('/api/users?role=serveur,pos_server', {
        headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
      });
      const data = await response.json();
      setServers(data.data || []);
    } catch (err) {
      console.error('Erreur chargement serveurs:', err);
    }
  };

  const loadProducts = async () => {
    try {
      const response = await fetch('/api/products', {
        headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
      });
      const data = await response.json();
      setProducts(data.data || []);
    } catch (err) {
      console.error('Erreur chargement produits:', err);
    }
  };

  const loadMovements = async () => {
    try {
      const response = await serverStockService.getMovements();
      setMovements(response.data.data || []);
    } catch (err) {
      console.error('Erreur chargement mouvements:', err);
    }
  };

  // Déléguer du stock (Gérant)
  const handleDelegate = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      await serverStockService.delegate(delegateForm);
      setSuccess('Stock délégué avec succès');
      setDelegateForm({
        server_id: '',
        items: [{ product_id: '', quantity: 1, unit_price: '' }],
        notes: ''
      });
      await loadAllStocks();
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la délégation');
    } finally {
      setLoading(false);
    }
  };

  // Ajouter un item au formulaire de délégation
  const addDelegateItem = () => {
    setDelegateForm(prev => ({
      ...prev,
      items: [...prev.items, { product_id: '', quantity: 1, unit_price: '' }]
    }));
  };

  // Supprimer un item du formulaire
  const removeDelegateItem = (index) => {
    setDelegateForm(prev => ({
      ...prev,
      items: prev.items.filter((_, i) => i !== index)
    }));
  };

  // Mettre à jour un item
  const updateDelegateItem = (index, field, value) => {
    setDelegateForm(prev => ({
      ...prev,
      items: prev.items.map((item, i) => 
        i === index ? { ...item, [field]: value } : item
      )
    }));
  };

  // Démarrer une réconciliation (Serveur)
  const handleStartReconciliation = async () => {
    setLoading(true);
    try {
      const response = await serverStockService.startReconciliation();
      setCurrentReconciliation(response.data.data);
      setSuccess('Réconciliation initiée');
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de l\'initiation');
    } finally {
      setLoading(false);
    }
  };

  // Soumettre la réconciliation (Serveur)
  const handleSubmitReconciliation = async (e) => {
    e.preventDefault();
    if (!currentReconciliation) return;
    
    setLoading(true);
    try {
      await serverStockService.submitReconciliation(currentReconciliation.id, reconciliationForm);
      setSuccess('Réconciliation soumise pour validation');
      setCurrentReconciliation(null);
      setReconciliationForm({ cash_collected: '', notes: '' });
      await loadMyStock();
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la soumission');
    } finally {
      setLoading(false);
    }
  };

  // Valider une réconciliation (Gérant)
  const handleValidateReconciliation = async (id) => {
    setLoading(true);
    try {
      await serverStockService.validateReconciliation(id, { notes: '' });
      setSuccess('Réconciliation validée');
      await loadPendingReconciliations();
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la validation');
    } finally {
      setLoading(false);
    }
  };

  // Contester une réconciliation (Gérant)
  const handleDisputeReconciliation = async (id, reason) => {
    setLoading(true);
    try {
      await serverStockService.disputeReconciliation(id, { reason });
      setSuccess('Réconciliation contestée');
      await loadPendingReconciliations();
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la contestation');
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(amount || 0);
  };

  return (
    <div className="p-6 max-w-7xl mx-auto">
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
          <Package className="w-7 h-7 text-orange-500" />
          Stock Délégué (Option B)
        </h1>
        <p className="text-gray-600 mt-1">
          {isManager 
            ? 'Déléguez du stock aux serveurs et validez leurs points de caisse'
            : 'Gérez votre stock délégué et faites le point avec le gérant'
          }
        </p>
      </div>

      {/* Messages */}
      {error && (
        <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg flex items-center gap-2 text-red-700">
          <AlertTriangle className="w-5 h-5" />
          {error}
          <button onClick={() => setError(null)} className="ml-auto">×</button>
        </div>
      )}
      {success && (
        <div className="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg flex items-center gap-2 text-green-700">
          <CheckCircle className="w-5 h-5" />
          {success}
          <button onClick={() => setSuccess(null)} className="ml-auto">×</button>
        </div>
      )}

      {/* Tabs */}
      <div className="border-b border-gray-200 mb-6">
        <nav className="flex space-x-4">
          {isServer && (
            <>
              <button
                onClick={() => setActiveTab('my-stock')}
                className={`py-3 px-4 border-b-2 font-medium text-sm ${
                  activeTab === 'my-stock'
                    ? 'border-orange-500 text-orange-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700'
                }`}
              >
                <Package className="w-4 h-4 inline mr-2" />
                Mon Stock
              </button>
              <button
                onClick={() => setActiveTab('reconciliation')}
                className={`py-3 px-4 border-b-2 font-medium text-sm ${
                  activeTab === 'reconciliation'
                    ? 'border-orange-500 text-orange-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700'
                }`}
              >
                <DollarSign className="w-4 h-4 inline mr-2" />
                Faire le Point
              </button>
            </>
          )}
          {isManager && (
            <>
              <button
                onClick={() => setActiveTab('delegate')}
                className={`py-3 px-4 border-b-2 font-medium text-sm ${
                  activeTab === 'delegate'
                    ? 'border-orange-500 text-orange-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700'
                }`}
              >
                <Send className="w-4 h-4 inline mr-2" />
                Déléguer Stock
              </button>
              <button
                onClick={() => setActiveTab('pending')}
                className={`py-3 px-4 border-b-2 font-medium text-sm ${
                  activeTab === 'pending'
                    ? 'border-orange-500 text-orange-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700'
                }`}
              >
                <Clock className="w-4 h-4 inline mr-2" />
                Points en Attente
                {pendingReconciliations.length > 0 && (
                  <span className="ml-2 bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">
                    {pendingReconciliations.length}
                  </span>
                )}
              </button>
              <button
                onClick={() => setActiveTab('overview')}
                className={`py-3 px-4 border-b-2 font-medium text-sm ${
                  activeTab === 'overview'
                    ? 'border-orange-500 text-orange-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700'
                }`}
              >
                <TrendingUp className="w-4 h-4 inline mr-2" />
                Vue d'ensemble
              </button>
            </>
          )}
          <button
            onClick={() => { setActiveTab('movements'); loadMovements(); }}
            className={`py-3 px-4 border-b-2 font-medium text-sm ${
              activeTab === 'movements'
                ? 'border-orange-500 text-orange-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <RefreshCw className="w-4 h-4 inline mr-2" />
            Historique
          </button>
        </nav>
      </div>

      {/* Content */}
      {loading && (
        <div className="flex justify-center py-12">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500"></div>
        </div>
      )}

      {!loading && (
        <>
          {/* Mon Stock (Serveur) */}
          {activeTab === 'my-stock' && isServer && (
            <div>
              {/* Summary Cards */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div className="bg-white rounded-lg shadow p-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm text-gray-500">Produits en stock</p>
                      <p className="text-2xl font-bold">{stockSummary.total_products || 0}</p>
                    </div>
                    <Package className="w-10 h-10 text-blue-500" />
                  </div>
                </div>
                <div className="bg-white rounded-lg shadow p-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm text-gray-500">Valeur restante</p>
                      <p className="text-2xl font-bold">{formatCurrency(stockSummary.total_remaining_value)}</p>
                    </div>
                    <DollarSign className="w-10 h-10 text-green-500" />
                  </div>
                </div>
                <div className="bg-white rounded-lg shadow p-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm text-gray-500">Total ventes</p>
                      <p className="text-2xl font-bold">{formatCurrency(stockSummary.total_sales)}</p>
                    </div>
                    <ShoppingCart className="w-10 h-10 text-orange-500" />
                  </div>
                </div>
              </div>

              {/* Stock List */}
              <div className="bg-white rounded-lg shadow overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produit</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Délégué</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendu</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Restant</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prix Unit.</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ventes</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {myStock.length === 0 ? (
                      <tr>
                        <td colSpan="6" className="px-6 py-12 text-center text-gray-500">
                          Aucun stock délégué pour le moment
                        </td>
                      </tr>
                    ) : (
                      myStock.map((stock) => (
                        <tr key={stock.id}>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="font-medium text-gray-900">{stock.product?.name}</div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-gray-500">
                            {stock.quantity_delegated}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-green-600 font-medium">
                            {stock.quantity_sold}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className={`font-medium ${stock.quantity_remaining < 5 ? 'text-red-600' : 'text-gray-900'}`}>
                              {stock.quantity_remaining}
                            </span>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-gray-500">
                            {formatCurrency(stock.unit_price)}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap font-medium text-green-600">
                            {formatCurrency(stock.total_sales_amount)}
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* Faire le Point (Serveur) */}
          {activeTab === 'reconciliation' && isServer && (
            <div className="max-w-2xl mx-auto">
              <div className="bg-white rounded-lg shadow p-6">
                <h2 className="text-lg font-semibold mb-4 flex items-center gap-2">
                  <DollarSign className="w-5 h-5 text-orange-500" />
                  Faire le Point avec le Gérant
                </h2>

                {!currentReconciliation ? (
                  <div className="text-center py-8">
                    <p className="text-gray-600 mb-4">
                      Cliquez pour démarrer une session de réconciliation avec le gérant.
                    </p>
                    <button
                      onClick={handleStartReconciliation}
                      className="bg-orange-500 text-white px-6 py-3 rounded-lg hover:bg-orange-600 flex items-center gap-2 mx-auto"
                    >
                      <Clock className="w-5 h-5" />
                      Démarrer le Point
                    </button>
                  </div>
                ) : (
                  <form onSubmit={handleSubmitReconciliation} className="space-y-4">
                    <div className="bg-blue-50 p-4 rounded-lg mb-4">
                      <p className="text-sm text-blue-700">
                        <strong>Référence:</strong> {currentReconciliation.reference}
                      </p>
                      <p className="text-sm text-blue-700">
                        <strong>Démarré:</strong> {new Date(currentReconciliation.session_start).toLocaleString('fr-FR')}
                      </p>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Montant collecté (espèces)
                      </label>
                      <input
                        type="number"
                        value={reconciliationForm.cash_collected}
                        onChange={(e) => setReconciliationForm(prev => ({ ...prev, cash_collected: e.target.value }))}
                        className="w-full border border-gray-300 rounded-lg px-4 py-2"
                        placeholder="0"
                        required
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Notes (optionnel)
                      </label>
                      <textarea
                        value={reconciliationForm.notes}
                        onChange={(e) => setReconciliationForm(prev => ({ ...prev, notes: e.target.value }))}
                        className="w-full border border-gray-300 rounded-lg px-4 py-2"
                        rows="3"
                        placeholder="Remarques, incidents..."
                      />
                    </div>

                    <button
                      type="submit"
                      className="w-full bg-green-500 text-white py-3 rounded-lg hover:bg-green-600 flex items-center justify-center gap-2"
                    >
                      <Send className="w-5 h-5" />
                      Soumettre pour Validation
                    </button>
                  </form>
                )}
              </div>
            </div>
          )}

          {/* Déléguer Stock (Gérant) */}
          {activeTab === 'delegate' && isManager && (
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {/* Formulaire */}
              <div className="bg-white rounded-lg shadow p-6">
                <h2 className="text-lg font-semibold mb-4 flex items-center gap-2">
                  <Send className="w-5 h-5 text-orange-500" />
                  Déléguer du Stock
                </h2>

                <form onSubmit={handleDelegate} className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Serveur
                    </label>
                    <select
                      value={delegateForm.server_id}
                      onChange={(e) => setDelegateForm(prev => ({ ...prev, server_id: e.target.value }))}
                      className="w-full border border-gray-300 rounded-lg px-4 py-2"
                      required
                    >
                      <option value="">Sélectionner un serveur</option>
                      {servers.map(server => (
                        <option key={server.id} value={server.id}>{server.name}</option>
                      ))}
                    </select>
                  </div>

                  <div className="space-y-3">
                    <label className="block text-sm font-medium text-gray-700">Produits</label>
                    {delegateForm.items.map((item, index) => (
                      <div key={index} className="flex gap-2 items-end">
                        <div className="flex-1">
                          <select
                            value={item.product_id}
                            onChange={(e) => updateDelegateItem(index, 'product_id', e.target.value)}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                            required
                          >
                            <option value="">Produit</option>
                            {products.map(p => (
                              <option key={p.id} value={p.id}>{p.name}</option>
                            ))}
                          </select>
                        </div>
                        <div className="w-24">
                          <input
                            type="number"
                            value={item.quantity}
                            onChange={(e) => updateDelegateItem(index, 'quantity', parseInt(e.target.value))}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                            placeholder="Qté"
                            min="1"
                            required
                          />
                        </div>
                        <div className="w-28">
                          <input
                            type="number"
                            value={item.unit_price}
                            onChange={(e) => updateDelegateItem(index, 'unit_price', e.target.value)}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                            placeholder="Prix"
                          />
                        </div>
                        {delegateForm.items.length > 1 && (
                          <button
                            type="button"
                            onClick={() => removeDelegateItem(index)}
                            className="p-2 text-red-500 hover:bg-red-50 rounded"
                          >
                            <Minus className="w-4 h-4" />
                          </button>
                        )}
                      </div>
                    ))}
                    <button
                      type="button"
                      onClick={addDelegateItem}
                      className="text-sm text-orange-600 hover:text-orange-700 flex items-center gap-1"
                    >
                      <Plus className="w-4 h-4" /> Ajouter un produit
                    </button>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Notes
                    </label>
                    <textarea
                      value={delegateForm.notes}
                      onChange={(e) => setDelegateForm(prev => ({ ...prev, notes: e.target.value }))}
                      className="w-full border border-gray-300 rounded-lg px-4 py-2"
                      rows="2"
                    />
                  </div>

                  <button
                    type="submit"
                    className="w-full bg-orange-500 text-white py-3 rounded-lg hover:bg-orange-600"
                  >
                    Déléguer le Stock
                  </button>
                </form>
              </div>

              {/* Liste des stocks actifs */}
              <div className="bg-white rounded-lg shadow p-6">
                <h2 className="text-lg font-semibold mb-4 flex items-center gap-2">
                  <Eye className="w-5 h-5 text-blue-500" />
                  Stocks Actifs
                </h2>

                <div className="space-y-3 max-h-96 overflow-y-auto">
                  {allStocks.length === 0 ? (
                    <p className="text-gray-500 text-center py-8">Aucun stock actif</p>
                  ) : (
                    allStocks.map(stock => (
                      <div key={stock.id} className="border rounded-lg p-3">
                        <div className="flex justify-between items-start">
                          <div>
                            <p className="font-medium">{stock.product?.name}</p>
                            <p className="text-sm text-gray-500">
                              <User className="w-3 h-3 inline mr-1" />
                              {stock.server?.name}
                            </p>
                          </div>
                          <div className="text-right">
                            <p className="text-sm">
                              <span className="text-gray-500">Restant:</span>{' '}
                              <span className="font-medium">{stock.quantity_remaining}</span>
                            </p>
                            <p className="text-sm text-green-600">
                              Ventes: {formatCurrency(stock.total_sales_amount)}
                            </p>
                          </div>
                        </div>
                      </div>
                    ))
                  )}
                </div>
              </div>
            </div>
          )}

          {/* Points en Attente (Gérant) */}
          {activeTab === 'pending' && isManager && (
            <div className="bg-white rounded-lg shadow overflow-hidden">
              <div className="px-6 py-4 border-b">
                <h2 className="text-lg font-semibold">Réconciliations en Attente</h2>
              </div>

              {pendingReconciliations.length === 0 ? (
                <div className="p-12 text-center text-gray-500">
                  <Clock className="w-12 h-12 mx-auto mb-4 text-gray-300" />
                  Aucune réconciliation en attente
                </div>
              ) : (
                <div className="divide-y">
                  {pendingReconciliations.map(rec => (
                    <div key={rec.id} className="p-6">
                      <div className="flex justify-between items-start mb-4">
                        <div>
                          <h3 className="font-semibold">{rec.reference}</h3>
                          <p className="text-sm text-gray-500">
                            <User className="w-4 h-4 inline mr-1" />
                            {rec.server?.name}
                          </p>
                          <p className="text-sm text-gray-500">
                            {new Date(rec.session_end).toLocaleString('fr-FR')}
                          </p>
                        </div>
                        <div className="text-right">
                          <p className="text-sm text-gray-500">Attendu</p>
                          <p className="font-semibold">{formatCurrency(rec.cash_expected)}</p>
                        </div>
                      </div>

                      <div className="grid grid-cols-3 gap-4 mb-4 bg-gray-50 p-4 rounded-lg">
                        <div>
                          <p className="text-xs text-gray-500">Collecté</p>
                          <p className="font-medium">{formatCurrency(rec.cash_collected)}</p>
                        </div>
                        <div>
                          <p className="text-xs text-gray-500">Écart</p>
                          <p className={`font-medium ${rec.cash_difference < 0 ? 'text-red-600' : 'text-green-600'}`}>
                            {formatCurrency(rec.cash_difference)}
                          </p>
                        </div>
                        <div>
                          <p className="text-xs text-gray-500">Pertes</p>
                          <p className="font-medium text-orange-600">{formatCurrency(rec.total_losses_value)}</p>
                        </div>
                      </div>

                      {rec.server_notes && (
                        <p className="text-sm text-gray-600 mb-4 italic">
                          "{rec.server_notes}"
                        </p>
                      )}

                      <div className="flex gap-3">
                        <button
                          onClick={() => handleValidateReconciliation(rec.id)}
                          className="flex-1 bg-green-500 text-white py-2 rounded-lg hover:bg-green-600 flex items-center justify-center gap-2"
                        >
                          <CheckCircle className="w-4 h-4" />
                          Valider
                        </button>
                        <button
                          onClick={() => {
                            const reason = prompt('Raison de la contestation:');
                            if (reason) handleDisputeReconciliation(rec.id, reason);
                          }}
                          className="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600 flex items-center justify-center gap-2"
                        >
                          <XCircle className="w-4 h-4" />
                          Contester
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* Vue d'ensemble (Gérant) */}
          {activeTab === 'overview' && isManager && statistics && (
            <div>
              <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div className="bg-white rounded-lg shadow p-4">
                  <p className="text-sm text-gray-500">Valeur Déléguée</p>
                  <p className="text-2xl font-bold">{formatCurrency(statistics.totals?.delegated_value)}</p>
                </div>
                <div className="bg-white rounded-lg shadow p-4">
                  <p className="text-sm text-gray-500">Total Ventes</p>
                  <p className="text-2xl font-bold text-green-600">{formatCurrency(statistics.totals?.sales)}</p>
                </div>
                <div className="bg-white rounded-lg shadow p-4">
                  <p className="text-sm text-gray-500">Stock Retourné</p>
                  <p className="text-2xl font-bold">{formatCurrency(statistics.totals?.returned_value)}</p>
                </div>
                <div className="bg-white rounded-lg shadow p-4">
                  <p className="text-sm text-gray-500">Bénéfice Brut</p>
                  <p className="text-2xl font-bold text-blue-600">{formatCurrency(statistics.totals?.gross_profit)}</p>
                </div>
              </div>

              {/* Par serveur */}
              <div className="bg-white rounded-lg shadow overflow-hidden">
                <div className="px-6 py-4 border-b">
                  <h2 className="text-lg font-semibold">Performance par Serveur</h2>
                </div>
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Serveur</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Délégué</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ventes</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Retourné</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pertes</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {(statistics.by_server || []).map((s, i) => (
                      <tr key={i}>
                        <td className="px-6 py-4 whitespace-nowrap font-medium">{s.server_name}</td>
                        <td className="px-6 py-4 whitespace-nowrap">{formatCurrency(s.total_delegated)}</td>
                        <td className="px-6 py-4 whitespace-nowrap text-green-600">{formatCurrency(s.total_sales)}</td>
                        <td className="px-6 py-4 whitespace-nowrap">{formatCurrency(s.total_returned)}</td>
                        <td className="px-6 py-4 whitespace-nowrap text-red-600">{formatCurrency(s.total_losses)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* Historique des mouvements */}
          {activeTab === 'movements' && (
            <div className="bg-white rounded-lg shadow overflow-hidden">
              <div className="px-6 py-4 border-b">
                <h2 className="text-lg font-semibold">Historique des Mouvements</h2>
              </div>
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produit</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantité</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Par</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {movements.length === 0 ? (
                    <tr>
                      <td colSpan="6" className="px-6 py-12 text-center text-gray-500">
                        Aucun mouvement
                      </td>
                    </tr>
                  ) : (
                    movements.map(m => (
                      <tr key={m.id}>
                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                          {new Date(m.created_at).toLocaleString('fr-FR')}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className={`px-2 py-1 text-xs rounded-full ${
                            m.type === 'delegation' ? 'bg-blue-100 text-blue-700' :
                            m.type === 'sale' ? 'bg-green-100 text-green-700' :
                            m.type === 'return' ? 'bg-yellow-100 text-yellow-700' :
                            m.type === 'loss' ? 'bg-red-100 text-red-700' :
                            'bg-gray-100 text-gray-700'
                          }`}>
                            {m.type === 'delegation' ? 'Délégation' :
                             m.type === 'sale' ? 'Vente' :
                             m.type === 'return' ? 'Retour' :
                             m.type === 'loss' ? 'Perte' : m.type}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">{m.product?.name}</td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className={m.quantity > 0 ? 'text-green-600' : 'text-red-600'}>
                            {m.quantity > 0 ? '+' : ''}{m.quantity}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">{formatCurrency(m.total_amount)}</td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          {m.performed_by_user?.name}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}
    </div>
  );
}
