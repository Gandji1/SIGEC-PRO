import { useState, useEffect, memo } from 'react';
import { TrendingUp, ShoppingCart, Package, AlertTriangle } from 'lucide-react';
import apiClient from '../services/apiClient';
import { usePermission } from '../hooks/usePermission';

/**
 * Composant de statistiques rapides pour le header
 * Affiche les KPIs clés selon le rôle
 */
const QuickStats = memo(function QuickStats() {
  const { can, userRole } = usePermission();
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchStats();
    // Rafraîchir toutes les 2 minutes
    const interval = setInterval(fetchStats, 120000);
    return () => clearInterval(interval);
  }, [userRole]);

  const fetchStats = async () => {
    try {
      let endpoint = '/dashboard/stats';
      
      // Adapter l'endpoint selon le rôle
      if (userRole === 'caissier') {
        endpoint = '/dashboard/cashier/stats';
      } else if (userRole === 'pos_server') {
        endpoint = '/dashboard/server/stats';
      } else if (userRole === 'manager') {
        endpoint = '/dashboard/manager/stats';
      } else if (userRole === 'accountant') {
        endpoint = '/dashboard/accountant/stats';
      }

      const res = await apiClient.get(endpoint);
      setStats(res.data?.data || res.data || {});
    } catch (error) {
      console.error('QuickStats error:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading || !stats) return null;

  const formatCurrency = (val) => {
    if (!val) return '0';
    return new Intl.NumberFormat('fr-FR', { 
      notation: 'compact',
      maximumFractionDigits: 1 
    }).format(val);
  };

  // Définir les stats à afficher selon le rôle
  const getStatsForRole = () => {
    switch (userRole) {
      case 'caissier':
        return [
          { label: 'Ventes', value: formatCurrency(stats.today_sales), icon: TrendingUp, color: 'green' },
          { label: 'Transactions', value: stats.today_transactions || 0, icon: ShoppingCart, color: 'blue' },
        ];
      case 'pos_server':
        return [
          { label: 'Commandes', value: stats.my_orders_count || 0, icon: ShoppingCart, color: 'blue' },
          { label: 'En cours', value: stats.preparing_count || 0, icon: Package, color: 'orange' },
        ];
      case 'manager':
        return [
          { label: 'Ventes', value: stats.todaySales || 0, icon: TrendingUp, color: 'green' },
          { label: 'Stock bas', value: stats.lowStockItems || 0, icon: AlertTriangle, color: 'red' },
        ];
      default:
        return [
          { label: 'CA Jour', value: formatCurrency(stats.today_sales || stats.sales?.total), icon: TrendingUp, color: 'green' },
        ];
    }
  };

  const statsToShow = getStatsForRole();

  return (
    <div className="flex items-center gap-4">
      {statsToShow.map((stat, idx) => (
        <div key={idx} className="flex items-center gap-2 px-3 py-1 bg-gray-100 rounded-lg">
          <stat.icon size={16} className={`text-${stat.color}-600`} />
          <span className="text-xs text-gray-500">{stat.label}</span>
          <span className={`font-bold text-${stat.color}-600`}>{stat.value}</span>
        </div>
      ))}
    </div>
  );
});

export default QuickStats;
