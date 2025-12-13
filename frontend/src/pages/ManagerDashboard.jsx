import { useEffect, useState, memo, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { useTenantStore } from '../stores/tenantStore';
import apiClient from '../services/apiClient';
import { TrendingUp, Package, AlertTriangle, Wallet, Send, DollarSign, RefreshCw, ArrowRight, CheckCircle, ShoppingCart, BarChart3, XCircle } from 'lucide-react';
import { PageLoader, ErrorMessage } from '../components/LoadingFallback';

// Composant KPI
const KPI = memo(({ title, value, icon, color }) => (
  <div className="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-sm">
    <div className="flex items-center gap-3">
      <div className={`${color} w-11 h-11 rounded-lg flex items-center justify-center text-white shadow`}>{icon}</div>
      <div className="flex-1 min-w-0">
        <p className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">{title}</p>
        <p className="text-lg font-bold text-gray-900 dark:text-white truncate">{value}</p>
      </div>
    </div>
  </div>
));

export default function ManagerDashboard() {
  const { tenant } = useTenantStore();
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState({
    todaySales: 0,
    todaySalesAmount: 0,
    pendingTransfers: 0,
    lowStockItems: 0,
    pendingPurchases: 0,
    salesTrend: [],
    topProducts: [],
    lowStockProducts: [],
    cashData: null,
    pendingRemittances: []
  });

  const [error, setError] = useState(null);

  const fetchAllData = useCallback(async () => {
    setLoading(true);
    setError(null);
    
    // Étape 1: Charger les stats principales d'abord (priorité haute)
    try {
      const statsRes = await apiClient.get('/dashboard/manager/stats');
      const stats = statsRes.data?.data || statsRes.data || {};
      
      setData(prev => ({
        ...prev,
        todaySales: stats.todaySales ?? 0,
        todaySalesAmount: stats.todaySalesAmount ?? 0,
        pendingTransfers: stats.pendingTransfers ?? 0,
        lowStockItems: stats.lowStockItems ?? 0,
        pendingPurchases: stats.pendingPurchases ?? 0,
        salesTrend: Array.isArray(stats.salesTrend) ? stats.salesTrend : [],
        topProducts: Array.isArray(stats.topProducts) ? stats.topProducts : [],
        lowStockProducts: Array.isArray(stats.lowStockProducts) ? stats.lowStockProducts : [],
      }));
      setLoading(false);
    } catch (err) {
      console.error('[ManagerDashboard] Stats error:', err);
      setError('Impossible de charger les données. Vérifiez votre connexion.');
      setLoading(false);
      return;
    }
    
    // Étape 2: Charger les données secondaires en arrière-plan
    try {
      const [cashRes, remittancesRes] = await Promise.allSettled([
        apiClient.get('/cash-register/manager-dashboard'),
        apiClient.get('/cash-register/remittances/pending'),
      ]);

      const cashData = cashRes.status === 'fulfilled' ? (cashRes.value.data?.data || cashRes.value.data || null) : null;
      const pendingRemittances = remittancesRes.status === 'fulfilled' ? (remittancesRes.value.data?.data || remittancesRes.value.data || []) : [];

      setData(prev => ({
        ...prev,
        cashData,
        pendingRemittances: Array.isArray(pendingRemittances) ? pendingRemittances : []
      }));
    } catch (err) {
      console.error('[ManagerDashboard] Secondary data error:', err);
    }
  }, []);

  useEffect(() => {
    fetchAllData();
  }, [fetchAllData]);

  const handleReceiveRemittance = async (id) => {
    try {
      await apiClient.post(`/cash-register/remittances/${id}/receive`);
      fetchAllData();
    } catch (error) {
      alert(error.response?.data?.message || 'Erreur');
    }
  };

  const fmt = v => new Intl.NumberFormat('fr-FR').format(v || 0);
  const fmtC = v => fmt(v) + ' FCFA';

  if (loading) {
    return <PageLoader message="Chargement du tableau de bord..." />;
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 p-4">
        <div className="bg-white dark:bg-gray-800 rounded-xl p-6 max-w-md w-full text-center shadow-lg">
          <XCircle className="w-12 h-12 text-red-500 mx-auto mb-4" />
          <h2 className="text-lg font-bold text-gray-800 dark:text-white mb-2">Erreur de chargement</h2>
          <p className="text-gray-600 dark:text-gray-400 mb-4">{error}</p>
          <button
            onClick={fetchAllData}
            className="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium"
          >
            Réessayer
          </button>
        </div>
      </div>
    );
  }

  const maxVentes = Math.max(...data.salesTrend.map(d => d.ventes), 1);

  return (
    <div className="p-4 sm:p-6 space-y-5 bg-gray-50 dark:bg-gray-900 min-h-screen">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Tableau de Bord</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">{tenant?.name}</p>
        </div>
        <button
          onClick={fetchAllData}
          className="inline-flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl text-sm font-medium transition-colors"
        >
          <RefreshCw size={16} />
          Actualiser
        </button>
      </div>

      {/* KPIs principaux - 5 colonnes */}
      <div className="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <KPI title="CA Aujourd'hui" value={fmtC(data.todaySalesAmount)} icon={<DollarSign size={20}/>} color="bg-green-500"/>
        <KPI title="Ventes" value={`${data.todaySales} cmd`} icon={<ShoppingCart size={20}/>} color="bg-blue-500"/>
        <KPI title="Transferts" value={data.pendingTransfers} icon={<Send size={20}/>} color="bg-indigo-500"/>
        <KPI title="Stock Critique" value={data.lowStockItems} icon={<AlertTriangle size={20}/>} color={data.lowStockItems > 0 ? "bg-red-500" : "bg-yellow-500"}/>
        <KPI title="Approvs" value={data.pendingPurchases} icon={<Package size={20}/>} color="bg-purple-500"/>
      </div>

      {/* Graphique + Caisse */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Graphique Ventes 7 jours - Amélioré avec ligne de tendance */}
        <div className="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-base font-bold text-gray-800 dark:text-white flex items-center gap-2">
              <BarChart3 size={18} className="text-blue-500" />
              Ventes des 7 derniers jours
            </h3>
            <div className="flex items-center gap-3">
              <div className="flex items-center gap-1 text-xs text-gray-500">
                <div className="w-3 h-3 bg-blue-500 rounded"></div>
                <span>Montant</span>
              </div>
              <div className="flex items-center gap-1 text-xs text-gray-500">
                <div className="w-3 h-0.5 bg-orange-500"></div>
                <span>Tendance</span>
              </div>
              <span className="text-xs font-semibold text-blue-600">{fmtC(data.salesTrend.reduce((s, d) => s + d.ventes, 0))}</span>
            </div>
          </div>
          
          {/* Graphique avec axes */}
          <div className="relative h-56 mt-2">
            {/* Lignes horizontales de grille + labels Y */}
            <div className="absolute left-0 right-0 top-6 bottom-8 flex flex-col justify-between pointer-events-none">
              {[100, 75, 50, 25, 0].map((pct, i) => (
                <div key={i} className="flex items-center w-full">
                  <span className="text-[9px] text-gray-400 w-12 text-right pr-2">
                    {pct > 0 ? (maxVentes * (pct / 100) / 1000).toFixed(0) + 'k' : '0'}
                  </span>
                  <div className="flex-1 border-t border-gray-200 dark:border-gray-700 border-dashed"></div>
                </div>
              ))}
            </div>
            
            {/* Zone du graphique - avec marge en haut pour les labels */}
            <div className="absolute left-14 right-0 top-6 bottom-8 flex items-end gap-2">
              {/* Barres avec montants au-dessus */}
              {data.salesTrend.map((d, i) => {
                // Limiter la hauteur max à 85% pour laisser de l'espace pour les labels
                const heightPct = maxVentes > 0 ? Math.min((d.ventes / maxVentes) * 85, 85) : 0;
                const displayAmount = d.ventes >= 1000 
                  ? (d.ventes / 1000).toFixed(0) + 'k' 
                  : d.ventes.toFixed(0);
                return (
                  <div key={i} className="flex-1 flex flex-col items-center justify-end h-full relative group">
                    {/* Montant au-dessus de la barre */}
                    <span className="text-[9px] font-semibold text-blue-600 dark:text-blue-400 mb-1 whitespace-nowrap">
                      {displayAmount}
                    </span>
                    {/* Barre */}
                    <div 
                      className="w-full max-w-8 bg-gradient-to-t from-blue-600 to-blue-400 rounded-t-md transition-all duration-300 hover:from-blue-700 hover:to-blue-500 cursor-pointer shadow-sm relative"
                      style={{ height: `${Math.max(4, heightPct)}%` }}
                    >
                      {/* Tooltip au survol */}
                      <div className="absolute -top-16 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-[10px] px-2 py-1.5 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity z-20 whitespace-nowrap shadow-lg">
                        <div className="font-semibold">{fmtC(d.ventes)}</div>
                        <div className="text-gray-300">{d.count} ventes</div>
                      </div>
                    </div>
                  </div>
                );
              })}
              
              {/* Ligne de tendance SVG */}
              <svg className="absolute inset-0 pointer-events-none overflow-visible" style={{ top: '15%', height: '85%' }} preserveAspectRatio="none">
                <defs>
                  <linearGradient id="trendGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stopColor="#f97316" stopOpacity="0.8"/>
                    <stop offset="100%" stopColor="#ea580c" stopOpacity="0.8"/>
                  </linearGradient>
                </defs>
                {data.salesTrend.length > 1 && (
                  <polyline
                    fill="none"
                    stroke="url(#trendGradient)"
                    strokeWidth="2.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    points={data.salesTrend.map((d, i) => {
                      const x = ((i + 0.5) / data.salesTrend.length) * 100;
                      const y = maxVentes > 0 ? 100 - Math.min((d.ventes / maxVentes) * 85, 85) : 100;
                      return `${x}%,${Math.max(5, y)}%`;
                    }).join(' ')}
                  />
                )}
              </svg>
            </div>
            
            {/* Labels X (jours) */}
            <div className="absolute left-14 right-0 bottom-0 flex">
              {data.salesTrend.map((d, i) => (
                <div key={i} className="flex-1 text-center">
                  <span className="text-[10px] font-medium text-gray-600 dark:text-gray-400">{d.date}</span>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Caisse du Jour */}
        {data.cashData && (
          <div className="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl p-5 text-white shadow-lg">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-base font-bold flex items-center gap-2">
                <Wallet size={18} /> Caisse
              </h3>
              <Link to="/cash-register" className="bg-white/20 hover:bg-white/30 px-2 py-1 rounded text-xs flex items-center gap-1">
                Détails <ArrowRight size={12} />
              </Link>
            </div>
            <div className="space-y-3">
              <div className="flex justify-between items-center">
                <span className="text-emerald-100 text-sm">Entrées</span>
                <span className="font-bold">{fmtC(data.cashData.today?.total_in)}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-emerald-100 text-sm">Sorties</span>
                <span className="font-bold">{fmtC(data.cashData.today?.total_out)}</span>
              </div>
              <div className="border-t border-white/20 pt-3 flex justify-between items-center">
                <span className="text-emerald-100 text-sm">Solde Net</span>
                <span className="text-xl font-bold">{fmtC(data.cashData.today?.balance)}</span>
              </div>
              {data.cashData.open_sessions?.length > 0 && (
                <div className="bg-white/10 rounded-lg p-2 mt-2">
                  <span className="text-xs text-emerald-100">{data.cashData.open_sessions.length} session(s) ouverte(s)</span>
                </div>
              )}
            </div>
          </div>
        )}
      </div>

      {/* Top Produits + Stock Critique */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {/* Top Produits Vendus */}
        <div className="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
          <h3 className="text-base font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
            <TrendingUp size={18} className="text-green-500" />
            Top Produits Vendus
          </h3>
          {data.topProducts.length > 0 ? (
            <div className="space-y-3">
              {data.topProducts.map((p, i) => (
                <div key={i} className="flex items-center gap-3">
                  <div className="w-6 h-6 bg-orange-100 dark:bg-orange-900/30 rounded-full flex items-center justify-center text-orange-600 font-bold text-xs">
                    {i + 1}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-gray-800 dark:text-white truncate">{p.name}</p>
                    <p className="text-xs text-gray-500">{p.qty} vendus</p>
                  </div>
                  <span className="text-sm font-bold text-green-600">{fmtC(p.total)}</span>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-gray-500 text-sm text-center py-4">Aucune vente enregistrée</p>
          )}
        </div>

        {/* Stock Critique */}
        <div className="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-base font-bold text-gray-800 dark:text-white flex items-center gap-2">
              <AlertTriangle size={18} className="text-red-500" />
              Stock Critique
            </h3>
            <Link to="/inventory-enriched" className="text-xs text-orange-600 hover:underline flex items-center gap-1">
              Voir tout <ArrowRight size={12} />
            </Link>
          </div>
          {data.lowStockProducts?.length > 0 ? (
            <div className="space-y-2">
              {data.lowStockProducts.map((s, i) => (
                <div key={i} className="flex items-center justify-between p-2 bg-red-50 dark:bg-red-900/20 rounded-lg">
                  <span className="text-sm font-medium text-gray-800 dark:text-white truncate">{s.name}</span>
                  <span className={`text-sm font-bold ${s.quantity <= 0 ? 'text-red-600' : 'text-orange-600'}`}>
                    {s.quantity} {s.unit}
                  </span>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-4">
              <CheckCircle size={32} className="mx-auto text-green-500 mb-2" />
              <p className="text-gray-500 text-sm">Tous les stocks sont suffisants</p>
            </div>
          )}
        </div>
      </div>

      {/* Remises en attente */}
      {data.pendingRemittances.length > 0 && (
        <div className="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-300 dark:border-yellow-700 rounded-xl p-4">
          <h3 className="font-bold text-yellow-800 dark:text-yellow-400 mb-3 flex items-center gap-2 text-sm">
            <Send size={16} /> {data.pendingRemittances.length} Remise(s) en Attente de Réception
          </h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            {data.pendingRemittances.slice(0, 3).map((rem) => (
              <div key={rem.id} className="flex items-center justify-between gap-2 bg-white dark:bg-gray-800 p-3 rounded-lg">
                <div className="min-w-0">
                  <p className="font-bold text-gray-900 dark:text-white">{fmtC(rem.amount)}</p>
                  <p className="text-xs text-gray-500 truncate">De {rem.from_user?.name}</p>
                </div>
                <button
                  onClick={() => handleReceiveRemittance(rem.id)}
                  className="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-xs font-medium flex items-center gap-1"
                >
                  <CheckCircle size={14} /> OK
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Actions rapides */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <Link to="/transfers" className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 hover:shadow-md transition flex items-center gap-3">
          <div className="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
            <Send size={20} className="text-blue-600" />
          </div>
          <div>
            <p className="font-medium text-gray-900 dark:text-white text-sm">Transferts</p>
            <p className="text-xs text-gray-500">{data.pendingTransfers} en attente</p>
          </div>
        </Link>
        <Link to="/pos/manager-orders" className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 hover:shadow-md transition flex items-center gap-3">
          <div className="w-10 h-10 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
            <ShoppingCart size={20} className="text-green-600" />
          </div>
          <div>
            <p className="font-medium text-gray-900 dark:text-white text-sm">Commandes</p>
            <p className="text-xs text-gray-500">Historique ventes</p>
          </div>
        </Link>
        <Link to="/purchases" className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 hover:shadow-md transition flex items-center gap-3">
          <div className="w-10 h-10 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center">
            <Package size={20} className="text-purple-600" />
          </div>
          <div>
            <p className="font-medium text-gray-900 dark:text-white text-sm">Achats</p>
            <p className="text-xs text-gray-500">{data.pendingPurchases} en cours</p>
          </div>
        </Link>
        <Link to="/cash-register" className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 hover:shadow-md transition flex items-center gap-3">
          <div className="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center">
            <Wallet size={20} className="text-emerald-600" />
          </div>
          <div>
            <p className="font-medium text-gray-900 dark:text-white text-sm">Caisse</p>
            <p className="text-xs text-gray-500">Gestion</p>
          </div>
        </Link>
      </div>
    </div>
  );
}
