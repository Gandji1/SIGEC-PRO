import React, { useState, useEffect, useRef, lazy, Suspense } from 'react';
import { useTenantStore } from '../stores/tenantStore';
import { useLanguageStore } from '../stores/languageStore';
import apiClient from '../services/apiClient';
import { useCacheStore, CACHE_KEYS } from '../stores/cacheStore';
import { DashboardSkeleton } from '../components/Skeleton';

// Lazy load des graphiques pour performance
const LazyCharts = lazy(() => import('recharts').then(module => ({
  default: () => null // Placeholder
})));
import { BarChart, Bar, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { TrendingUp, DollarSign, Package, ShoppingCart, Plus, Box, FileText, BarChart3, Users, Settings } from 'lucide-react';

export default function DashboardPage() {
  const { user, tenant } = useTenantStore();
  const { t, language } = useLanguageStore();
  const { get: getCache, set: setCache } = useCacheStore();
  const fetchedRef = useRef(false);
  
  // Initialiser avec le cache pour affichage instantané
  const cachedStats = getCache(CACHE_KEYS.DASHBOARD_STATS);
  const cachedChartData = getCache('dashboard_chart');
  
  const [stats, setStats] = useState(cachedStats || {
    totalSales: 0,
    totalRevenue: 0,
    lowStockProducts: 0,
    pendingPurchases: 0,
  });
  const [chartData, setChartData] = useState(cachedChartData || []);
  const [loading, setLoading] = useState(!cachedStats);

  useEffect(() => {
    if (fetchedRef.current) return;
    fetchedRef.current = true;
    fetchDashboardData();
  }, []);

  const fetchDashboardData = async () => {
    try {
      // Appels parallèles aux endpoints optimisés du backend
      const [statsRes, chartRes] = await Promise.all([
        apiClient.get('/dashboard/stats').catch(() => ({ data: {} })),
        apiClient.get('/dashboard/last7days').catch(() => ({ data: { data: {} } })),
      ]);

      const statsData = statsRes.data?.data || statsRes.data || {};
      const chartApiData = chartRes.data?.data || {};

      // Mapper les stats du backend
      const newStats = {
        totalSales: statsData.sales?.count || statsData.orders_today || 0,
        totalRevenue: statsData.sales?.total_revenue || statsData.today_sales || 0,
        lowStockProducts: statsData.stock_alerts?.low_stock_count || 0,
        pendingPurchases: statsData.pending_operations?.pending_purchases || 0,
      };
      setStats(newStats);
      setCache(CACHE_KEYS.DASHBOARD_STATS, newStats);

      // Mapper les données du graphique depuis le backend
      if (chartApiData.days && chartApiData.totals) {
        const chartFormatted = chartApiData.days.map((day, i) => ({
          date: day,
          revenue: chartApiData.totals[i] || 0,
          count: chartApiData.counts?.[i] || 0,
        }));
        setChartData(chartFormatted);
        setCache('dashboard_chart', chartFormatted);
      }
    } catch (error) {
      console.error('Error fetching dashboard data:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading && !cachedStats) {
    return <DashboardSkeleton />;
  }

  return (
    <div className="space-y-3 sm:space-y-4">
      {/* Header compact */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 dark:text-white">{t('nav.dashboard')}</h1>
          <p className="text-xs sm:text-sm text-gray-500 dark:text-gray-400">{language === 'fr' ? 'Bienvenue' : 'Welcome'}, {user?.name?.split(' ')[0]}!</p>
        </div>
      </div>

      {/* Stats Grid - Design moderne avec icônes carrées - MOBILE OPTIMIZED */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-4 mb-4 sm:mb-6">
        {/* Ventes */}
        <div className="stat-card p-2 sm:p-4">
          <div className="flex items-center gap-2 sm:gap-4">
            <div className="stat-card-icon w-8 h-8 sm:w-10 sm:h-10 bg-blue-100 dark:bg-blue-500/20">
              <TrendingUp size={16} className="text-blue-600 dark:text-blue-400" />
            </div>
            <div className="min-w-0">
              <p className="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Ventes</p>
              <p className="text-base sm:text-xl font-bold text-gray-900 dark:text-white truncate">{stats.totalSales}</p>
            </div>
          </div>
        </div>

        {/* Revenus */}
        <div className="stat-card p-2 sm:p-4">
          <div className="flex items-center gap-2 sm:gap-4">
            <div className="stat-card-icon w-8 h-8 sm:w-10 sm:h-10 bg-green-100 dark:bg-green-500/20">
              <DollarSign size={16} className="text-green-600 dark:text-green-400" />
            </div>
            <div className="min-w-0">
              <p className="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Revenus</p>
              <p className="text-base sm:text-xl font-bold text-gray-900 dark:text-white truncate">{stats.totalRevenue.toLocaleString()}</p>
            </div>
          </div>
        </div>

        {/* Stock bas */}
        <div className="stat-card p-2 sm:p-4">
          <div className="flex items-center gap-2 sm:gap-4">
            <div className="stat-card-icon w-8 h-8 sm:w-10 sm:h-10 bg-orange-100 dark:bg-orange-500/20">
              <Package size={16} className="text-orange-600 dark:text-orange-400" />
            </div>
            <div className="min-w-0">
              <p className="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Stock</p>
              <p className="text-base sm:text-xl font-bold text-gray-900 dark:text-white truncate">{stats.lowStockProducts}</p>
            </div>
          </div>
        </div>

        {/* Achats */}
        <div className="stat-card p-2 sm:p-4">
          <div className="flex items-center gap-2 sm:gap-4">
            <div className="stat-card-icon w-8 h-8 sm:w-10 sm:h-10 bg-purple-100 dark:bg-purple-500/20">
              <ShoppingCart size={16} className="text-purple-600 dark:text-purple-400" />
            </div>
            <div className="min-w-0">
              <p className="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Achats</p>
              <p className="text-base sm:text-xl font-bold text-gray-900 dark:text-white truncate">{stats.pendingPurchases}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Charts - Design compact - MOBILE OPTIMIZED */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4 mb-4 sm:mb-6">
        {/* Revenue Chart */}
        <div className="card p-3 sm:p-5">
          <h2 className="text-sm sm:text-base font-semibold text-gray-900 dark:text-white mb-2 sm:mb-4 flex items-center gap-2">
            <BarChart3 size={16} className="text-blue-500" />
            Revenus (7j)
          </h2>
          <ResponsiveContainer width="100%" height={160} className="sm:!h-[220px]">
            <LineChart data={chartData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#E5E7EB" className="dark:stroke-gray-700" />
              <XAxis dataKey="date" stroke="#9CA3AF" fontSize={12} />
              <YAxis stroke="#9CA3AF" fontSize={12} />
              <Tooltip 
                contentStyle={{ 
                  backgroundColor: 'var(--bg-card-dark)', 
                  border: '1px solid var(--border-dark)', 
                  borderRadius: '12px',
                  color: 'var(--text-dark)',
                  fontSize: '12px'
                }} 
              />
              <Line type="monotone" dataKey="revenue" stroke="#3F5EFB" strokeWidth={2} dot={{ fill: '#3F5EFB', r: 4 }} />
            </LineChart>
          </ResponsiveContainer>
        </div>

        {/* Sales Count Chart */}
        <div className="card p-3 sm:p-5">
          <h2 className="text-sm sm:text-base font-semibold text-gray-900 dark:text-white mb-2 sm:mb-4 flex items-center gap-2">
            <TrendingUp size={16} className="text-green-500" />
            Ventes (7j)
          </h2>
          <ResponsiveContainer width="100%" height={160} className="sm:!h-[220px]">
            <BarChart data={chartData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#E5E7EB" className="dark:stroke-gray-700" />
              <XAxis dataKey="date" stroke="#9CA3AF" fontSize={12} />
              <YAxis stroke="#9CA3AF" fontSize={12} />
              <Tooltip 
                contentStyle={{ 
                  backgroundColor: 'var(--bg-card-dark)', 
                  border: '1px solid var(--border-dark)', 
                  borderRadius: '12px',
                  color: 'var(--text-dark)',
                  fontSize: '12px'
                }} 
              />
              <Bar dataKey="count" fill="#2EC4B6" radius={[6, 6, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Quick Actions - Boutons icônes modernes - MOBILE OPTIMIZED */}
      <div className="card p-3 sm:p-5">
        <h2 className="text-sm sm:text-base font-semibold text-gray-900 dark:text-white mb-2 sm:mb-4">Actions rapides</h2>
        <div className="grid grid-cols-4 gap-2 sm:gap-3">
          <a href="/pos" className="flex flex-col items-center gap-1 sm:gap-2 p-2 sm:p-4 rounded-lg sm:rounded-xl bg-blue-50 dark:bg-blue-500/10 hover:bg-blue-100 dark:hover:bg-blue-500/20 transition-all group">
            <div className="w-9 h-9 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl bg-blue-500 flex items-center justify-center group-hover:scale-110 transition-transform">
              <Plus size={18} className="text-white" />
            </div>
            <span className="text-[10px] sm:text-sm font-medium text-gray-700 dark:text-gray-300">Vente</span>
          </a>
          <a href="/products" className="flex flex-col items-center gap-1 sm:gap-2 p-2 sm:p-4 rounded-lg sm:rounded-xl bg-green-50 dark:bg-green-500/10 hover:bg-green-100 dark:hover:bg-green-500/20 transition-all group">
            <div className="w-9 h-9 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl bg-green-500 flex items-center justify-center group-hover:scale-110 transition-transform">
              <Box size={18} className="text-white" />
            </div>
            <span className="text-[10px] sm:text-sm font-medium text-gray-700 dark:text-gray-300">Produits</span>
          </a>
          <a href="/purchases" className="flex flex-col items-center gap-1 sm:gap-2 p-2 sm:p-4 rounded-lg sm:rounded-xl bg-purple-50 dark:bg-purple-500/10 hover:bg-purple-100 dark:hover:bg-purple-500/20 transition-all group">
            <div className="w-9 h-9 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl bg-purple-500 flex items-center justify-center group-hover:scale-110 transition-transform">
              <ShoppingCart size={18} className="text-white" />
            </div>
            <span className="text-[10px] sm:text-sm font-medium text-gray-700 dark:text-gray-300">Achats</span>
          </a>
          <a href="/reports" className="flex flex-col items-center gap-1 sm:gap-2 p-2 sm:p-4 rounded-lg sm:rounded-xl bg-orange-50 dark:bg-orange-500/10 hover:bg-orange-100 dark:hover:bg-orange-500/20 transition-all group">
            <div className="w-9 h-9 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl bg-orange-500 flex items-center justify-center group-hover:scale-110 transition-transform">
              <FileText size={18} className="text-white" />
            </div>
            <span className="text-[10px] sm:text-sm font-medium text-gray-700 dark:text-gray-300">Rapports</span>
          </a>
        </div>
      </div>
    </div>
  );
}
