import React, { useState, useEffect, useCallback, memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Package, TrendingUp, DollarSign, BarChart3, ArrowUpRight, Warehouse, Filter, Calendar, Users, ShoppingCart, AlertTriangle, Bell, XCircle } from 'lucide-react';
import { useTenantStore } from '../stores/tenantStore';
import apiClient from '../services/apiClient';
import { PageLoader } from '../components/LoadingFallback';

export default function DashboardCompletePage() {
  const navigate = useNavigate();
  const { token, tenant } = useTenantStore();
  const [loading, setLoading] = useState(true);
  const [dateFilter, setDateFilter] = useState('month');
  const [serverFilter, setServerFilter] = useState('all');
  const [stockAlerts, setStockAlerts] = useState({ alerts: [], out_of_stock_count: 0, low_stock_count: 0 });
  const [suggestedOrders, setSuggestedOrders] = useState({ products_to_order: [], urgent_count: 0, total_amount: 0 });
  const [data, setData] = useState({
    ebeGross: 0, ebeNet: 0, totalSales: 0, totalExpenses: 0, benefice: 0,
    stockTotal: 0, stockValue: 0, pendingTransfers: 0, warehouseCount: 0,
    salesByServer: [], salesTrend: [], topProducts: [], servers: []
  });

  const [error, setError] = useState(null);

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError(null);
    
    try {
      // Étape 1: Charger les données essentielles d'abord (stocks, orders, warehouses)
      const [stocksRes, posOrdersRes, warehousesRes] = await Promise.allSettled([
        apiClient.get('/stocks?per_page=200'),
        apiClient.get('/pos/orders?payment_status=confirmed&limit=500'),
        apiClient.get('/warehouses'),
      ]);
      
      // Afficher immédiatement les données de base
      const extractData = (result, defaultValue = []) => {
        if (result.status === 'fulfilled') {
          return result.value.data?.data || result.value.data || defaultValue;
        }
        return defaultValue;
      };
      
      const stocks = extractData(stocksRes);
      const posOrders = extractData(posOrdersRes);
      const warehouses = extractData(warehousesRes);
      
      // Calculer et afficher immédiatement les KPIs de base
      const stockTotal = stocks.reduce((s, i) => s + (parseFloat(i.quantity) || 0), 0);
      const stockValue = stocks.reduce((s, i) => s + ((parseFloat(i.quantity) || 0) * (parseFloat(i.cost_average) || 0)), 0);
      const totalSales = posOrders.reduce((sum, o) => sum + parseFloat(o.total || 0), 0);
      
      setData(prev => ({
        ...prev,
        stockTotal,
        stockValue,
        totalSales,
        warehouseCount: warehouses.length,
      }));
      setLoading(false);
      
      // Étape 2: Charger les données secondaires en arrière-plan
      const [transfersRes, expensesRes, cashRes, alertsRes, suggestedRes] = await Promise.allSettled([
        apiClient.get('/transfers?status=pending'),
        apiClient.get('/expenses?per_page=200'),
        apiClient.get('/cash-movements?type=in&per_page=500'),
        apiClient.get('/stocks/alerts?warehouse=Magasin de détail'),
        apiClient.get('/stocks/suggested-orders'),
      ]);
      
      // Mettre à jour les alertes
      const alertsData = alertsRes.status === 'fulfilled' ? alertsRes.value.data : { alerts: [], out_of_stock_count: 0, low_stock_count: 0 };
      const suggestedData = suggestedRes.status === 'fulfilled' ? suggestedRes.value.data : { products_to_order: [], urgent_count: 0, total_amount: 0 };
      
      setStockAlerts(alertsData || { alerts: [], out_of_stock_count: 0, low_stock_count: 0 });
      setSuggestedOrders(suggestedData || { products_to_order: [], urgent_count: 0, total_amount: 0 });

      const transfers = extractData(transfersRes);
      const expenses = extractData(expensesRes);
      const cashMovements = extractData(cashRes);

      // Calculer période de filtrage
      const now = new Date();
      let startDate = new Date();
      if (dateFilter === 'week') startDate.setDate(now.getDate() - 7);
      else if (dateFilter === 'month') startDate.setMonth(now.getMonth() - 1);
      else if (dateFilter === 'quarter') startDate.setMonth(now.getMonth() - 3);
      else if (dateFilter === 'year') startDate.setFullYear(now.getFullYear() - 1);

      // Filtrer les commandes POS par période
      let filteredOrders = posOrders.filter(o => new Date(o.created_at || o.validated_at) >= startDate);
      
      // Extraire la liste des serveurs AVANT le filtrage par serveur
      const serverSet = new Set();
      posOrders.forEach(o => {
        // Laravel snake_case: created_by_user
        const serverName = o.created_by_user?.name || o.createdByUser?.name || o.server_name || 'Serveur';
        serverSet.add(serverName);
      });
      const servers = Array.from(serverSet).sort();

      // Filtrer par serveur si sélectionné
      if (serverFilter !== 'all') {
        filteredOrders = filteredOrders.filter(o => {
          const name = o.created_by_user?.name || o.createdByUser?.name || o.server_name;
          return name === serverFilter;
        });
      }

      // Filtrer les dépenses par période
      const filteredExpenses = expenses.filter(e => new Date(e.created_at) >= startDate);

      // CALCULS FINANCIERS RÉELS
      // CA = somme des totaux des commandes POS validées (filtrées)
      const filteredTotalSales = filteredOrders.reduce((sum, o) => sum + parseFloat(o.total || 0), 0);
      
      // Charges Variables (CMV) = somme(CMP × Quantité vendue) pour chaque item
      // Calculé depuis les items des commandes POS
      let chargesVariables = 0;
      filteredOrders.forEach(o => {
        (o.items || []).forEach(item => {
          const qty = item.quantity_ordered || item.quantity_served || item.quantity || 1;
          const cmp = parseFloat(item.unit_cost || 0);
          chargesVariables += qty * cmp;
        });
      });
      
      // Charges Fixes = somme des dépenses enregistrées (loyer, salaires, etc.)
      const chargesFixes = filteredExpenses.reduce((sum, e) => sum + parseFloat(e.amount || 0), 0);
      
      // Total Charges = Charges Variables + Charges Fixes
      const totalExpenses = chargesVariables + chargesFixes;
      
      // EBE Brut = CA - Charges Variables (Marge Brute)
      const ebeGross = filteredTotalSales - chargesVariables;
      
      // EBE Net = EBE Brut - Charges Fixes
      const ebeNet = ebeGross - chargesFixes;
      
      // Bénéfice Net = EBE Net - 15% (estimation impôts)
      const benefice = ebeNet > 0 ? ebeNet * 0.85 : ebeNet;

      // VENTES PAR SERVEUR (toutes périodes confondues pour le graphique)
      const serverMap = {};
      filteredOrders.forEach(o => {
        const name = o.created_by_user?.name || o.createdByUser?.name || o.server_name || 'Serveur';
        if (!serverMap[name]) serverMap[name] = { name, total: 0, count: 0 };
        serverMap[name].total += parseFloat(o.total || 0);
        serverMap[name].count++;
      });
      const salesByServer = Object.values(serverMap).sort((a, b) => b.total - a.total).slice(0, 8);

      // TENDANCE 7 JOURS (toujours sur 7 jours peu importe le filtre)
      const trend = [];
      for (let i = 6; i >= 0; i--) {
        const d = new Date(); 
        d.setDate(d.getDate() - i);
        const ds = d.toISOString().split('T')[0];
        const label = d.toLocaleDateString('fr-FR', { weekday: 'short' });
        
        // Ventes du jour
        const daySales = posOrders.filter(o => {
          const orderDate = (o.created_at || o.validated_at || '').split('T')[0];
          return orderDate === ds;
        });
        const dayRevenue = daySales.reduce((sum, o) => sum + parseFloat(o.total || 0), 0);
        
        // Charges Variables du jour (CMV = CMP × Qté vendue)
        let dayChargesVar = 0;
        daySales.forEach(o => {
          (o.items || []).forEach(item => {
            const qty = item.quantity_ordered || item.quantity_served || item.quantity || 1;
            const cmp = parseFloat(item.unit_cost || 0);
            dayChargesVar += qty * cmp;
          });
        });
        
        trend.push({ 
          date: label, 
          ventes: dayRevenue, 
          charges: dayChargesVar, // Charges Variables (CMV)
          benefice: dayRevenue - dayChargesVar // Bénéfice Brut
        });
      }

      // Top produits en stock - Exclure les produits sans nom
      const topProducts = stocks
        .filter(s => parseFloat(s.quantity) > 0 && s.product?.name && s.product.name !== 'N/A')
        .sort((a, b) => (parseFloat(b.quantity) * parseFloat(b.cost_average || 0)) - (parseFloat(a.quantity) * parseFloat(a.cost_average || 0)))
        .slice(0, 10)
        .map(s => ({
          name: s.product.name, 
          qty: parseFloat(s.quantity) || 0, 
          value: (parseFloat(s.quantity) || 0) * (parseFloat(s.cost_average) || 0), 
          cmp: parseFloat(s.cost_average) || 0
        }));

      // Mise à jour finale avec toutes les données calculées
      setData(prev => ({
        ...prev,
        ebeGross, ebeNet, totalSales: filteredTotalSales, totalExpenses, benefice,
        pendingTransfers: transfers.length,
        salesByServer, salesTrend: trend, topProducts, servers
      }));
    } catch (e) { 
      console.error('Erreur chargement dashboard:', e);
      setLoading(false);
    }
  }, [dateFilter, serverFilter]);

  useEffect(() => { if (token) fetchData(); else navigate('/login'); }, [token, fetchData, navigate]);

  const fmt = v => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(v || 0);
  const fmtC = v => fmt(v) + ' FCFA';

  if (loading) return <div className="min-h-screen flex items-center justify-center"><div className="w-12 h-12 border-4 border-orange-500 border-t-transparent rounded-full animate-spin"></div></div>;

  const maxVentes = Math.max(...data.salesTrend.map(x => x.ventes), 1);

  return (
    <div className="p-6 space-y-6 bg-gray-50 dark:bg-gray-900 min-h-screen">
      {/* Header avec filtres */}
      <div className="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Tableau de Bord</h1>
          <p className="text-base text-gray-500 dark:text-gray-400">{tenant?.name}</p>
        </div>
        <div className="flex flex-wrap gap-3">
          <select 
            value={dateFilter} 
            onChange={e => setDateFilter(e.target.value)}
            className="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-orange-500"
          >
            <option value="week">7 derniers jours</option>
            <option value="month">Ce mois</option>
            <option value="quarter">Ce trimestre</option>
            <option value="year">Cette année</option>
          </select>
          <select 
            value={serverFilter} 
            onChange={e => setServerFilter(e.target.value)}
            className="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-sm font-medium focus:ring-2 focus:ring-orange-500"
          >
            <option value="all">Tous les serveurs</option>
            {data.servers.map(s => <option key={s} value={s}>{s}</option>)}
          </select>
        </div>
      </div>

      {/* Alertes Stock - Affiché uniquement s'il y a des alertes */}
      {(stockAlerts.out_of_stock_count > 0 || stockAlerts.low_stock_count > 0) && (
        <div className="bg-gradient-to-r from-red-500 to-orange-500 rounded-xl p-4 text-white shadow-lg">
          <div className="flex items-center justify-between mb-3">
            <div className="flex items-center gap-2">
              <Bell className="animate-pulse" size={24} />
              <h3 className="font-bold text-lg">⚠️ Alertes Stock</h3>
            </div>
            <span className="bg-white/20 px-3 py-1 rounded-full text-sm font-medium">
              {stockAlerts.out_of_stock_count + stockAlerts.low_stock_count} alertes
            </span>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            {stockAlerts.out_of_stock_count > 0 && (
              <div className="bg-white/10 rounded-lg p-3">
                <div className="flex items-center gap-2 mb-2">
                  <AlertTriangle size={18} />
                  <span className="font-semibold">Ruptures de stock ({stockAlerts.out_of_stock_count})</span>
                </div>
                <div className="space-y-1 max-h-24 overflow-y-auto text-sm">
                  {stockAlerts.alerts.filter(a => a.alert_type === 'out_of_stock').slice(0, 5).map((a, i) => (
                    <div key={i} className="flex justify-between">
                      <span>{a.name}</span>
                      <span className="font-bold">0 en stock</span>
                    </div>
                  ))}
                </div>
              </div>
            )}
            {stockAlerts.low_stock_count > 0 && (
              <div className="bg-white/10 rounded-lg p-3">
                <div className="flex items-center gap-2 mb-2">
                  <Package size={18} />
                  <span className="font-semibold">Stock bas ({stockAlerts.low_stock_count})</span>
                </div>
                <div className="space-y-1 max-h-24 overflow-y-auto text-sm">
                  {stockAlerts.alerts.filter(a => a.alert_type === 'low_stock').slice(0, 5).map((a, i) => (
                    <div key={i} className="flex justify-between">
                      <span>{a.name}</span>
                      <span className="font-bold">{a.available} restants</span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
          {suggestedOrders.urgent_count > 0 && (
            <div className="mt-3 pt-3 border-t border-white/20 flex items-center justify-between">
              <span className="text-sm">
                <strong>{suggestedOrders.urgent_count}</strong> produits à commander en urgence • 
                Coût estimé: <strong>{fmtC(suggestedOrders.total_amount)}</strong>
              </span>
              <button 
                onClick={() => navigate('/purchases')}
                className="bg-white text-orange-600 px-4 py-1.5 rounded-lg text-sm font-semibold hover:bg-orange-50 transition"
              >
                Créer commande
              </button>
            </div>
          )}
        </div>
      )}

      {/* KPIs financiers */}
      <div className="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <KPI title="Chiffre d'Affaires" value={fmtC(data.totalSales)} icon={<ShoppingCart size={22}/>} color="bg-blue-500"/>
        <KPI title="Charges Var. (CMV)" value={fmtC(data.ebeGross > 0 ? data.totalSales - data.ebeGross : 0)} icon={<TrendingUp size={22}/>} color="bg-red-500"/>
        <KPI title="Marge Brute" value={fmtC(data.ebeGross)} icon={<BarChart3 size={22}/>} color="bg-green-500"/>
        <KPI title="EBE Net" value={fmtC(data.ebeNet)} icon={<DollarSign size={22}/>} color="bg-emerald-600"/>
        <KPI title="Bénéfice Net" value={fmtC(data.benefice)} icon={<ArrowUpRight size={22}/>} color="bg-teal-500"/>
      </div>

      {/* Stats Stock compactes - 2x2 */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <MiniStat title="Stock Total" value={fmt(data.stockTotal)} icon={<Package size={18}/>} color="text-purple-600" bg="bg-purple-50"/>
        <MiniStat title="Valeur Stock" value={fmtC(data.stockValue)} icon={<DollarSign size={18}/>} color="text-blue-600" bg="bg-blue-50"/>
        <MiniStat title="Transferts" value={data.pendingTransfers} icon={<TrendingUp size={18}/>} color="text-yellow-600" bg="bg-yellow-50"/>
        <MiniStat title="Entrepôts" value={data.warehouseCount} icon={<Warehouse size={18}/>} color="text-indigo-600" bg="bg-indigo-50"/>
      </div>

      {/* Graphiques - Courbes d'évolution */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Courbes CA / Charges / Bénéfice */}
        <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-200 dark:border-gray-700 shadow-sm">
          <h3 className="text-lg font-bold text-gray-800 dark:text-white mb-2">Évolution Financière (7 jours)</h3>
          <div className="flex gap-4 mb-4 text-xs">
            <span className="flex items-center gap-1"><span className="w-3 h-3 bg-blue-500 rounded-full"></span> CA</span>
            <span className="flex items-center gap-1"><span className="w-3 h-3 bg-red-500 rounded-full"></span> CMV (Charges Var.)</span>
            <span className="flex items-center gap-1"><span className="w-3 h-3 bg-green-500 rounded-full"></span> Marge Brute</span>
          </div>
          <div className="relative h-44">
            {/* Grille de fond */}
            <div className="absolute inset-0 flex flex-col justify-between">
              {[0,1,2,3,4].map(i => <div key={i} className="border-b border-gray-100 dark:border-gray-700"></div>)}
            </div>
            {/* Courbes SVG */}
            <svg className="w-full h-full" viewBox="0 0 700 160" preserveAspectRatio="none">
              {(() => {
                const maxVal = Math.max(...data.salesTrend.flatMap(d => [d.ventes, d.charges, Math.abs(d.benefice)]), 1);
                const getY = v => 150 - (Math.max(0, v) / maxVal) * 140;
                const getX = i => i * 100 + 50;
                const caPath = data.salesTrend.map((d, i) => `${i === 0 ? 'M' : 'L'}${getX(i)},${getY(d.ventes)}`).join(' ');
                const chPath = data.salesTrend.map((d, i) => `${i === 0 ? 'M' : 'L'}${getX(i)},${getY(d.charges)}`).join(' ');
                const bnPath = data.salesTrend.map((d, i) => `${i === 0 ? 'M' : 'L'}${getX(i)},${getY(d.benefice)}`).join(' ');
                return (
                  <>
                    <path d={caPath} fill="none" stroke="#3B82F6" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"/>
                    <path d={chPath} fill="none" stroke="#EF4444" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"/>
                    <path d={bnPath} fill="none" stroke="#22C55E" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"/>
                    {data.salesTrend.map((d, i) => (
                      <g key={i}>
                        <circle cx={getX(i)} cy={getY(d.ventes)} r="4" fill="#3B82F6"/>
                        <circle cx={getX(i)} cy={getY(d.charges)} r="4" fill="#EF4444"/>
                        <circle cx={getX(i)} cy={getY(d.benefice)} r="4" fill="#22C55E"/>
                      </g>
                    ))}
                  </>
                );
              })()}
            </svg>
            {/* Labels jours */}
            <div className="absolute bottom-0 left-0 right-0 flex justify-between px-2 text-xs text-gray-500">
              {data.salesTrend.map((d, i) => <span key={i}>{d.date}</span>)}
            </div>
          </div>
        </div>

        {/* Ventes par serveur - Compact */}
        <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-200 dark:border-gray-700 shadow-sm">
          <h3 className="text-lg font-bold text-gray-800 dark:text-white mb-4">Ventes par Serveur</h3>
          {data.salesByServer.length > 0 ? (
            <div className="space-y-3 max-h-48 overflow-y-auto">
              {data.salesByServer.map((s, i) => (
                <div key={i} className="flex items-center gap-2">
                  <div className="w-6 h-6 bg-orange-100 dark:bg-orange-900/30 rounded-full flex items-center justify-center text-orange-600 font-bold text-xs">
                    {i + 1}
                  </div>
                  <div className="w-24 text-sm font-medium text-gray-700 dark:text-gray-300 truncate">{s.name}</div>
                  <div className="flex-1 h-6 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div 
                      className="h-full bg-gradient-to-r from-orange-500 to-orange-400 rounded-full flex items-center justify-end pr-2" 
                      style={{width: `${Math.max(20, (s.total / (data.salesByServer[0]?.total || 1)) * 100)}%`}}
                    >
                      <span className="text-[10px] font-bold text-white">{s.count}</span>
                    </div>
                  </div>
                  <div className="w-28 text-sm font-bold text-right text-gray-800 dark:text-white">{fmtC(s.total)}</div>
                </div>
              ))}
            </div>
          ) : (
            <div className="flex items-center justify-center h-40 text-gray-500">
              <p className="text-base">Aucune vente enregistrée</p>
            </div>
          )}
        </div>
      </div>

      {/* Ventes Récentes + Stock - 2 colonnes */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {/* Ventes Récentes */}
        <div className="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-sm">
          <div className="flex items-center justify-between mb-3">
            <h3 className="text-base font-bold text-gray-800 dark:text-white flex items-center gap-2">
              <ShoppingCart size={18} className="text-green-500" />
              Ventes Récentes
            </h3>
            <a href="/pos/manager-orders" className="text-xs text-orange-600 hover:underline">Voir tout →</a>
          </div>
          <div className="space-y-2 max-h-48 overflow-y-auto">
            {data.salesByServer.length > 0 ? data.salesByServer.slice(0, 5).map((s, i) => (
              <div key={i} className="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <div className="flex items-center gap-2">
                  <div className="w-8 h-8 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center">
                    <Users size={14} className="text-green-600" />
                  </div>
                  <div>
                    <p className="text-sm font-medium text-gray-800 dark:text-white">{s.name}</p>
                    <p className="text-xs text-gray-500">{s.count} ventes</p>
                  </div>
                </div>
                <span className="text-sm font-bold text-green-600">{fmtC(s.total)}</span>
              </div>
            )) : (
              <p className="text-gray-500 text-sm text-center py-4">Aucune vente sur la période</p>
            )}
          </div>
        </div>

        {/* Stock Actuel - Simplifié */}
        <div className="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-sm">
          <div className="flex items-center justify-between mb-3">
            <h3 className="text-base font-bold text-gray-800 dark:text-white flex items-center gap-2">
              <Package size={18} className="text-purple-500" />
              Stock Actuel
            </h3>
            <a href="/inventory-enriched" className="text-xs text-orange-600 hover:underline">Voir tout →</a>
          </div>
          <div className="space-y-2 max-h-48 overflow-y-auto">
            {data.topProducts.slice(0, 6).map((p, i) => (
              <div key={i} className="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <span className="text-sm font-medium text-gray-800 dark:text-white truncate flex-1">{p.name}</span>
                <div className="text-right ml-2">
                  <p className="text-sm font-bold text-gray-900 dark:text-white">{p.qty}</p>
                  <p className="text-xs text-gray-500">{fmtC(p.value)}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

const KPI = memo(({ title, value, icon, color }) => (
  <div className="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition">
    <div className="flex items-center gap-3">
      <div className={`${color} w-12 h-12 rounded-lg flex items-center justify-center text-white shadow`}>{icon}</div>
      <div className="flex-1 min-w-0">
        <p className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">{title}</p>
        <p className="text-lg font-bold text-gray-900 dark:text-white truncate">{value}</p>
      </div>
    </div>
  </div>
));

const MiniStat = memo(({ title, value, icon, color, bg }) => (
  <div className={`${bg} dark:bg-gray-800 rounded-xl p-3 border border-gray-100 dark:border-gray-700 flex items-center gap-3`}>
    <div className={`${color}`}>{icon}</div>
    <div>
      <p className="text-xs text-gray-500 dark:text-gray-400">{title}</p>
      <p className="text-base font-bold text-gray-900 dark:text-white">{value}</p>
    </div>
  </div>
));
