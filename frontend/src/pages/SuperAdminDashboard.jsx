import { useState, useEffect, memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useLanguageStore } from '../stores/languageStore';
import apiClient from '../services/apiClient';
import { Building2, Users, CreditCard, AlertTriangle, TrendingUp, Server, Activity, RefreshCw, Shield, Database, Cpu, HardDrive } from 'lucide-react';

const CardSkeleton = memo(() => (
  <div className="animate-pulse bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm">
    <div className="h-4 bg-gray-200 dark:bg-slate-700 rounded w-24 mb-3"></div>
    <div className="h-8 bg-gray-200 dark:bg-slate-700 rounded w-32"></div>
  </div>
));

const TableSkeleton = memo(() => (
  <div className="animate-pulse">
    <div className="h-10 bg-gray-200 rounded mb-2"></div>
    {[1,2,3,4,5].map(i => <div key={i} className="h-12 bg-gray-100 rounded mb-2"></div>)}
  </div>
));

export default function SuperAdminDashboard() {
  const navigate = useNavigate();
  const { t } = useLanguageStore();
  const [stats, setStats] = useState({});
  const [tenants, setTenants] = useState([]);
  const [recentLogs, setRecentLogs] = useState([]);
  const [health, setHealth] = useState({});
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchDashboard();
  }, []);

  const fetchDashboard = async () => {
    setLoading(true);
    try {
      // Utiliser les nouvelles routes superadmin
      const [dashboardRes, healthRes] = await Promise.all([
        apiClient.get('/superadmin/dashboard').catch(() => ({ data: { data: {} } })),
        apiClient.get('/superadmin/dashboard/health').catch(() => ({ data: {} })),
      ]);
      
      const data = dashboardRes.data?.data || {};
      setStats({
        active_tenants: data.tenants?.active || 0,
        suspended_tenants: data.tenants?.suspended || 0,
        total_tenants: data.tenants?.total || 0,
        total_users: data.users?.total || 0,
        monthly_revenue: data.revenue?.monthly || 0,
        yearly_revenue: data.revenue?.yearly || 0,
        total_sales: data.transactions?.total_sales || 0,
        total_sales_amount: data.transactions?.total_amount || 0,
        critical_logs: data.system?.critical_logs_24h || 0,
        db_size: data.system?.db_size_mb || 0,
        requests_today: data.system?.requests_today || 0,
        uptime: data.system?.uptime || '99.9%',
        api_response_time: data.system?.api_response_time || '< 100ms',
      });
      setTenants(data.recent_tenants || []);
      setRecentLogs(data.recent_logs || []);
      setHealth(healthRes.data || {});
    } catch (error) {
      console.error('Error:', error);
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (val) => new Intl.NumberFormat('fr-FR', { 
    style: 'currency', currency: 'XAF', maximumFractionDigits: 0 
  }).format(val || 0);

  const kpis = [
    { title: 'Tenants Actifs', value: stats.active_tenants || 0, icon: Building2, color: 'blue' },
    { title: 'Utilisateurs Total', value: stats.total_users || 0, icon: Users, color: 'green' },
    { title: 'Revenus Mois', value: formatCurrency(stats.monthly_revenue), icon: CreditCard, color: 'purple' },
    { title: 'Tenants Suspendus', value: stats.suspended_tenants || 0, icon: AlertTriangle, color: 'red' },
  ];

  const systemKpis = [
    { title: 'Ventes Totales', value: stats.total_sales || 0, subtitle: formatCurrency(stats.total_sales_amount), icon: TrendingUp, color: 'emerald' },
    { title: 'Erreurs 24h', value: stats.critical_logs || 0, icon: AlertTriangle, color: stats.critical_logs > 0 ? 'red' : 'green' },
    { title: 'Base de données', value: `${stats.db_size || 0} MB`, icon: Database, color: 'indigo' },
    { title: 'Requêtes Aujourd\'hui', value: stats.requests_today || 0, icon: Activity, color: 'cyan' },
  ];

  const colorMap = {
    blue: 'from-blue-50 to-blue-100 border-blue-200 text-blue-700',
    green: 'from-green-50 to-green-100 border-green-200 text-green-700',
    purple: 'from-purple-50 to-purple-100 border-purple-200 text-purple-700',
    red: 'from-red-50 to-red-100 border-red-200 text-red-700',
    emerald: 'from-emerald-50 to-emerald-100 border-emerald-200 text-emerald-700',
    indigo: 'from-indigo-50 to-indigo-100 border-indigo-200 text-indigo-700',
    cyan: 'from-cyan-50 to-cyan-100 border-cyan-200 text-cyan-700',
  };

  return (
    <div className="space-y-4">
      {/* Header compact */}
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
        <div>
          <h1 className="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <Shield className="text-orange-500" size={24} />
            Plateforme SIGEC
          </h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">Administration globale multi-tenant</p>
        </div>
        <div className="flex gap-2">
          <button 
            onClick={fetchDashboard} 
            disabled={loading}
            className="flex items-center gap-2 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-300 px-3 py-2 rounded-xl font-medium text-sm"
          >
            <RefreshCw size={16} className={loading ? 'animate-spin' : ''} />
            Actualiser
          </button>
          <button onClick={() => navigate('/tenant-management')} className="bg-orange-500 hover:bg-orange-600 text-white px-3 py-2 rounded-xl font-medium text-sm flex items-center gap-1">
            <Building2 size={16} />
            Nouveau Tenant
          </button>
        </div>
      </div>

      {/* Santé Système */}
      {health.status && (
        <div className={`p-4 rounded-lg flex items-center gap-3 ${
          health.status === 'healthy' ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200'
        }`}>
          <Shield size={24} className={health.status === 'healthy' ? 'text-green-600' : 'text-yellow-600'} />
          <div>
            <span className={`font-semibold ${health.status === 'healthy' ? 'text-green-700' : 'text-yellow-700'}`}>
              Système {health.status === 'healthy' ? 'Opérationnel' : 'Dégradé'}
            </span>
            <span className="text-gray-500 ml-2 text-sm">
              DB: {health.checks?.database?.status || 'N/A'} | Cache: {health.checks?.cache?.status || 'N/A'} | Disque: {health.checks?.storage?.message || 'N/A'}
            </span>
          </div>
        </div>
      )}

      {/* KPIs Principaux */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
        {loading ? [1,2,3,4].map(i => <CardSkeleton key={i} />) : kpis.map((kpi, idx) => (
          <div key={idx} className={`bg-gradient-to-br ${colorMap[kpi.color]} dark:from-slate-800 dark:to-slate-800 dark:border-slate-700 border rounded-xl p-4`}>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-xs font-semibold mb-1 dark:text-gray-300">{kpi.title}</p>
                <p className="text-xl font-bold dark:text-white">{kpi.value}</p>
              </div>
              <kpi.icon size={32} className="opacity-30" />
            </div>
          </div>
        ))}
      </div>

      {/* Actions Rapides */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
        <button onClick={() => navigate('/tenant-management')} className="bg-blue-100 hover:bg-blue-200 text-blue-700 font-medium py-4 px-4 rounded-xl transition flex items-center justify-center gap-2">
          <Building2 size={20} /> Gérer Tenants
        </button>
        <button onClick={() => navigate('/subscriptions')} className="bg-purple-100 hover:bg-purple-200 text-purple-700 font-medium py-4 px-4 rounded-xl transition flex items-center justify-center gap-2">
          <CreditCard size={20} /> Abonnements
        </button>
        <button onClick={() => navigate('/payment-gateways')} className="bg-green-100 hover:bg-green-200 text-green-700 font-medium py-4 px-4 rounded-xl transition flex items-center justify-center gap-2">
          <CreditCard size={20} /> Passerelles Paiement
        </button>
        <button onClick={() => navigate('/system-logs')} className="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-4 px-4 rounded-xl transition flex items-center justify-center gap-2">
          <Server size={20} /> Logs Système
        </button>
        <button onClick={() => navigate('/platform-settings')} className="bg-orange-100 hover:bg-orange-200 text-orange-700 font-medium py-4 px-4 rounded-xl transition flex items-center justify-center gap-2">
          <Activity size={20} /> Paramètres
        </button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Tenants Récents */}
        <div className="bg-white rounded-xl shadow-sm p-6">
          <div className="flex justify-between items-center mb-4">
            <h2 className="text-lg font-bold text-gray-900">🏢 Tenants Récents</h2>
            <a href="/tenant-management" className="text-blue-600 text-sm hover:underline">Voir tout</a>
          </div>
          {loading ? <TableSkeleton /> : tenants.length === 0 ? (
            <p className="text-gray-500 text-center py-8">Aucun tenant</p>
          ) : (
            <div className="space-y-3">
              {tenants.slice(0, 5).map((tenant) => (
                <div key={tenant.id} className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                  <div>
                    <span className="font-medium">{tenant.name}</span>
                    <span className="text-gray-500 ml-2 text-sm">{tenant.business_type}</span>
                  </div>
                  <span className={`px-2 py-1 rounded text-xs font-medium ${
                    tenant.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                  }`}>
                    {tenant.status}
                  </span>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Logs Récents */}
        <div className="bg-white rounded-xl shadow-sm p-6">
          <div className="flex justify-between items-center mb-4">
            <h2 className="text-lg font-bold text-gray-900">📋 Activité Récente</h2>
            <a href="/system-logs" className="text-blue-600 text-sm hover:underline">Voir tout</a>
          </div>
          {loading ? <TableSkeleton /> : recentLogs.length === 0 ? (
            <p className="text-gray-500 text-center py-8">Aucune activité</p>
          ) : (
            <div className="space-y-2">
              {recentLogs.slice(0, 5).map((log, idx) => (
                <div key={idx} className="flex justify-between items-center p-2 border-b text-sm">
                  <span className="text-gray-700">{log.action}</span>
                  <span className="text-gray-400 text-xs">{log.created_at}</span>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Statistiques Système */}
      <div className="bg-white rounded-xl shadow-sm p-6">
        <h2 className="text-lg font-bold text-gray-900 mb-4">📊 Performance Système</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="text-center p-4 bg-gray-50 rounded-lg">
            <p className="text-2xl font-bold text-green-600">{stats.api_response_time || '< 100'}ms</p>
            <p className="text-sm text-gray-500">Temps Réponse API</p>
          </div>
          <div className="text-center p-4 bg-gray-50 rounded-lg">
            <p className="text-2xl font-bold text-blue-600">{stats.uptime || '99.9'}%</p>
            <p className="text-sm text-gray-500">Uptime</p>
          </div>
          <div className="text-center p-4 bg-gray-50 rounded-lg">
            <p className="text-2xl font-bold text-purple-600">{stats.db_size || '0'} GB</p>
            <p className="text-sm text-gray-500">Taille DB</p>
          </div>
          <div className="text-center p-4 bg-gray-50 rounded-lg">
            <p className="text-2xl font-bold text-orange-600">{stats.requests_today || 0}</p>
            <p className="text-sm text-gray-500">Requêtes Aujourd'hui</p>
          </div>
        </div>
      </div>
    </div>
  );
}
