import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { CreditCard, Check, Star, Zap, Crown, AlertTriangle, LogOut } from 'lucide-react';
import apiClient from '../services/apiClient';
import { useTenantStore } from '../stores/tenantStore';

export default function SubscriptionRequiredPage() {
  const navigate = useNavigate();
  const { user, tenant, logout } = useTenantStore();
  const [plans, setPlans] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedPlan, setSelectedPlan] = useState(null);
  const [processing, setProcessing] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState('mobile_money');
  const [showPaymentModal, setShowPaymentModal] = useState(false);

  useEffect(() => {
    fetchPlans();
    checkSubscription();
  }, []);

  const checkSubscription = async () => {
    try {
      const res = await apiClient.get('/subscription/status');
      if (res.data?.data?.has_subscription && res.data?.data?.status === 'active') {
        // Déjà abonné, rediriger vers dashboard
        navigate('/dashboard');
      }
    } catch (err) {
      console.error('Error checking subscription:', err);
    }
  };

  const fetchPlans = async () => {
    try {
      const res = await apiClient.get('/subscription/plans');
      setPlans(res.data?.data || []);
    } catch (err) {
      console.error('Error fetching plans:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleSelectPlan = (plan) => {
    setSelectedPlan(plan);
    setShowPaymentModal(true);
  };

  const handleSubscribe = async () => {
    if (!selectedPlan) return;
    
    setProcessing(true);
    try {
      const res = await apiClient.post('/subscription/subscribe', {
        plan_id: selectedPlan.id,
        payment_method: paymentMethod,
        duration_months: 1,
      });

      if (res.data?.success) {
        // Rediriger vers le dashboard après abonnement réussi
        navigate('/dashboard');
      }
    } catch (err) {
      console.error('Subscription error:', err);
      const status = err.response?.status;
      const errors = err.response?.data?.errors;

      if (status === 422 && errors && typeof errors === 'object') {
        const firstField = Object.keys(errors)[0];
        const firstMessage = errors[firstField]?.[0];
        alert(firstMessage || err.response?.data?.message || 'Données invalides');
      } else {
        alert(err.response?.data?.message || 'Erreur lors de l\'abonnement');
      }
    } finally {
      setProcessing(false);
    }
  };

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  const getPlanIcon = (planName) => {
    const name = planName?.toLowerCase() || '';
    if (name.includes('premium') || name.includes('prenium')) return <Crown className="text-yellow-500" size={32} />;
    if (name.includes('standard') || name.includes('pro')) return <Zap className="text-blue-500" size={32} />;
    return <Star className="text-green-500" size={32} />;
  };

  const formatPrice = (price) => {
    return new Intl.NumberFormat('fr-FR').format(price || 0);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
      {/* Header */}
      <header className="bg-white shadow-sm">
        <div className="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">SIGEC</h1>
            <p className="text-sm text-gray-500">Bienvenue, {user?.name || 'Utilisateur'}</p>
          </div>
          <button
            onClick={handleLogout}
            className="flex items-center gap-2 text-gray-600 hover:text-red-600"
          >
            <LogOut size={20} />
            Déconnexion
          </button>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-4 py-12">
        {/* Alert */}
        <div className="bg-yellow-50 border border-yellow-200 rounded-xl p-6 mb-8 flex items-start gap-4">
          <AlertTriangle className="text-yellow-500 flex-shrink-0" size={28} />
          <div>
            <h2 className="text-lg font-semibold text-yellow-800">Abonnement requis</h2>
            <p className="text-yellow-700 mt-1">
              Pour accéder à votre espace de travail et aux fonctionnalités de SIGEC, 
              veuillez choisir un plan d'abonnement adapté à vos besoins.
            </p>
          </div>
        </div>

        {/* Plans */}
        <div className="text-center mb-10">
          <h2 className="text-3xl font-bold text-gray-900">Choisissez votre plan</h2>
          <p className="text-gray-600 mt-2">Sélectionnez le plan qui correspond à vos besoins</p>
        </div>

        <div className="grid md:grid-cols-3 gap-6">
          {plans.map((plan) => (
            <div
              key={plan.id}
              className={`bg-white rounded-2xl shadow-lg overflow-hidden transition-all hover:shadow-xl ${
                plan.name?.toLowerCase().includes('standard') ? 'ring-2 ring-blue-500 scale-105' : ''
              }`}
            >
              {plan.name?.toLowerCase().includes('standard') && (
                <div className="bg-blue-500 text-white text-center py-2 text-sm font-medium">
                  Recommandé
                </div>
              )}
              
              <div className="p-6">
                <div className="flex items-center gap-3 mb-4">
                  {getPlanIcon(plan.name)}
                  <div>
                    <h3 className="text-xl font-bold text-gray-900">{plan.display_name || plan.name}</h3>
                    <p className="text-sm text-gray-500">{plan.description}</p>
                  </div>
                </div>

                <div className="mb-6">
                  <span className="text-4xl font-bold text-gray-900">
                    {formatPrice(plan.price_monthly)}
                  </span>
                  <span className="text-gray-500 ml-2">XOF/mois</span>
                </div>

                <ul className="space-y-3 mb-6">
                  <li className="flex items-center gap-2 text-gray-600">
                    <Check className="text-green-500" size={18} />
                    <span>{plan.max_users} utilisateurs</span>
                  </li>
                  <li className="flex items-center gap-2 text-gray-600">
                    <Check className="text-green-500" size={18} />
                    <span>{plan.max_products} produits</span>
                  </li>
                  <li className="flex items-center gap-2 text-gray-600">
                    <Check className="text-green-500" size={18} />
                    <span>{plan.max_pos} points de vente</span>
                  </li>
                  <li className="flex items-center gap-2 text-gray-600">
                    <Check className="text-green-500" size={18} />
                    <span>{plan.max_warehouses} entrepôt(s)</span>
                  </li>
                  <li className="flex items-center gap-2 text-gray-600">
                    <Check className="text-green-500" size={18} />
                    <span>{plan.storage_limit_mb} MB stockage</span>
                  </li>
                  {plan.trial_days > 0 && (
                    <li className="flex items-center gap-2 text-blue-600 font-medium">
                      <Check className="text-blue-500" size={18} />
                      <span>{plan.trial_days} jours d'essai gratuit</span>
                    </li>
                  )}
                </ul>

                <button
                  onClick={() => handleSelectPlan(plan)}
                  className={`w-full py-3 rounded-lg font-semibold transition-colors ${
                    plan.name?.toLowerCase().includes('standard')
                      ? 'bg-blue-600 hover:bg-blue-700 text-white'
                      : 'bg-gray-100 hover:bg-gray-200 text-gray-800'
                  }`}
                >
                  {plan.trial_days > 0 ? 'Commencer l\'essai gratuit' : 'Choisir ce plan'}
                </button>
              </div>
            </div>
          ))}
        </div>

        {plans.length === 0 && (
          <div className="text-center py-12 bg-white rounded-xl shadow">
            <CreditCard className="mx-auto text-gray-400 mb-4" size={48} />
            <p className="text-gray-500">Aucun plan disponible pour le moment.</p>
            <p className="text-gray-400 text-sm mt-2">Contactez l'administrateur.</p>
          </div>
        )}
      </main>

      {/* Payment Modal */}
      {showPaymentModal && selectedPlan && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl max-w-md w-full p-6">
            <h3 className="text-xl font-bold text-gray-900 mb-4">
              Confirmer l'abonnement
            </h3>
            
            <div className="bg-gray-50 rounded-lg p-4 mb-6">
              <div className="flex justify-between items-center mb-2">
                <span className="text-gray-600">Plan</span>
                <span className="font-semibold">{selectedPlan.display_name}</span>
              </div>
              <div className="flex justify-between items-center mb-2">
                <span className="text-gray-600">Prix mensuel</span>
                <span className="font-semibold">{formatPrice(selectedPlan.price_monthly)} XOF</span>
              </div>
              {selectedPlan.trial_days > 0 && (
                <div className="flex justify-between items-center text-blue-600">
                  <span>Période d'essai</span>
                  <span className="font-semibold">{selectedPlan.trial_days} jours gratuits</span>
                </div>
              )}
            </div>

            <div className="mb-6">
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Mode de paiement
              </label>
              <div className="space-y-2">
                <label className="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                  <input
                    type="radio"
                    name="payment"
                    value="mobile_money"
                    checked={paymentMethod === 'mobile_money'}
                    onChange={(e) => setPaymentMethod(e.target.value)}
                    className="text-blue-600"
                  />
                  <span>Mobile Money (MTN, Moov)</span>
                </label>
                <label className="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                  <input
                    type="radio"
                    name="payment"
                    value="card"
                    checked={paymentMethod === 'card'}
                    onChange={(e) => setPaymentMethod(e.target.value)}
                    className="text-blue-600"
                  />
                  <span>Carte bancaire</span>
                </label>
                <label className="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                  <input
                    type="radio"
                    name="payment"
                    value="bank_transfer"
                    checked={paymentMethod === 'bank_transfer'}
                    onChange={(e) => setPaymentMethod(e.target.value)}
                    className="text-blue-600"
                  />
                  <span>Virement bancaire</span>
                </label>
              </div>
            </div>

            <div className="flex gap-3">
              <button
                onClick={() => setShowPaymentModal(false)}
                className="flex-1 py-3 border border-gray-300 rounded-lg font-medium hover:bg-gray-50"
              >
                Annuler
              </button>
              <button
                onClick={handleSubscribe}
                disabled={processing}
                className="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:opacity-50 flex items-center justify-center gap-2"
              >
                {processing ? (
                  <>
                    <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></div>
                    Traitement...
                  </>
                ) : (
                  <>
                    <CreditCard size={20} />
                    {selectedPlan.trial_days > 0 ? 'Démarrer l\'essai' : 'Payer maintenant'}
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
