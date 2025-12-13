import { useState, useEffect, useCallback, memo } from 'react';
import apiClient from '../services/apiClient';
import { Card, StatCard, PageHeader, Badge, Button, Select, SearchInput, Spinner } from '../components/ui';
import { 
  DollarSign, TrendingUp, TrendingDown, Building2, Calendar, 
  Download, RefreshCw, Filter, PieChart, BarChart3, ArrowUpRight, ArrowDownRight 
} from 'lucide-react';

// Skeleton pour les stats
const StatsSkeleton = memo(() => (
  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    {[1,2,3,4].map(i => (
      <div key={i} className="bg-white dark:bg-gray-800 rounded-xl p-5 animate-pulse">
        <div className="flex items-center gap-4">
          <div className="w-12 h-12 bg-gray-200 dark:bg-gray-700 rounded-xl" />
          <div className="flex-1">
            <div className="h-3 bg-gray-200 dark:bg-gray-700 rounded w-20 mb-2" />
            <div className="h-6 bg-gray-200 dark:bg-gray-700 rounded w-28" />
          </div>
        </div>
      </div>
    ))}
  </div>
));

// Composant pour les graphiques simplifiés
const MiniChart = memo(({ data, color = 'blue' }) => {
  if (!data || data.length === 0) return null;
  
  const max = Math.max(...data);
  const min = Math.min(...data);
  const range = max - min || 1;
  
  return (
    <div className="flex items-end gap-1 h-12">
      {data.slice(-7).map((value, idx) => (
        <div
          key={idx}
          className={`w-2 rounded-t bg-${color}-500 dark:bg-${color}-400 transition-all`}
          style={{ height: `${((value - min) / range) * 100}%`, minHeight: '4px' }}
        />
      ))}
    </div>
  );
});

