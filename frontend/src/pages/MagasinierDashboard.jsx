import { useEffect, useState, memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTenantStore } from '../stores/tenantStore';
import { useLanguageStore } from '../stores/languageStore';
import apiClient from '../services/apiClient';
import { Package, ArrowDownToLine, ArrowUpFromLine, AlertTriangle, ClipboardList, Truck, Warehouse } from 'lucide-react';

// Skeleton
const CardSkeleton = memo(() => (
  <div className="animate-pulse bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm">
    <div className="h-4 bg-gray-200 dark:bg-slate-700 rounded w-24 mb-3"></div>
    <div className="h-8 bg-gray-200 dark:bg-slate-700 rounded w-32"></div>
  </div>
));

export default function MagasinierDashboard() {
  const navigate = useNavigate();
  const { user } = useTenantStore();
  const { t } = useLanguageStore();
  const [stats, setStats] = useState({});
  const [pendingTasks, setPendingTasks] = useState([]);
  const [loading, setLoading] = useState(true);

  // Déterminer le type de magasinier
  const isGros = user?.role === 'magasinier_gros';
  const warehouseType = isGros ? 'gros' : 'detail';

  useEffect(() => {
    fetchDashboard();
  }, [isGros]);

  const fetchDashboard = async () => {
    try {
      const endpoint = isGros 
        ? '/approvisionnement/gros/dashboard' 
        : '/approvisionnement/detail/dashboard';
      const tasksEndpoint = isGros ? '/dashboard/warehouse-gros/tasks' : '/dashboard/warehouse-detail/tasks';
      
      // Appels parallèles pour réduire le temps de chargement
      const [dashboardRes, tasksRes] = await Promise.all([
        apiClient.get(endpoint),
        apiClient.get(tasksEndpoint).catch(() => ({ data: { data: [] } }))
      ]);
      
      setStats(dashboardRes.data?.data || dashboardRes.data || {});
      setPendingTasks(tasksRes.data?.data || []);
    } catch (error) {
      console.error('Error:', error);
      // Fallback avec données vides pour éviter écran blanc
      setStats({});
      setPendingTasks([]);
    } finally {
      setLoading(false);
    }
  };

  const kpis = isGros ? [
    { title: 'Valeur Stock', value: stats.stock_value, format: 'currency', icon: Package, color: 'blue' },
    { title: 'Mouvements Aujourd\'hui', value: stats.movements_today, icon: ArrowDownToLine, color: 'green' },
    { title: 'Commandes en Attente', value: stats.pending_po_count, icon: Truck, color: 'orange' },
    { title: 'Demandes à Traiter', value: stats.pending_requests_count, icon: ClipboardList, color: 'purple' },
    { title: 'Stock Bas', value: stats.low_stock_count, icon: AlertTriangle, color: 'red' },
  ] : [
    { title: 'Stock Disponible', value: stats.available_stock, icon: Package, color: 'green' },
    { title: 'Valeur Stock', value: stats.stock_value, format: 'currency', icon: Package, color: 'blue' },
    { title: 'Transferts en Attente', value: stats.pending_transfers_count, icon: ArrowDownToLine, color: 'orange' },
    { title: 'Demandes en Cours', value: stats.pending_requests_count, icon: ArrowUpFromLine, color: 'purple' },
    { title: 'Stock Bas', value: stats.low_stock_count, icon: AlertTriangle, color: 'red' },
  ];

  const formatValue = (val, format) => {
    if (format === 'currency') return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XAF', maximumFractionDigits: 0 }).format(val || 0);
    return val ?? 0;
  };

  const colorMap = {
    blue: 'from-blue-50 to-blue-100 border-blue-200 text-blue-700',
    green: 'from-green-50 to-green-100 border-green-200 text-green-700',
    orange: 'from-orange-50 to-orange-100 border-orange-200 text-orange-700',
    purple: 'from-purple-50 to-purple-100 border-purple-200 text-purple-700',
    red: 'from-red-50 to-red-100 border-red-200 text-red-700',
  };

  return (
    <div className="space-y-4">
      {/* Header compact */}
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
        <div>
          <h1 className="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <Warehouse className="text-orange-500" size={24} />
            {isGros ? 'Magasin Gros' : 'Magasin Détail'}
          </h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">Bienvenue, {user?.name}</p>
        </div>
        <button 
          onClick={() => navigate('/approvisionnement')}
          className="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2.5 rounded-xl font-medium flex items-center gap-2"
        >
          <Package size={18} />
          Accéder au Module
        </button>
      </div>

      {/* KPIs compacts */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
        {loading ? (
          [1,2,3,4,5].map(i => <CardSkeleton key={i} />)
        ) : (
          kpis.map((kpi, idx) => (
            <div key={idx} className={`bg-gradient-to-br ${colorMap[kpi.color]} dark:from-slate-800 dark:to-slate-800 dark:border-slate-700 border rounded-xl p-4`}>
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-xs font-semibold mb-1 dark:text-gray-300">{kpi.title}</p>
                  <p className="text-lg font-bold dark:text-white">{formatValue(kpi.value, kpi.format)}</p>
                </div>
                <kpi.icon size={24} className="opacity-30" />
              </div>
            </div>
          ))
        )}
      </div>

      {/* Actions Rapides */}
      <div className="bg-white rounded-xl shadow-sm p-6">
        <h2 className="text-lg font-bold text-gray-900 mb-4">⚡ Actions Rapides</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {isGros ? (
            <>
              <button onClick={() => navigate('/approvisionnement?tab=gros&section=reception')} className="bg-green-100 hover:bg-green-200 text-green-700 font-medium py-3 px-4 rounded-lg transition">
                📥 Réceptionner Commande
              </button>
              <button onClick={() => navigate('/approvisionnement?tab=gros&section=requests')} className="bg-purple-100 hover:bg-purple-200 text-purple-700 font-medium py-3 px-4 rounded-lg transition">
                📋 Traiter Demandes
              </button>
              <button onClick={() => navigate('/approvisionnement?tab=gros&section=transfers')} className="bg-blue-100 hover:bg-blue-200 text-blue-700 font-medium py-3 px-4 rounded-lg transition">
                📤 Créer Transfert
              </button>
              <button onClick={() => navigate('/approvisionnement?tab=gros&section=inventory')} className="bg-orange-100 hover:bg-orange-200 text-orange-700 font-medium py-3 px-4 rounded-lg transition">
                📊 Inventaire
              </button>
            </>
          ) : (
            <>
              <button onClick={() => navigate('/approvisionnement?tab=detail&section=reception')} className="bg-green-100 hover:bg-green-200 text-green-700 font-medium py-3 px-4 rounded-lg transition">
                📥 Réceptionner Transfert
              </button>
              <button onClick={() => navigate('/approvisionnement?tab=detail&section=requests')} className="bg-purple-100 hover:bg-purple-200 text-purple-700 font-medium py-3 px-4 rounded-lg transition">
                📤 Nouvelle Demande
              </button>
              <button onClick={() => navigate('/approvisionnement?tab=detail&section=orders')} className="bg-blue-100 hover:bg-blue-200 text-blue-700 font-medium py-3 px-4 rounded-lg transition">
                🛒 Commandes POS
              </button>
              <button onClick={() => navigate('/approvisionnement?tab=detail&section=stocks')} className="bg-orange-100 hover:bg-orange-200 text-orange-700 font-medium py-3 px-4 rounded-lg transition">
                📦 État Stock
              </button>
            </>
          )}
        </div>
      </div>

      {/* Tâches en Attente */}
      <div className="bg-white rounded-xl shadow-sm p-6">
        <h2 className="text-lg font-bold text-gray-900 mb-4">📋 Tâches en Attente</h2>
        {pendingTasks.length === 0 ? (
          <p className="text-gray-500 text-center py-8">Aucune tâche en attente</p>
        ) : (
          <div className="space-y-3">
            {pendingTasks.slice(0, 5).map((task, idx) => (
              <div key={idx} className="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                <div>
                  <span className="font-medium">{task.reference || task.title}</span>
                  <span className="text-gray-500 ml-2 text-sm">{task.type}</span>
                </div>
                <span className={`px-2 py-1 rounded text-xs font-medium ${
                  task.priority === 'urgent' ? 'bg-red-100 text-red-700' :
                  task.priority === 'high' ? 'bg-orange-100 text-orange-700' :
                  'bg-blue-100 text-blue-700'
                }`}>
                  {task.priority || 'Normal'}
                </span>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
