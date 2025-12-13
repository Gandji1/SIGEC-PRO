import { useState, useEffect, memo } from 'react';
import apiClient from '../services/apiClient';
import { Server, Calendar, Filter, RefreshCw, AlertTriangle, Info, CheckCircle, XCircle, Download, Trash2 } from 'lucide-react';

const TableSkeleton = memo(() => (
  <div className="animate-pulse">
    {[1,2,3,4,5,6,7,8].map(i => <div key={i} className="h-12 bg-gray-100 rounded mb-2"></div>)}
  </div>
));

/**
 * Page des Logs Système (Super Admin)
 */
export default function SystemLogsPage() {
  const [logs, setLogs] = useState([]);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState({ level: 'all', type: 'all' });
  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState(null);

  useEffect(() => {
    fetchLogs();
    fetchStats();
  }, [filter, page]);

  const fetchLogs = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page, per_page: 50 });
      if (filter.level !== 'all') params.append('level', filter.level);
      if (filter.type !== 'all') params.append('type', filter.type);

      const res = await apiClient.get(`/superadmin/logs?${params}`);
      setLogs(res.data?.data || []);
      setPagination({
        current: res.data?.current_page,
        last: res.data?.last_page,
        total: res.data?.total,
      });
    } catch (error) {
      console.error('Error:', error);
    } finally {
      setLoading(false);
    }
  };

  const fetchStats = async () => {
    try {
      const res = await apiClient.get('/superadmin/logs/stats');
      setStats(res.data?.data);
    } catch (error) {
      console.error('Error:', error);
    }
  };

  const handleExport = async () => {
    try {
      const params = new URLSearchParams();
      if (filter.level !== 'all') params.append('level', filter.level);
      if (filter.type !== 'all') params.append('type', filter.type);
      
      const res = await apiClient.get(`/superadmin/logs/export?${params}`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `logs-${new Date().toISOString().split('T')[0]}.csv`);
      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch (error) {
      console.error('Export error:', error);
    }
  };

  const levelIcons = {
    info: <Info size={16} className="text-blue-500" />,
    warning: <AlertTriangle size={16} className="text-yellow-500" />,
    error: <XCircle size={16} className="text-red-500" />,
    success: <CheckCircle size={16} className="text-green-500" />,
  };

  const levelColors = {
    info: 'bg-blue-100 text-blue-700',
    warning: 'bg-yellow-100 text-yellow-700',
    error: 'bg-red-100 text-red-700',
    success: 'bg-green-100 text-green-700',
  };

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">📋 Logs Système</h1>
          <p className="text-gray-600 mt-1">Surveillance et audit de la plateforme</p>
        </div>
        <div className="flex gap-2">
          <button 
            onClick={handleExport}
            className="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg"
          >
            <Download size={18} /> Exporter
          </button>
          <button 
            onClick={() => { setPage(1); fetchLogs(); fetchStats(); }}
            className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg"
          >
            <RefreshCw size={18} className={loading ? 'animate-spin' : ''} /> Actualiser
          </button>
        </div>
      </div>

      {/* Filters */}
      <div className="flex gap-4 items-center bg-white p-4 rounded-lg shadow-sm">
        <Filter size={20} className="text-gray-400" />
        <select
          value={filter.level}
          onChange={(e) => { setFilter({...filter, level: e.target.value}); setPage(1); }}
          className="border rounded-lg px-3 py-2"
        >
          <option value="all">Tous les niveaux</option>
          <option value="info">Info</option>
          <option value="warning">Warning</option>
          <option value="error">Error</option>
          <option value="success">Success</option>
        </select>
        <select
          value={filter.type}
          onChange={(e) => { setFilter({...filter, type: e.target.value}); setPage(1); }}
          className="border rounded-lg px-3 py-2"
        >
          <option value="all">Tous les types</option>
          <option value="auth">Authentification</option>
          <option value="tenant">Tenant</option>
          <option value="payment">Paiement</option>
          <option value="system">Système</option>
          <option value="api">API</option>
        </select>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-4 gap-4">
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <p className="text-blue-600 text-sm font-medium">Info</p>
          <p className="text-2xl font-bold text-blue-700">{stats?.by_level?.info || 0}</p>
        </div>
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <p className="text-yellow-600 text-sm font-medium">Warnings</p>
          <p className="text-2xl font-bold text-yellow-700">{stats?.by_level?.warning || 0}</p>
        </div>
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <p className="text-red-600 text-sm font-medium">Errors</p>
          <p className="text-2xl font-bold text-red-700">{stats?.by_level?.error || 0}</p>
        </div>
        <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
          <p className="text-gray-600 text-sm font-medium">Total</p>
          <p className="text-2xl font-bold text-gray-700">{stats?.total || pagination?.total || 0}</p>
        </div>
      </div>

      {/* Logs List */}
      <div className="bg-white rounded-xl shadow-sm overflow-hidden">
        {loading && page === 1 ? (
          <div className="p-4"><TableSkeleton /></div>
        ) : logs.length === 0 ? (
          <div className="p-8 text-center text-gray-500">Aucun log trouvé</div>
        ) : (
          <div className="divide-y">
            {logs.map((log, idx) => (
              <div key={idx} className="p-4 hover:bg-gray-50">
                <div className="flex items-start gap-3">
                  <div className="mt-1">{levelIcons[log.level] || levelIcons.info}</div>
                  <div className="flex-1">
                    <div className="flex items-center gap-2 mb-1">
                      <span className={`px-2 py-0.5 rounded text-xs font-medium ${levelColors[log.level] || levelColors.info}`}>
                        {log.level?.toUpperCase()}
                      </span>
                      <span className="text-xs text-gray-400">{log.type}</span>
                      <span className="text-xs text-gray-400">•</span>
                      <span className="text-xs text-gray-400">
                        {new Date(log.created_at).toLocaleString('fr-FR')}
                      </span>
                    </div>
                    <p className="text-gray-800 font-medium">{log.action || log.message}</p>
                    {log.details && (
                      <p className="text-sm text-gray-500 mt-1">{log.details}</p>
                    )}
                    {log.tenant_name && (
                      <p className="text-xs text-blue-600 mt-1">Tenant: {log.tenant_name}</p>
                    )}
                    {log.user_email && (
                      <p className="text-xs text-gray-400 mt-1">User: {log.user_email}</p>
                    )}
                  </div>
                  {log.ip_address && (
                    <span className="text-xs text-gray-400 font-mono">{log.ip_address}</span>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
        
        {/* Pagination */}
        {pagination && pagination.last > 1 && (
          <div className="p-4 border-t flex items-center justify-between">
            <span className="text-sm text-gray-500">
              Page {pagination.current} sur {pagination.last} ({pagination.total} logs)
            </span>
            <div className="flex gap-2">
              <button
                onClick={() => setPage(p => Math.max(1, p - 1))}
                disabled={page === 1}
                className="px-3 py-1 border rounded disabled:opacity-50"
              >
                Précédent
              </button>
              <button
                onClick={() => setPage(p => Math.min(pagination.last, p + 1))}
                disabled={page >= pagination.last}
                className="px-3 py-1 border rounded disabled:opacity-50"
              >
                Suivant
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
