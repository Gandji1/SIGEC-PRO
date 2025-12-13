import React, { useState, useEffect, useCallback } from 'react';
import { useTenantStore } from '../stores/tenantStore';
import apiClient from '../services/apiClient';
import { LineChart, Line, AreaChart, Area, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';
import { TrendingUp, TrendingDown, DollarSign, Package, Warehouse, ArrowUpRight, ArrowDownRight, RefreshCw, Filter, Calendar, Users, ShoppingCart } from 'lucide-react';

const COLORS = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'];

export default function TenantDashboardPage() {
  const { user, tenant } = useTenantStore();
  const [loading, setLoading] = useState(true);
  const [dateRange, setDateRange] = useState('month');
  const [data, setData] = useState({
    ebeGross: 0, // Excédent Brut d'Exploitation
    ebeNet: 0,   // Excédent Net
    totalSales: 0,
    totalExpenses: 0,
    grossProfit: 0,
    stockTotal: 0,
    stockValue: 0,
    pendingTransfers: 0,
    warehouseCount: 0,
    salesByServer: [],
    salesTrend: [],
    expensesTrend: [],
    profitTrend: [],
    topProducts: [],
  });

  const fetchDashboardData = useCallback(async () => {
    setLoading(true);
    try {
      const [
        accountingRes,
        stocksRes,
        salesRes,
        transfersRes,
        warehousesRes,
      ] = await Promise.all([
        apiClient.get(`/accounting/summary?period=${dateRange}`).catch(() => ({ data: {} })),
        apiClient.get('/stocks/summary').catch(() => ({ data: {} })),
        apiClient.get('/reports/sales-journal').catch(() => ({ data: { data: [] } })),
        apiClient.get('/transfers?status=pending').catch(() => ({ data: { data: [] } })),
        apiClient.get('/warehouses').catch(() => ({ data: { data: [] } })),
      ]);

      const accounting = accountingRes.data?.data || accountingRes.data || {};
      const stocks = stocksRes.data?.data || stocksRes.data || {};
      const sales = salesRes.data?.data || [];
      const transfers = transfersRes.data?.data || [];
      const warehouses = warehousesRes.data?.data || [];

      // Calcul EBE
      const totalRevenue = accounting.total_revenue || accounting.totalRevenue || 0;
      const totalCogs = accounting.total_cogs || accounting.totalCogs || 0;
      const totalExpenses = accounting.total_expenses || accounting.totalExpenses || 0;
      const grossProfit = totalRevenue - totalCogs;
      const ebeGross = grossProfit;
      const ebeNet = grossProfit - totalExpenses;

      // Ventes par serveur
      const serverSales = {};
      sales.forEach(sale => {
        const serverName = sale.user?.name || sale.server_name || 'Inconnu';
        if (!serverSales[serverName]) {
          serverSales[serverName] = { name: serverName, total: 0, count: 0 };
        }
        serverSales[serverName].total += parseFloat(sale.total || 0);
        serverSales[serverName].count += 1;
      });

      // Tendances (derniers 7 jours)
      const last7Days = [];
      for (let i = 6; i >= 0; i--) {
        const date = new Date();
        date.setDate(date.getDate() - i);
        const dateStr = date.toISOString().split('T')[0];
        const dayLabel = date.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric' });
        
        const daySales = sales.filter(s => s.created_at?.startsWith(dateStr) || s.completed_at?.startsWith(dateStr));
        const dayRevenue = daySales.reduce((sum, s) => sum + parseFloat(s.total || 0), 0);
        const dayCogs = daySales.reduce((sum, s) => sum + parseFloat(s.cogs || s.total * 0.6 || 0), 0);
        
        last7Days.push({
          date: dayLabel,
          ventes: dayRevenue,
          charges: dayCogs,
          benefice: dayRevenue - dayCogs,
        });
      }

      setData({
        ebeGross,
        ebeNet,
        totalSales: totalRevenue,
        totalExpenses,
        grossProfit,
        stockTotal: stocks.total_quantity || stocks.totalQuantity || 0,
        stockValue: stocks.total_value || stocks.totalValue || 0,
        pendingTransfers: transfers.length || 0,
        warehouseCount: warehouses.length || 0,
        salesByServer: Object.values(serverSales).sort((a, b) => b.total - a.total).slice(0, 10),
        salesTrend: last7Days,
        topProducts: (stocks.by_product || []).slice(0, 5),
      });
    } catch (error) {
      console.error('Dashboard error:', error);
    } finally {
      setLoading(false);
    }
  }, [dateRange]);

  useEffect(() => {
    fetchDashboardData();
  }, [fetchDashboardData]);

  const formatCurrency = (val) => new Intl.NumberFormat('fr-FR').format(val || 0) + ' FCFA';
  const formatCompact = (val) => {
    if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
    if (val >= 1000) return (val / 1000).toFixed(0) + 'K';
    return val?.toFixed(0) || '0';
  };

  if (loading) {
    return (
      <div className="p-4 space-y-4 animate-pulse">
        <div className="h-8 bg-gray-200 rounded w-48"></div>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          {[1,2,3,4].map(i => <div key={i} className="h-20 bg-gray-200 rounded-xl"></div>)}
        </div>
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div className="h-64 bg-gray-200 rounded-xl"></div>
          <div className="h-64 bg-gray-200 rounded-xl"></div>
        </div>
      </div>
    );
  }

  return (
    <div className="p-4 space-y-4">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-gray-900">Tableau de Bord</h1>
          <p className="text-sm text-gray-500">Performance de {tenant?.name || 'votre entreprise'}</p>
        </div>
        <div className="flex items-center gap-2">
          <select
            value={dateRange}
            onChange={(e) => setDateRange(e.target.value)}
            className="text-sm border rounded-lg px-3 py-1.5 bg-white"
          >
            <option value="week">Cette semaine</option>
            <option value="month">Ce mois</option>
            <option value="quarter">Ce trimestre</option>
            <option value="year">Cette année</option>
          </select>
          <button
            onClick={fetchDashboardData}
            disabled={loading}
            className="p-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600"
          >
            <RefreshCw size={16} className={loading ? 'animate-spin' : ''} />
          </button>
        </div>
      </div>

      {/* KPIs Financiers - Compacts 2x2 */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        {/* EBE Brut */}
        <div className="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-3 text-white">
          <div className="flex items-center justify-between">
            <span className="text-xs opacity-80">EBE Brut</span>
            <TrendingUp size={16} />
          </div>
          <p className="text-lg font-bold mt-1">{formatCompact(data.ebeGross)}</p>
          <p className="text-[10px] opacity-70">Excédent Brut d'Exploitation</p>
        </div>

        {/* EBE Net */}
        <div className={`bg-gradient-to-br ${data.ebeNet >= 0 ? 'from-green-500 to-green-600' : 'from-red-500 to-red-600'} rounded-xl p-3 text-white`}>
          <div className="flex items-center justify-between">
            <span className="text-xs opacity-80">EBE Net</span>
            {data.ebeNet >= 0 ? <ArrowUpRight size={16} /> : <ArrowDownRight size={16} />}
          </div>
          <p className="text-lg font-bold mt-1">{formatCompact(data.ebeNet)}</p>
          <p className="text-[10px] opacity-70">Après charges variables</p>
        </div>

        {/* Stock Total */}
        <div className="bg-white rounded-xl p-3 border shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs text-gray-500">Stock Total</span>
            <Package size={16} className="text-purple-500" />
          </div>
          <p className="text-lg font-bold text-gray-900 mt-1">{data.stockTotal.toLocaleString()}</p>
          <p className="text-[10px] text-gray-400">unités en stock</p>
        </div>

        {/* Valeur Stock */}
        <div className="bg-white rounded-xl p-3 border shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs text-gray-500">Valeur Stock</span>
            <DollarSign size={16} className="text-amber-500" />
          </div>
          <p className="text-lg font-bold text-gray-900 mt-1">{formatCompact(data.stockValue)}</p>
          <p className="text-[10px] text-gray-400">FCFA</p>
        </div>
      </div>

      {/* Mini Stats Row */}
      <div className="grid grid-cols-2 gap-3">
        <div className="bg-white rounded-xl p-3 border shadow-sm flex items-center gap-3">
          <div className="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
            <Warehouse size={18} className="text-indigo-600" />
          </div>
          <div>
            <p className="text-xs text-gray-500">Entrepôts</p>
            <p className="text-lg font-bold">{data.warehouseCount}</p>
          </div>
        </div>
        <div className="bg-white rounded-xl p-3 border shadow-sm flex items-center gap-3">
          <div className="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
            <ShoppingCart size={18} className="text-orange-600" />
          </div>
          <div>
            <p className="text-xs text-gray-500">Transferts en attente</p>
            <p className="text-lg font-bold">{data.pendingTransfers}</p>
          </div>
        </div>
      </div>

      {/* Graphiques */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {/* Courbe Ventes/Charges/Bénéfice */}
        <div className="bg-white rounded-xl p-4 border shadow-sm">
          <h3 className="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <TrendingUp size={16} className="text-blue-500" />
            Évolution (7 jours)
          </h3>
          <ResponsiveContainer width="100%" height={200}>
            <AreaChart data={data.salesTrend}>
              <defs>
                <linearGradient id="colorVentes" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#3B82F6" stopOpacity={0.3}/>
                  <stop offset="95%" stopColor="#3B82F6" stopOpacity={0}/>
                </linearGradient>
                <linearGradient id="colorBenefice" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#10B981" stopOpacity={0.3}/>
                  <stop offset="95%" stopColor="#10B981" stopOpacity={0}/>
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" stroke="#E5E7EB" />
              <XAxis dataKey="date" tick={{ fontSize: 10 }} stroke="#9CA3AF" />
              <YAxis tick={{ fontSize: 10 }} stroke="#9CA3AF" tickFormatter={formatCompact} />
              <Tooltip 
                formatter={(val) => formatCurrency(val)}
                contentStyle={{ fontSize: 11, borderRadius: 8 }}
              />
              <Area type="monotone" dataKey="ventes" stroke="#3B82F6" fill="url(#colorVentes)" strokeWidth={2} />
              <Area type="monotone" dataKey="benefice" stroke="#10B981" fill="url(#colorBenefice)" strokeWidth={2} />
              <Line type="monotone" dataKey="charges" stroke="#EF4444" strokeWidth={1.5} strokeDasharray="4 4" dot={false} />
            </AreaChart>
          </ResponsiveContainer>
          <div className="flex justify-center gap-4 mt-2 text-xs">
            <span className="flex items-center gap-1"><span className="w-3 h-3 bg-blue-500 rounded"></span> Ventes</span>
            <span className="flex items-center gap-1"><span className="w-3 h-3 bg-green-500 rounded"></span> Bénéfice</span>
            <span className="flex items-center gap-1"><span className="w-3 h-0.5 bg-red-500"></span> Charges</span>
          </div>
        </div>

        {/* Ventes par Serveur */}
        <div className="bg-white rounded-xl p-4 border shadow-sm">
          <h3 className="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <Users size={16} className="text-purple-500" />
            Ventes par Serveur
          </h3>
          {data.salesByServer.length > 0 ? (
            <div className="space-y-2 max-h-[200px] overflow-y-auto">
              {data.salesByServer.map((server, idx) => {
                const maxTotal = Math.max(...data.salesByServer.map(s => s.total));
                const percentage = maxTotal > 0 ? (server.total / maxTotal) * 100 : 0;
                return (
                  <div key={idx} className="flex items-center gap-2">
                    <div className="w-20 text-xs text-gray-600 truncate">{server.name}</div>
                    <div className="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden">
                      <div 
                        className="h-full bg-gradient-to-r from-purple-500 to-indigo-500 rounded-full flex items-center justify-end pr-2"
                        style={{ width: `${Math.max(percentage, 10)}%` }}
                      >
                        <span className="text-[10px] text-white font-medium">{server.count}</span>
                      </div>
                    </div>
                    <div className="w-20 text-xs text-right font-medium text-gray-700">
                      {formatCompact(server.total)}
                    </div>
                  </div>
                );
              })}
            </div>
          ) : (
            <div className="h-[200px] flex items-center justify-center text-gray-400 text-sm">
              Aucune donnée de vente
            </div>
          )}
        </div>
      </div>

      {/* Stock Actuel - Compact */}
      <div className="bg-white rounded-xl p-4 border shadow-sm">
        <h3 className="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
          <Package size={16} className="text-amber-500" />
          Stock Actuel (Top 5)
        </h3>
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b">
                <th className="text-left py-2 px-2 font-medium text-gray-500">Produit</th>
                <th className="text-right py-2 px-2 font-medium text-gray-500">Qté</th>
                <th className="text-right py-2 px-2 font-medium text-gray-500">CMP</th>
                <th className="text-right py-2 px-2 font-medium text-gray-500">Valeur</th>
              </tr>
            </thead>
            <tbody>
              {data.topProducts.length > 0 ? data.topProducts.map((product, idx) => (
                <tr key={idx} className="border-b border-gray-50 hover:bg-gray-50">
                  <td className="py-2 px-2 font-medium text-gray-700 truncate max-w-[150px]">
                    {product.product_name || product.name || 'N/A'}
                  </td>
                  <td className="py-2 px-2 text-right">{product.quantity || 0}</td>
                  <td className="py-2 px-2 text-right">{(product.unit_cost || 0).toFixed(0)}</td>
                  <td className="py-2 px-2 text-right font-medium text-gray-900">
                    {formatCompact((product.quantity || 0) * (product.unit_cost || 0))}
                  </td>
                </tr>
              )) : (
                <tr>
                  <td colSpan={4} className="py-4 text-center text-gray-400">Aucun produit en stock</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
