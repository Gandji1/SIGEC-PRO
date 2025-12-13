import { useState, useEffect } from 'react';
import { AlertTriangle, CreditCard, X, Clock } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import apiClient from '../services/apiClient';
import { useTenantStore } from '../stores/tenantStore';

export default function SubscriptionAlert() {
  const navigate = useNavigate();
  const { user } = useTenantStore();
  const [alert, setAlert] = useState(null);
  const [dismissed, setDismissed] = useState(false);

  useEffect(() => {
    // Ne pas afficher pour super_admin
    if (user?.role === 'super_admin') return;
    
    checkSubscription();
  }, [user]);

  const checkSubscription = async () => {
    try {
      const res = await apiClient.get('/subscription/status');
      const data = res.data?.data;

      if (!data) return;

      // Expire bientôt (7 jours ou moins)
      if (data.is_expiring_soon && data.days_remaining > 0) {
        setAlert({
          type: 'warning',
          title: 'Abonnement expire bientôt',
          message: `Votre abonnement expire dans ${data.days_remaining} jour(s). Renouvelez pour éviter une interruption.`,
          action: 'renew',
        });
        return;
      }

      // Fin de période d'essai proche (3 jours ou moins)
      if (data.is_trial && data.days_remaining <= 3 && data.days_remaining > 0) {
        setAlert({
          type: 'warning',
          title: 'Période d\'essai',
          message: `Votre essai gratuit se termine dans ${data.days_remaining} jour(s). Passez à un plan payant.`,
          action: 'upgrade',
        });
        return;
      }

    } catch (err) {
      // Ignorer les erreurs silencieusement
    }
  };

  const handleAction = () => {
    navigate('/subscription-required');
  };

  if (!alert || dismissed) return null;

  const bgColor = alert.type === 'error' ? 'bg-red-50 border-red-200' : 'bg-yellow-50 border-yellow-200';
  const textColor = alert.type === 'error' ? 'text-red-700' : 'text-yellow-700';
  const iconColor = alert.type === 'error' ? 'text-red-500' : 'text-yellow-500';

  return (
    <div className={`fixed top-4 right-4 z-50 max-w-md p-4 rounded-lg border shadow-lg ${bgColor}`}>
      <div className="flex items-start gap-3">
        <AlertTriangle className={iconColor} size={24} />
        <div className="flex-1">
          <h4 className={`font-semibold ${textColor}`}>{alert.title}</h4>
          <p className={`text-sm ${textColor} opacity-80`}>{alert.message}</p>
          {alert.action && (
            <button 
              onClick={handleAction}
              className="mt-2 flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-800"
            >
              <CreditCard size={16} />
              {alert.action === 'renew' ? 'Renouveler' : 'Passer au plan payant'}
            </button>
          )}
        </div>
        <button 
          onClick={() => setDismissed(true)}
          className={`${textColor} hover:opacity-70`}
        >
          <X size={18} />
        </button>
      </div>
    </div>
  );
}
