import { useState, useEffect, memo } from 'react';
import { AlertTriangle, X, RefreshCw } from 'lucide-react';
import apiClient from '../services/apiClient';

/**
 * Composant d'alerte pour les stocks bas
 * S'affiche automatiquement si des produits sont en stock critique
 */
const LowStockAlert = memo(function LowStockAlert({ autoRefresh = true, refreshInterval = 300000 }) {
  const [alerts, setAlerts] = useState([]);
  const [loading, setLoading] = useState(false);
  const [dismissed, setDismissed] = useState(false);
  const [expanded, setExpanded] = useState(false);

  const fetchAlerts = async () => {
    setLoading(true);
    try {
      const res = await apiClient.get('/automation/low-stock');
      setAlerts(res.data?.data || []);
    } catch (error) {
      console.error('Error fetching low stock alerts:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAlerts();
    
    if (autoRefresh) {
      const interval = setInterval(fetchAlerts, refreshInterval);
      return () => clearInterval(interval);
    }
  }, [autoRefresh, refreshInterval]);

  if (dismissed || alerts.length === 0) return null;

  return (
    <div className="fixed bottom-4 right-4 z-50 max-w-sm">
      <div className="bg-orange-50 border border-orange-300 rounded-lg shadow-lg overflow-hidden">
        {/* Header */}
        <div className="bg-orange-100 px-4 py-2 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <AlertTriangle className="text-orange-600" size={20} />
            <span className="font-semibold text-orange-800">
              {alerts.length} Stock{alerts.length > 1 ? 's' : ''} Bas
            </span>
          </div>
          <div className="flex items-center gap-1">
            <button 
              onClick={fetchAlerts} 
              className="p-1 hover:bg-orange-200 rounded"
              disabled={loading}
            >
              <RefreshCw size={16} className={`text-orange-600 ${loading ? 'animate-spin' : ''}`} />
            </button>
            <button 
              onClick={() => setDismissed(true)} 
              className="p-1 hover:bg-orange-200 rounded"
            >
              <X size={16} className="text-orange-600" />
            </button>
          </div>
        </div>

        {/* Content */}
        <div className="px-4 py-3">
          <div className={`space-y-2 ${expanded ? '' : 'max-h-32 overflow-hidden'}`}>
            {alerts.slice(0, expanded ? undefined : 3).map((alert, idx) => (
              <div key={idx} className="flex justify-between items-center text-sm">
                <span className="text-gray-700 truncate flex-1">{alert.product}</span>
                <span className="text-orange-600 font-medium ml-2">
                  {alert.quantity}/{alert.min_quantity}
                </span>
              </div>
            ))}
          </div>
          
          {alerts.length > 3 && (
            <button 
              onClick={() => setExpanded(!expanded)}
              className="text-orange-600 text-sm font-medium mt-2 hover:underline"
            >
              {expanded ? 'Voir moins' : `+${alerts.length - 3} autres`}
            </button>
          )}
        </div>

        {/* Action */}
        <div className="px-4 py-2 bg-orange-50 border-t border-orange-200">
          <a 
            href="/approvisionnement?tab=gros&section=stocks"
            className="text-orange-700 text-sm font-medium hover:underline"
          >
            Gérer les stocks →
          </a>
        </div>
      </div>
    </div>
  );
});

export default LowStockAlert;
