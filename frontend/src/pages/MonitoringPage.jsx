import { useState, useEffect } from 'react';
import apiClient from '../services/apiClient';
import { Activity, Server, Database, HardDrive, Cpu, AlertTriangle, RefreshCw, CheckCircle, XCircle } from 'lucide-react';

export default function MonitoringPage() {
  const [data, setData] = useState(null);
  const [alerts, setAlerts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [autoRefresh, setAutoRefresh] = useState(true);

  useEffect(() => {
    fetchData();
    
    // Auto-refresh toutes les 30 secondes
    let interval;
    if (autoRefresh) {
      interval = setInterval(fetchData, 30000);
    }
    return () => clearInterval(interval);
  }, [autoRefresh]);

  const fetchData = async () => {
    try {
      const [monitoringRes, alertsRes] = await Promise.all([
        apiClient.get('/superadmin/monitoring'),
        apiClient.get('/superadmin/monitoring/alerts'),
      ]);
      setData(monitoringRes.data?.data);
      setAlerts(alertsRes.data?.data || []);
    } catch (err) {
      console.error('Error:', err);
    } finally {
      setLoading(false);
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'ok': return 'text-green-600 bg-green-100';
      case 'warning': return 'text-yellow-600 bg-yellow-100';
      case 'critical':
      case 'error': return 'text-red-600 bg-red-100';
      default: return 'text-gray-600 bg-gray-100';
    }
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'ok': return <CheckCircle className="text-green-500" size={20} />;
      case 'warning': return <AlertTriangle className="text-yellow-500" size={20} />;
      default: return <XCircle className="text-red-500" size={20} />;
    }
  };

  if (loading) {
    return (
      <div className="p-6 flex items-center justify-center">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">📊 Monitoring Système</h1>
          <p className="text-gray-600 mt-1">Surveillance en temps réel de la plateforme</p>
        </div>
        <div className="flex items-center gap-4">
          <label className="flex items-center gap-2 text-sm text-gray-600">
            <input 
              type="checkbox" 
              checked={autoRefresh} 
              onChange={(e) => setAutoRefresh(e.target.checked)}
              className="rounded"
            />
            Auto-refresh (30s)
          </label>
          <button 
            onClick={fetchData}
            className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg"
          >
            <RefreshCw size={18} />
            Actualiser
          </button>
        </div>
      </div>

      {/* Alertes actives */}
      {alerts.length > 0 && (
        <div className="space-y-2">
          {alerts.map((alert, idx) => (
            <div key={idx} className={`p-4 rounded-lg border flex items-center gap-3 ${
              alert.type === 'critical' ? 'bg-red-50 border-red-200' : 'bg-yellow-50 border-yellow-200'
            }`}>
              <AlertTriangle className={alert.type === 'critical' ? 'text-red-500' : 'text-yellow-500'} size={24} />
              <div>
                <span className={`font-semibold ${alert.type === 'critical' ? 'text-red-700' : 'text-yellow-700'}`}>
                  {alert.category.toUpperCase()}
                </span>
                <span className={`ml-2 ${alert.type === 'critical' ? 'text-red-600' : 'text-yellow-600'}`}>
                  {alert.message}
                </span>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Métriques Serveur */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* CPU */}
        <div className="bg-white rounded-xl shadow-sm p-5 border">
          <div className="flex items-center gap-3 mb-3">
            <div className="p-2 bg-blue-100 rounded-lg">
              <Cpu className="text-blue-600" size={24} />
            </div>
            <h3 className="font-semibold text-gray-800">CPU</h3>
          </div>
          <div className="space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-gray-500">Load 1m</span>
              <span className="font-medium">{data?.server?.cpu?.load_1m?.toFixed(2) || 0}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-gray-500">Load 5m</span>
              <span className="font-medium">{data?.server?.cpu?.load_5m?.toFixed(2) || 0}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-gray-500">Cores</span>
              <span className="font-medium">{data?.server?.cpu?.cores || 1}</span>
            </div>
          </div>
        </div>

        {/* Mémoire */}
        <div className="bg-white rounded-xl shadow-sm p-5 border">
          <div className="flex items-center gap-3 mb-3">
            <div className={`p-2 rounded-lg ${getStatusColor(data?.server?.memory?.status)}`}>
              <Server size={24} />
            </div>
            <h3 className="font-semibold text-gray-800">Mémoire</h3>
          </div>
          <div className="space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-gray-500">Utilisée</span>
              <span className="font-medium">{data?.server?.memory?.used_mb || 0} MB</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-gray-500">Total</span>
              <span className="font-medium">{data?.server?.memory?.total_mb || 0} MB</span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2">
              <div 
                className={`h-2 rounded-full ${
                  (data?.server?.memory?.percent || 0) > 90 ? 'bg-red-500' : 
                  (data?.server?.memory?.percent || 0) > 75 ? 'bg-yellow-500' : 'bg-green-500'
                }`}
                style={{ width: `${data?.server?.memory?.percent || 0}%` }}
              ></div>
            </div>
          </div>
        </div>

        {/* Disque */}
        <div className="bg-white rounded-xl shadow-sm p-5 border">
          <div className="flex items-center gap-3 mb-3">
            <div className={`p-2 rounded-lg ${getStatusColor(data?.server?.disk?.status)}`}>
              <HardDrive size={24} />
            </div>
            <h3 className="font-semibold text-gray-800">Disque</h3>
          </div>
          <div className="space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-gray-500">Utilisé</span>
              <span className="font-medium">{data?.server?.disk?.used_gb || 0} GB</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-gray-500">Libre</span>
              <span className="font-medium">{data?.server?.disk?.free_gb || 0} GB</span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2">
              <div 
                className={`h-2 rounded-full ${
                  (data?.server?.disk?.percent || 0) > 90 ? 'bg-red-500' : 
                  (data?.server?.disk?.percent || 0) > 80 ? 'bg-yellow-500' : 'bg-green-500'
                }`}
                style={{ width: `${data?.server?.disk?.percent || 0}%` }}
              ></div>
            </div>
          </div>
        </div>

        {/* Base de données */}
        <div className="bg-white rounded-xl shadow-sm p-5 border">
          <div className="flex items-center gap-3 mb-3">
            <div className={`p-2 rounded-lg ${getStatusColor(data?.database?.status)}`}>
              <Database size={24} />
            </div>
            <h3 className="font-semibold text-gray-800">Base de données</h3>
          </div>
          <div className="space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-gray-500">Driver</span>
              <span className="font-medium">{data?.database?.driver || 'N/A'}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-gray-500">Temps réponse</span>
              <span className="font-medium">{data?.database?.response_time_ms || 0} ms</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-gray-500">Taille</span>
              <span className="font-medium">{data?.database?.size_mb || 0} MB</span>
            </div>
          </div>
        </div>
      </div>

      {/* Application & Tenants */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Application */}
        <div className="bg-white rounded-xl shadow-sm p-6 border">
          <h3 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <Activity className="text-blue-600" size={24} />
            Application
          </h3>
          <div className="space-y-3">
            <div className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
              <span className="text-gray-600">Requêtes aujourd'hui</span>
              <span className="font-bold text-blue-600">{data?.application?.requests_today || 0}</span>
            </div>
            <div className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
              <span className="text-gray-600">Erreurs 24h</span>
              <span className={`font-bold ${(data?.application?.errors_24h || 0) > 0 ? 'text-red-600' : 'text-green-600'}`}>
                {data?.application?.errors_24h || 0}
              </span>
            </div>
            <div className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
              <span className="text-gray-600">Jobs en attente</span>
              <span className="font-bold">{data?.application?.queue?.pending_jobs || 0}</span>
            </div>
            <div className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
              <span className="text-gray-600">Jobs échoués</span>
              <span className={`font-bold ${(data?.application?.queue?.failed_jobs || 0) > 0 ? 'text-red-600' : 'text-green-600'}`}>
                {data?.application?.queue?.failed_jobs || 0}
              </span>
            </div>
            <div className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
              <span className="text-gray-600">Cache</span>
              <div className="flex items-center gap-2">
                {getStatusIcon(data?.application?.cache?.status)}
                <span className="font-medium">{data?.application?.cache?.driver}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Tenants */}
        <div className="bg-white rounded-xl shadow-sm p-6 border">
          <h3 className="text-lg font-bold text-gray-800 mb-4">🏢 Santé Tenants</h3>
          <div className="space-y-3">
            <div className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
              <span className="text-gray-600">Total</span>
              <span className="font-bold text-gray-800">{data?.tenants?.total || 0}</span>
            </div>
            <div className="flex justify-between items-center p-3 bg-green-50 rounded-lg">
              <span className="text-green-700">Sains</span>
              <span className="font-bold text-green-600">{data?.tenants?.healthy || 0}</span>
            </div>
            <div className="flex justify-between items-center p-3 bg-yellow-50 rounded-lg">
              <span className="text-yellow-700">Attention</span>
              <span className="font-bold text-yellow-600">{data?.tenants?.warning || 0}</span>
            </div>
            <div className="flex justify-between items-center p-3 bg-red-50 rounded-lg">
              <span className="text-red-700">Critiques</span>
              <span className="font-bold text-red-600">{data?.tenants?.critical || 0}</span>
            </div>
          </div>
          
          {/* Répartition par statut */}
          {data?.tenants?.by_status && (
            <div className="mt-4 pt-4 border-t">
              <h4 className="text-sm font-semibold text-gray-600 mb-2">Par statut</h4>
              <div className="flex flex-wrap gap-2">
                {Object.entries(data.tenants.by_status).map(([status, count]) => (
                  <span key={status} className={`px-3 py-1 rounded-full text-sm font-medium ${
                    status === 'active' ? 'bg-green-100 text-green-700' :
                    status === 'suspended' ? 'bg-red-100 text-red-700' :
                    'bg-gray-100 text-gray-700'
                  }`}>
                    {status}: {count}
                  </span>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Infos Système */}
      <div className="bg-white rounded-xl shadow-sm p-6 border">
        <h3 className="text-lg font-bold text-gray-800 mb-4">ℹ️ Informations Système</h3>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="text-center p-4 bg-gray-50 rounded-lg">
            <p className="text-sm text-gray-500">PHP Version</p>
            <p className="font-bold text-gray-800">{data?.server?.php_version || 'N/A'}</p>
          </div>
          <div className="text-center p-4 bg-gray-50 rounded-lg">
            <p className="text-sm text-gray-500">OS</p>
            <p className="font-bold text-gray-800">{data?.server?.os || 'N/A'}</p>
          </div>
          <div className="text-center p-4 bg-gray-50 rounded-lg">
            <p className="text-sm text-gray-500">Uptime</p>
            <p className="font-bold text-gray-800">{data?.server?.uptime || 'N/A'}</p>
          </div>
          <div className="text-center p-4 bg-gray-50 rounded-lg">
            <p className="text-sm text-gray-500">Connexions DB</p>
            <p className="font-bold text-gray-800">{data?.database?.connections || 0}</p>
          </div>
        </div>
      </div>
    </div>
  );
}