export default function SuperAdminAccountingPage() {
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState(null);
  const [tenants, setTenants] = useState([]);
  const [selectedTenant, setSelectedTenant] = useState('all');
  const [period, setPeriod] = useState('month');
  const [dateRange, setDateRange] = useState({
    from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    to: new Date().toISOString().split('T')[0]
  });
  const [tenantStats, setTenantStats] = useState([]);

  const formatCurrency = useCallback((val) => new Intl.NumberFormat('fr-FR', { 
    style: 'currency', currency: 'XAF', maximumFractionDigits: 0 
  }).format(val || 0), []);

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        period,
        from: dateRange.from,
        to: dateRange.to,
        ...(selectedTenant !== 'all' && { tenant_id: selectedTenant })
      });

      const [statsRes, tenantsRes, tenantStatsRes] = await Promise.all([
        apiClient.get(`/superadmin/accounting/global-stats?${params}`).catch(() => ({ data: { data: {} } })),
        apiClient.get('/superadmin/tenants?per_page=100').catch(() => ({ data: { data: [] } })),
        apiClient.get(`/superadmin/accounting/by-tenant?${params}`).catch(() => ({ data: { data: [] } })),
      ]);

      setStats(statsRes.data?.data || {});
      setTenants(tenantsRes.data?.data || []);
      setTenantStats(tenantStatsRes.data?.data || []);
    } catch (error) {
      console.error('Error fetching data:', error);
    } finally {
      setLoading(false);
    }
  }, [period, dateRange, selectedTenant]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handlePeriodChange = (newPeriod) => {
    setPeriod(newPeriod);
    const now = new Date();
    let from;
    
    switch (newPeriod) {
      case 'week':
        from = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
        break;
      case 'month':
        from = new Date(now.getFullYear(), now.getMonth(), 1);
        break;
      case 'quarter':
        from = new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1);
        break;
      case 'year':
        from = new Date(now.getFullYear(), 0, 1);
        break;
      default:
        from = new Date(now.getFullYear(), now.getMonth(), 1);
    }
    
    setDateRange({
      from: from.toISOString().split('T')[0],
      to: now.toISOString().split('T')[0]
    });
  };

  const kpis = [
    { 
      title: 'Chiffre d\'Affaires Global', 
      value: formatCurrency(stats?.total_revenue), 
      icon: DollarSign, 
      color: 'green',
      trend: stats?.revenue_trend > 0 ? 'up' : stats?.revenue_trend < 0 ? 'down' : 'neutral',
      trendValue: `${Math.abs(stats?.revenue_trend || 0).toFixed(1)}%`
    },
    { 
      title: 'Total Achats', 
      value: formatCurrency(stats?.total_purchases), 
      icon: TrendingDown, 
      color: 'red' 
    },
    { 
      title: 'Marge Brute', 
      value: formatCurrency(stats?.gross_margin), 
      icon: TrendingUp, 
      color: 'blue' 
    },
    { 
      title: 'Tenants Actifs', 
      value: stats?.active_tenants || 0, 
      icon: Building2, 
      color: 'purple' 
    },
  ];

  return (
    <div className="p-4 sm:p-6 space-y-6 bg-gray-50 dark:bg-gray-900 min-h-screen">
      <PageHeader
        title="💰 Comptabilité Globale"
        description="Vue d'ensemble financière de tous les tenants"
        actions={
          <div className="flex items-center gap-2">
            <Button variant="secondary" icon={RefreshCw} onClick={fetchData} loading={loading}>
              Actualiser
            </Button>
            <Button variant="primary" icon={Download}>
              Exporter
            </Button>
          </div>
        }
      />

      {/* Filtres */}
      <Card className="p-4">
        <div className="flex flex-col sm:flex-row gap-4">
          <div className="flex-1">
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Tenant</label>
            <select
              value={selectedTenant}
              onChange={(e) => setSelectedTenant(e.target.value)}
              className="w-full px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
            >
              <option value="all">Tous les tenants</option>
              {tenants.map(t => (
                <option key={t.id} value={t.id}>{t.name}</option>
              ))}
            </select>
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Période</label>
            <div className="flex rounded-xl overflow-hidden border border-gray-300 dark:border-gray-600">
              {['week', 'month', 'quarter', 'year'].map(p => (
                <button
                  key={p}
                  onClick={() => handlePeriodChange(p)}
                  className={`px-4 py-2.5 text-sm font-medium transition-colors ${
                    period === p 
                      ? 'bg-brand-600 text-white' 
                      : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
                  }`}
                >
                  {p === 'week' ? 'Semaine' : p === 'month' ? 'Mois' : p === 'quarter' ? 'Trimestre' : 'Année'}
                </button>
              ))}
            </div>
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Du</label>
            <input
              type="date"
              value={dateRange.from}
              onChange={(e) => setDateRange(prev => ({ ...prev, from: e.target.value }))}
              className="px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
            />
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Au</label>
            <input
              type="date"
              value={dateRange.to}
              onChange={(e) => setDateRange(prev => ({ ...prev, to: e.target.value }))}
              className="px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
            />
          </div>
        </div>
      </Card>

      {/* KPIs */}
      {loading ? (
        <StatsSkeleton />
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {kpis.map((kpi, idx) => (
            <StatCard key={idx} {...kpi} />
          ))}
        </div>
      )}

      {/* Détails par Tenant */}
      <Card>
        <div className="p-4 border-b border-gray-100 dark:border-gray-700">
          <h3 className="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <Building2 size={20} />
            Performance par Tenant
          </h3>
        </div>
        
        <div className="overflow-x-auto">
          {loading ? (
            <div className="p-8 text-center">
              <Spinner size="lg" className="mx-auto" />
            </div>
          ) : tenantStats.length === 0 ? (
            <div className="p-8 text-center text-gray-500 dark:text-gray-400">
              Aucune donnée disponible pour cette période
            </div>
          ) : (
            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead className="bg-gray-50 dark:bg-gray-800/50">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Tenant</th>
                  <th className="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">CA</th>
                  <th className="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Achats</th>
                  <th className="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Charges</th>
                  <th className="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Marge</th>
                  <th className="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Résultat</th>
                  <th className="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Tendance</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {tenantStats.map((tenant, idx) => (
                  <tr key={idx} className="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-3">
                        <div className="w-8 h-8 bg-brand-100 dark:bg-brand-900/30 rounded-lg flex items-center justify-center">
                          <Building2 size={16} className="text-brand-600 dark:text-brand-400" />
                        </div>
                        <div>
                          <p className="font-medium text-gray-900 dark:text-white">{tenant.name}</p>
                          <p className="text-xs text-gray-500 dark:text-gray-400">{tenant.business_type}</p>
                        </div>
                      </div>
                    </td>
                    <td className="px-4 py-3 text-right font-medium text-green-600 dark:text-green-400">
                      {formatCurrency(tenant.revenue)}
                    </td>
                    <td className="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                      {formatCurrency(tenant.purchases)}
                    </td>
                    <td className="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                      {formatCurrency(tenant.expenses)}
                    </td>
                    <td className="px-4 py-3 text-right font-medium text-blue-600 dark:text-blue-400">
                      {formatCurrency(tenant.gross_margin)}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <span className={`font-bold ${tenant.net_result >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                        {formatCurrency(tenant.net_result)}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-center">
                      {tenant.trend > 0 ? (
                        <Badge variant="success" className="inline-flex items-center gap-1">
                          <ArrowUpRight size={12} />
                          +{tenant.trend.toFixed(1)}%
                        </Badge>
                      ) : tenant.trend < 0 ? (
                        <Badge variant="danger" className="inline-flex items-center gap-1">
                          <ArrowDownRight size={12} />
                          {tenant.trend.toFixed(1)}%
                        </Badge>
                      ) : (
                        <Badge variant="default">0%</Badge>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot className="bg-gray-50 dark:bg-gray-800/50 font-bold">
                <tr>
                  <td className="px-4 py-3 text-gray-900 dark:text-white">TOTAL</td>
                  <td className="px-4 py-3 text-right text-green-600 dark:text-green-400">
                    {formatCurrency(tenantStats.reduce((sum, t) => sum + (t.revenue || 0), 0))}
                  </td>
                  <td className="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                    {formatCurrency(tenantStats.reduce((sum, t) => sum + (t.purchases || 0), 0))}
                  </td>
                  <td className="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                    {formatCurrency(tenantStats.reduce((sum, t) => sum + (t.expenses || 0), 0))}
                  </td>
                  <td className="px-4 py-3 text-right text-blue-600 dark:text-blue-400">
                    {formatCurrency(tenantStats.reduce((sum, t) => sum + (t.gross_margin || 0), 0))}
                  </td>
                  <td className="px-4 py-3 text-right text-gray-900 dark:text-white">
                    {formatCurrency(tenantStats.reduce((sum, t) => sum + (t.net_result || 0), 0))}
                  </td>
                  <td className="px-4 py-3"></td>
                </tr>
              </tfoot>
            </table>
          )}
        </div>
      </Card>

      {/* Résumé Comptable */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Répartition des revenus */}
        <Card className="p-6">
          <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <PieChart size={20} />
            Répartition des Revenus
          </h3>
          <div className="space-y-3">
            {(stats?.revenue_breakdown || []).map((item, idx) => (
              <div key={idx} className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className={`w-3 h-3 rounded-full bg-${['blue', 'green', 'purple', 'orange', 'red'][idx % 5]}-500`} />
                  <span className="text-sm text-gray-700 dark:text-gray-300">{item.category}</span>
                </div>
                <div className="text-right">
                  <span className="font-medium text-gray-900 dark:text-white">{formatCurrency(item.amount)}</span>
                  <span className="text-xs text-gray-500 dark:text-gray-400 ml-2">({item.percentage}%)</span>
                </div>
              </div>
            ))}
          </div>
        </Card>

        {/* Top Performers */}
        <Card className="p-6">
          <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <BarChart3 size={20} />
            Top Performers
          </h3>
          <div className="space-y-3">
            {tenantStats.slice(0, 5).map((tenant, idx) => (
              <div key={idx} className="flex items-center gap-3">
                <div className={`w-8 h-8 rounded-lg flex items-center justify-center font-bold text-white ${
                  idx === 0 ? 'bg-yellow-500' : idx === 1 ? 'bg-gray-400' : idx === 2 ? 'bg-orange-600' : 'bg-gray-300'
                }`}>
                  {idx + 1}
                </div>
                <div className="flex-1 min-w-0">
                  <p className="font-medium text-gray-900 dark:text-white truncate">{tenant.name}</p>
                  <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mt-1">
                    <div 
                      className="bg-brand-600 h-1.5 rounded-full transition-all"
                      style={{ width: `${(tenant.revenue / (tenantStats[0]?.revenue || 1)) * 100}%` }}
                    />
                  </div>
                </div>
                <span className="font-medium text-gray-900 dark:text-white">{formatCurrency(tenant.revenue)}</span>
              </div>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
}
