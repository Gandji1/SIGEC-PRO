import React, { useState, useEffect, useCallback, memo } from 'react';
import { Package, RefreshCw, Save, Download, Lock, AlertTriangle } from 'lucide-react';
import apiClient from '../services/apiClient';
import { useTenantStore } from '../stores/tenantStore';
import { PageLoader, ErrorMessage, EmptyState } from '../components/LoadingFallback';

/**
 * Page d'inventaire enrichie avec:
 * - Stock Initial + Entrées - Sorties = SDU Théorique
 * - Stock Physique (saisie manuelle)
 * - Écart = Stock Physique - SDU Théorique
 * - CMM (Consommation Moyenne Mensuelle)
 * - CMP (Coût Moyen Pondéré)
 * - Min/Max et Statut
 */
export default function EnrichedInventoryPage() {
  const { user } = useTenantStore();
  const [warehouses, setWarehouses] = useState([]);
  const [selectedWarehouse, setSelectedWarehouse] = useState(null);
  const [inventoryData, setInventoryData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [physicalCounts, setPhysicalCounts] = useState({});
  const [message, setMessage] = useState({ type: '', text: '' });
  const [cmmPeriod, setCmmPeriod] = useState('month'); // 'day', 'week', 'month'

  // Seul le Gérant peut entrer le stock physique (pas le Tenant/Owner)
  const canEditPhysicalStock = user?.role === 'manager' || user?.role === 'super_admin';

  // Charger les entrepôts
  useEffect(() => {
    const fetchWarehouses = async () => {
      try {
        const res = await apiClient.get('/warehouses');
        const whs = Array.isArray(res.data?.data) ? res.data.data : 
                    Array.isArray(res.data) ? res.data : [];
        setWarehouses(whs);
        if (whs.length > 0) {
          setSelectedWarehouse(whs[0].id);
        }
      } catch (err) {
        console.error('Erreur chargement entrepôts:', err);
        setMessage({ type: 'error', text: 'Impossible de charger les entrepôts' });
        setWarehouses([]);
      } finally {
        setLoading(false);
      }
    };
    fetchWarehouses();
  }, []);

  // Charger les données d'inventaire enrichies depuis l'API backend
  const fetchInventoryData = useCallback(async () => {
    if (!selectedWarehouse) return;
    setLoading(true);
    setMessage({ type: '', text: '' });
    
    try {
      const res = await apiClient.get('/inventory/enriched-data', {
        params: { warehouse_id: selectedWarehouse, period: cmmPeriod }
      });
      
      const data = res.data?.data;
      
      if (!data || !data.items) {
        setMessage({ type: 'error', text: 'Aucune donnée disponible pour cet entrepôt' });
        setInventoryData(null);
        return;
      }
      
      setInventoryData(data);
      
      // Initialiser les comptages physiques
      const counts = {};
      (data.items || []).forEach(item => {
        counts[item.product_id] = item.stock_physique ?? '';
      });
      setPhysicalCounts(counts);
      
    } catch (err) {
      console.error('Erreur inventaire enrichi:', err);
      const errorMsg = err.response?.data?.message || err.message || 'Erreur inconnue';
      setMessage({ type: 'error', text: 'Erreur de chargement: ' + errorMsg });
      setInventoryData(null);
    } finally {
      setLoading(false);
    }
  }, [selectedWarehouse, cmmPeriod]);

  useEffect(() => {
    fetchInventoryData();
  }, [fetchInventoryData]);

  // Mettre à jour le comptage physique
  const handlePhysicalCountChange = (productId, value) => {
    setPhysicalCounts(prev => ({
      ...prev,
      [productId]: value
    }));
  };

  // Calculer l'écart = Stock Physique - SDU Théorique
  const calculateEcart = (sduTheorique, stockPhysique) => {
    if (stockPhysique === '' || stockPhysique === null || stockPhysique === undefined) return null;
    return parseInt(stockPhysique) - sduTheorique;
  };

  // Calculer le statut basé sur le STOCK PHYSIQUE entré manuellement
  const calculateStatus = (stockPhysique, sduTheorique, minStock, maxStock) => {
    // Si stock physique renseigné, on l'utilise, sinon SDU théorique
    const stockPourStatut = (stockPhysique !== '' && stockPhysique !== null && stockPhysique !== undefined) 
      ? parseInt(stockPhysique) 
      : sduTheorique;
    
    if (stockPourStatut <= 0) return 'rupture';
    if (stockPourStatut < minStock) return 'sous-stocke';
    if (stockPourStatut > maxStock) return 'sur-stocke';
    return 'normal';
  };

  // Compter les stocks physiques renseignés
  const getFilledCounts = () => {
    return Object.entries(physicalCounts).filter(([_, value]) => value !== '' && value !== null && value !== undefined);
  };

  // Sauvegarder les stocks physiques
  const handleSavePhysicalCounts = async () => {
    const filledCounts = getFilledCounts();
    if (filledCounts.length === 0) {
      setMessage({ type: 'error', text: 'Aucun stock physique à enregistrer' });
      return;
    }

    setSaving(true);
    try {
      const counts = filledCounts.map(([productId, value]) => ({
        product_id: parseInt(productId),
        stock_physique: parseInt(value),
      }));

      const res = await apiClient.post('/inventory/save-physical-counts', {
        warehouse_id: selectedWarehouse,
        counts,
      });

      const adjustments = res.data?.data?.adjustments || [];
      const savedCount = res.data?.data?.saved_count || 0;

      if (adjustments.length > 0) {
        setMessage({ 
          type: 'success', 
          text: `${savedCount} stock(s) enregistré(s). ${adjustments.length} ajustement(s) effectué(s).` 
        });
      } else {
        setMessage({ type: 'success', text: `${savedCount} stock(s) physique(s) enregistré(s)` });
      }

      // Recharger les données
      fetchInventoryData();
    } catch (err) {
      setMessage({ type: 'error', text: err.response?.data?.message || 'Erreur lors de l\'enregistrement' });
    } finally {
      setSaving(false);
      setTimeout(() => setMessage({ type: '', text: '' }), 5000);
    }
  };

  const formatPrice = (p) => new Intl.NumberFormat('fr-FR').format(p || 0);
  const currentWarehouse = warehouses.find(w => w.id === selectedWarehouse);

  // Exporter la fiche d'inventaire enrichi
  const handleExport = async () => {
    if (!selectedWarehouse) return;
    
    try {
      const response = await apiClient.get('/inventory/export-enriched', {
        params: { warehouse_id: selectedWarehouse, period: cmmPeriod },
        responseType: 'blob'
      });
      
      // Créer un lien de téléchargement
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `inventaire_enrichi_${currentWarehouse?.name || 'export'}_${new Date().toISOString().split('T')[0]}.csv`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
      
      setMessage({ type: 'success', text: 'Export téléchargé avec succès!' });
      setTimeout(() => setMessage({ type: '', text: '' }), 3000);
    } catch (err) {
      setMessage({ type: 'error', text: 'Erreur lors de l\'export' });
    }
  };

  const getStatusBadge = (status) => {
    const configs = {
      'rupture': { bg: 'bg-red-100', text: 'text-red-700', label: 'Rupture' },
      'sous-stocke': { bg: 'bg-orange-100', text: 'text-orange-700', label: 'Sous-stocké' },
      'normal': { bg: 'bg-green-100', text: 'text-green-700', label: 'Normal' },
      'sur-stocke': { bg: 'bg-blue-100', text: 'text-blue-700', label: 'Sur-stocké' },
    };
    const config = configs[status] || configs.normal;
    return <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${config.bg} ${config.text}`}>{config.label}</span>;
  };

  return (
    <div className="p-4 sm:p-6 space-y-6 bg-gray-50 dark:bg-gray-900 min-h-screen">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
            <Package className="text-blue-600" size={28} />
            Inventaire Enrichi
          </h1>
          <p className="text-gray-500 dark:text-gray-400 mt-1">
            SDU, CMM, Points de commande et génération automatique
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          {/* Sélecteur d'entrepôt */}
          <select
            value={selectedWarehouse || ''}
            onChange={(e) => setSelectedWarehouse(parseInt(e.target.value))}
            className="px-3 py-2 border rounded-lg bg-white dark:bg-gray-800"
          >
            {warehouses.map(wh => (
              <option key={wh.id} value={wh.id}>
                {wh.name} ({wh.type === 'gros' ? 'Gros' : 'Détail'})
              </option>
            ))}
          </select>
          
          {/* Sélecteur de période CMM */}
          <select
            value={cmmPeriod}
            onChange={(e) => setCmmPeriod(e.target.value)}
            className="px-3 py-2 border rounded-lg bg-white dark:bg-gray-800"
            title="Période de calcul CMM"
          >
            <option value="day">CMM Jour (3 derniers jours)</option>
            <option value="week">CMM Semaine (3 dernières semaines)</option>
            <option value="month">CMM Mois (3 derniers mois)</option>
          </select>
          
          <button
            onClick={fetchInventoryData}
            disabled={loading}
            className="p-2 bg-white dark:bg-gray-800 border rounded-lg hover:bg-gray-50"
            title="Actualiser"
          >
            <RefreshCw size={18} className={loading ? 'animate-spin' : ''} />
          </button>
          
          {/* Bouton Enregistrer l'inventaire - Visible uniquement pour le Gérant */}
          {canEditPhysicalStock ? (
            <button
              onClick={handleSavePhysicalCounts}
              disabled={saving || getFilledCounts().length === 0}
              className="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white rounded-lg font-medium"
              title="Enregistrer les stocks physiques"
            >
              <Save size={18} className={saving ? 'animate-pulse' : ''} />
              {saving ? 'Enregistrement...' : `Enregistrer (${getFilledCounts().length})`}
            </button>
          ) : (
            <div className="flex items-center gap-2 px-4 py-2 bg-gray-200 text-gray-500 rounded-lg text-sm">
              <Lock size={16} />
              Lecture seule
            </div>
          )}
          
          {/* Bouton Exporter */}
          <button
            onClick={handleExport}
            disabled={!inventoryData?.items?.length}
            className="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white rounded-lg font-medium"
            title="Exporter la fiche d'inventaire"
          >
            <Download size={18} />
            Exporter CSV
          </button>
        </div>
      </div>

      {/* Message */}
      {message.text && (
        <div className={`p-3 rounded-lg ${message.type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
          {message.text}
        </div>
      )}

      {/* Résumé */}
      {inventoryData?.summary && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="bg-white dark:bg-gray-800 rounded-xl p-4 border shadow-sm">
            <p className="text-sm text-gray-500">Total Produits</p>
            <p className="text-2xl font-bold">{inventoryData.summary.total_products}</p>
          </div>
          <div className="bg-orange-50 dark:bg-orange-900/20 rounded-xl p-4 border border-orange-200">
            <p className="text-sm text-orange-700">Stock Faible/Rupture</p>
            <p className="text-2xl font-bold text-orange-800">{inventoryData.summary.low_stock_count || 0}</p>
          </div>
          <div className="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 border border-blue-200">
            <p className="text-sm text-blue-700">Valeur Stock</p>
            <p className="text-2xl font-bold text-blue-800">{formatPrice(inventoryData.summary.total_value)} FCFA</p>
          </div>
          <div className="bg-white dark:bg-gray-800 rounded-xl p-4 border shadow-sm">
            <p className="text-sm text-gray-500">Entrepôt</p>
            <p className="text-lg font-bold">{currentWarehouse?.name}</p>
            <span className={`text-xs px-2 py-0.5 rounded ${currentWarehouse?.type === 'gros' ? 'bg-purple-100 text-purple-700' : 'bg-green-100 text-green-700'}`}>
              {currentWarehouse?.type === 'gros' ? 'Magasin Gros' : 'Magasin Détail'}
            </span>
          </div>
        </div>
      )}

      {/* Tableau d'inventaire */}
      <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border overflow-hidden">
        <div className="overflow-x-auto">
          {loading ? (
            <PageLoader message="Chargement de l'inventaire..." />
          ) : !inventoryData?.items?.length ? (
            <EmptyState 
              icon={Package}
              title="Aucun produit en stock"
              message="Aucune donnée d'inventaire disponible pour cet entrepôt."
              action={fetchInventoryData}
              actionLabel="Actualiser"
            />
          ) : (
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700 text-xs uppercase text-gray-500">
                <tr>
                  <th className="px-3 py-3 text-left">Produit</th>
                  <th className="px-3 py-3 text-center bg-purple-50 dark:bg-purple-900/20">Stock Initial</th>
                  <th className="px-3 py-3 text-center bg-green-50 dark:bg-green-900/20">Entrées</th>
                  <th className="px-3 py-3 text-center bg-red-50 dark:bg-red-900/20">Sorties</th>
                  <th className="px-3 py-3 text-center bg-blue-50 dark:bg-blue-900/20">SDU Théorique</th>
                  <th className="px-3 py-3 text-center">Stock Physique</th>
                  <th className="px-3 py-3 text-center">Écart</th>
                  <th className="px-3 py-3 text-center">CMM</th>
                  <th className="px-3 py-3 text-center">CMP</th>
                  <th className="px-3 py-3 text-center">Min</th>
                  <th className="px-3 py-3 text-center">Max</th>
                  <th className="px-3 py-3 text-center">Statut</th>
                </tr>
              </thead>
              <tbody className="divide-y">
                {(inventoryData?.items || []).map(item => {
                  const physicalCount = physicalCounts[item.product_id];
                  const ecart = calculateEcart(item.sdu_theorique, physicalCount);
                  
                  // Calcul dynamique du statut basé sur le stock physique entré
                  const dynamicStatus = calculateStatus(physicalCount, item.sdu_theorique, item.min_stock, item.max_stock);
                  
                  return (
                    <tr key={item.product_id} className={`hover:bg-gray-50 dark:hover:bg-gray-700 ${dynamicStatus === 'rupture' ? 'bg-red-50 dark:bg-red-900/10' : dynamicStatus === 'sous-stocke' ? 'bg-orange-50 dark:bg-orange-900/10' : ''}`}>
                      <td className="px-3 py-3">
                        <div className="font-medium">{item.product_name}</div>
                        <div className="text-xs text-gray-500">{item.sku} • {item.unit}</div>
                      </td>
                      <td className="px-3 py-3 text-center font-mono bg-purple-50/50 dark:bg-purple-900/10">{item.stock_initial || 0}</td>
                      <td className="px-3 py-3 text-center font-mono bg-green-50/50 dark:bg-green-900/10 text-green-700 font-medium">
                        {item.entrees > 0 ? `+${item.entrees}` : item.entrees || 0}
                      </td>
                      <td className="px-3 py-3 text-center font-mono bg-red-50/50 dark:bg-red-900/10 text-red-700 font-medium">
                        {item.sorties || 0}
                      </td>
                      <td className="px-3 py-3 text-center font-mono bg-blue-50/50 dark:bg-blue-900/10 font-bold">{item.sdu_theorique}</td>
                      <td className="px-3 py-3 text-center">
                        {canEditPhysicalStock ? (
                          <input
                            type="number"
                            value={physicalCount}
                            onChange={(e) => handlePhysicalCountChange(item.product_id, e.target.value)}
                            className="w-20 px-2 py-1 border rounded text-center"
                            placeholder="—"
                            min="0"
                          />
                        ) : (
                          <span className="text-gray-400 font-mono">{physicalCount || '—'}</span>
                        )}
                      </td>
                      <td className="px-3 py-3 text-center font-mono">
                        {ecart !== null && (
                          <span className={`font-medium ${ecart > 0 ? 'text-green-600' : ecart < 0 ? 'text-red-600' : 'text-gray-600'}`}>
                            {ecart > 0 ? '+' : ''}{ecart}
                          </span>
                        )}
                      </td>
                      <td className="px-3 py-3 text-center font-mono">{item.cmm}</td>
                      <td className="px-3 py-3 text-center font-mono text-blue-600">{formatPrice(item.cmp || 0)}</td>
                      <td className="px-3 py-3 text-center">{item.min_stock}</td>
                      <td className="px-3 py-3 text-center">{item.max_stock}</td>
                      <td className="px-3 py-3 text-center">{getStatusBadge(dynamicStatus)}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          )}
        </div>
      </div>

      {/* Légende */}
      <div className="bg-white dark:bg-gray-800 rounded-xl p-4 border">
        <h3 className="font-semibold mb-2">Légende & Formules</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
          <div>
            <p className="font-medium text-purple-700 mb-1">Traçabilité Stock</p>
            <p><strong className="bg-purple-100 px-1 rounded">Stock Initial</strong> = Stock en début de période</p>
            <p><strong className="bg-green-100 px-1 rounded">Entrées</strong> = Réceptions fournisseur</p>
            <p><strong className="bg-red-100 px-1 rounded">Sorties</strong> = Ventes POS validées</p>
            <p><strong className="bg-blue-100 px-1 rounded">SDU Théorique</strong> = Stock Initial + Entrées - Sorties</p>
          </div>
          <div>
            <p className="font-medium text-blue-700 mb-1">Inventaire</p>
            <p><strong>Stock Physique</strong> = Comptage réel (saisie manuelle)</p>
            <p><strong>Écart</strong> = Stock Physique - SDU Théorique</p>
            <p className="mt-2"><strong>CMM</strong> = Consommation Moyenne Mensuelle</p>
            <p><strong>CMP</strong> = Coût Moyen Pondéré</p>
          </div>
          <div>
            <p className="font-medium text-gray-700 mb-1">Statut (basé sur Stock Physique ou SDU)</p>
            <ul className="list-disc list-inside text-xs">
              <li><span className="text-red-600 font-medium">Rupture</span> = Stock = 0</li>
              <li><span className="text-orange-600 font-medium">Sous-stocké</span> = Stock &lt; Min</li>
              <li><span className="text-green-600 font-medium">Normal</span> = Min ≤ Stock ≤ Max</li>
              <li><span className="text-blue-600 font-medium">Sur-stocké</span> = Stock &gt; Max</li>
            </ul>
          </div>
        </div>
        <div className="mt-3 pt-3 border-t text-xs text-gray-500">
          <p>💡 Entrez le stock physique pour calculer automatiquement l'écart et le statut.</p>
          <p>📄 <strong>Exporter CSV</strong> : télécharge la fiche d'inventaire complète pour archivage ou impression</p>
        </div>
      </div>
    </div>
  );
}
