import { useEffect, useState, memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTenantStore } from '../stores/tenantStore';
import { useLanguageStore } from '../stores/languageStore';
import apiClient from '../services/apiClient';
import { ShoppingBag, Clock, CheckCircle, XCircle, Users, Coffee, TrendingUp } from 'lucide-react';

// Skeleton
const CardSkeleton = memo(() => (
  <div className="animate-pulse bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm">
    <div className="h-4 bg-gray-200 dark:bg-slate-700 rounded w-24 mb-3"></div>
    <div className="h-8 bg-gray-200 dark:bg-slate-700 rounded w-32"></div>
  </div>
));

export default function ServeurDashboard() {
  const navigate = useNavigate();
  const { user } = useTenantStore();
  const { t } = useLanguageStore();
  const [stats, setStats] = useState({});
  const [myOrders, setMyOrders] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchDashboard();
    // Rafraîchir toutes les 30 secondes
    const interval = setInterval(fetchDashboard, 30000);
    return () => clearInterval(interval);
  }, []);

  const fetchDashboard = async () => {
    try {
      const [statsRes, ordersRes] = await Promise.all([
        apiClient.get('/dashboard/server/stats').catch(() => ({ data: {} })),
        apiClient.get('/pos/orders').catch(() => ({ data: { data: [] } })),
      ]);
      setStats(statsRes.data?.data || statsRes.data || {});
      setMyOrders(ordersRes.data?.data || ordersRes.data || []);
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
    { title: 'Mes Commandes', value: stats.my_orders_count || 0, icon: ShoppingBag, color: 'blue' },
    { title: 'En Préparation', value: stats.preparing_count || 0, icon: Clock, color: 'orange' },
    { title: 'Servies', value: stats.served_count || 0, icon: CheckCircle, color: 'green' },
    { title: 'Mon CA', value: formatCurrency(stats.my_sales), icon: Coffee, color: 'purple' },
  ];

  const colorMap = {
    blue: 'from-blue-50 to-blue-100 border-blue-200 text-blue-700',
    orange: 'from-orange-50 to-orange-100 border-orange-200 text-orange-700',
    green: 'from-green-50 to-green-100 border-green-200 text-green-700',
    purple: 'from-purple-50 to-purple-100 border-purple-200 text-purple-700',
  };

  const statusColors = {
    pending: 'bg-yellow-100 text-yellow-700',
    approved: 'bg-blue-100 text-blue-700',
    preparing: 'bg-orange-100 text-orange-700',
    ready: 'bg-cyan-100 text-cyan-700',
    served: 'bg-green-100 text-green-700',
    confirmed: 'bg-purple-100 text-purple-700',
    cancelled: 'bg-red-100 text-red-700',
  };

  const statusLabels = {
    pending: 'En attente',
    approved: 'Approuvée',
    preparing: 'En préparation',
    ready: 'Prête',
    served: 'Servie',
    confirmed: 'Payée',
    cancelled: 'Annulée',
  };

  return (
    <div className="space-y-3 sm:space-y-4">
      {/* Header compact - MOBILE OPTIMIZED */}
      <div className="flex justify-between items-center gap-2">
        <div className="min-w-0">
          <h1 className="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <ShoppingBag className="text-orange-500" size={20} />
            <span className="truncate">Espace Serveur</span>
          </h1>
          <p className="text-gray-500 dark:text-gray-400 text-xs sm:text-sm truncate">Bienvenue, {user?.name?.split(' ')[0]}</p>
        </div>
        <button 
          onClick={() => navigate('/pos')}
          className="bg-orange-500 hover:bg-orange-600 text-white px-2 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl font-medium flex items-center gap-1 sm:gap-2 text-xs sm:text-sm flex-shrink-0"
        >
          <ShoppingBag size={16} />
          <span className="hidden sm:inline">Nouvelle</span> Cmd
        </button>
      </div>

      {/* KPIs compacts - MOBILE OPTIMIZED */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3">
        {loading ? (
          [1,2,3,4].map(i => <CardSkeleton key={i} />)
        ) : (
          kpis.map((kpi, idx) => (
            <div key={idx} className={`bg-gradient-to-br ${colorMap[kpi.color]} dark:from-slate-800 dark:to-slate-800 dark:border-slate-700 border rounded-lg sm:rounded-xl p-2 sm:p-4`}>
              <div className="flex items-center justify-between">
                <div className="min-w-0">
                  <p className="text-[10px] sm:text-xs font-semibold mb-0.5 sm:mb-1 dark:text-gray-300 truncate">{kpi.title}</p>
                  <p className="text-base sm:text-xl font-bold dark:text-white truncate">{kpi.value}</p>
                </div>
                <kpi.icon size={20} className="opacity-30 flex-shrink-0" />
              </div>
            </div>
          ))
        )}
      </div>

      {/* Actions Rapides - MOBILE OPTIMIZED */}
      <div className="grid grid-cols-4 gap-1.5 sm:gap-2">
        <button 
          onClick={() => navigate('/pos')}
          className="bg-orange-500 hover:bg-orange-600 text-white font-medium py-2 sm:py-3 rounded-lg sm:rounded-xl transition flex flex-col items-center gap-0.5 sm:gap-1"
        >
          <ShoppingBag size={18} />
          <span className="text-[9px] sm:text-xs">Cmd</span>
        </button>
        <button 
          onClick={() => navigate('/pos/tables')}
          className="bg-green-500 hover:bg-green-600 text-white font-medium py-2 sm:py-3 rounded-lg sm:rounded-xl transition flex flex-col items-center gap-0.5 sm:gap-1"
        >
          <Users size={18} />
          <span className="text-[9px] sm:text-xs">Tables</span>
        </button>
        <button 
          onClick={() => navigate('/pos/kitchen')}
          className="bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 sm:py-3 rounded-lg sm:rounded-xl transition flex flex-col items-center gap-0.5 sm:gap-1"
        >
          <Clock size={18} />
          <span className="text-[9px] sm:text-xs">Statut</span>
        </button>
        <button 
          onClick={fetchDashboard}
          className="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 sm:py-3 rounded-lg sm:rounded-xl transition flex flex-col items-center gap-0.5 sm:gap-1"
        >
          <TrendingUp size={18} />
          <span className="text-[9px] sm:text-xs">Refresh</span>
        </button>
      </div>



      {/* Indicateur de statut - MOBILE OPTIMIZED */}
      <div className="bg-gray-50 rounded-lg sm:rounded-xl p-2 sm:p-4 flex items-center justify-between">
        <div className="flex items-center gap-1.5 sm:gap-2">
          <span className="w-2 h-2 sm:w-3 sm:h-3 bg-green-500 rounded-full animate-pulse"></span>
          <span className="text-gray-600 text-xs sm:text-sm">Connecté</span>
        </div>
        <span className="text-[10px] sm:text-sm text-gray-500">
          {new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
        </span>
      </div>
    </div>
  );
}
