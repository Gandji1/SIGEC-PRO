import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import apiClient from '../services/apiClient';
import { CreditCard, Plus, Edit, Check, X, Calendar, Users, Package, Building2 } from 'lucide-react';

export default function SubscriptionsPage() {
  const navigate = useNavigate();
  const [plans, setPlans] = useState([]);
  const [subscriptions, setSubscriptions] = useState([]);
  const [payments, setPayments] = useState([]);
  const [revenueStats, setRevenueStats] = useState({});
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState('plans');
  const [showPlanModal, setShowPlanModal] = useState(false);
  const [showPaymentModal, setShowPaymentModal] = useState(false);
  const [editingPlan, setEditingPlan] = useState(null);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const [planForm, setPlanForm] = useState({
    name: '',
    display_name: '',
    description: '',
    price_monthly: 0,
    price_yearly: 0,
    max_users: 5,
    max_pos: 1,
    max_products: 100,
    max_warehouses: 1,
    storage_limit_mb: 500,
    trial_days: 14,
  });

  const [paymentForm, setPaymentForm] = useState({
    tenant_id: '',
    amount: 0,
    payment_method: 'momo',
    payment_reference: '',
    description: '',
    extend_subscription_months: 1,
  });

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    setLoading(true);
    try {
      const [plansRes, subsRes, paymentsRes, statsRes] = await Promise.all([
        apiClient.get('/superadmin/plans').catch(() => ({ data: { data: [] } })),
        apiClient.get('/superadmin/subscriptions?per_page=50').catch(() => ({ data: { data: [] } })),
        apiClient.get('/superadmin/payments?per_page=50').catch(() => ({ data: { data: [] } })),
        apiClient.get('/superadmin/revenue-stats').catch(() => ({ data: { data: {} } })),
      ]);
      setPlans(plansRes.data?.data || []);
      setSubscriptions(subsRes.data?.data || []);
      setPayments(paymentsRes.data?.data || []);
      setRevenueStats(statsRes.data?.data || {});
    } catch (err) {
      setError('Erreur de chargement');
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (val) => new Intl.NumberFormat('fr-FR', { 
    style: 'currency', currency: 'XOF', maximumFractionDigits: 0 
  }).format(val || 0);

  const handleSavePlan = async (e) => {
    e.preventDefault();
    setError('');
    try {
      if (editingPlan) {
        await apiClient.put(`/superadmin/plans/${editingPlan.id}`, planForm);
        setSuccess('Plan modifié');
      } else {
        await apiClient.post('/superadmin/plans', planForm);
        setSuccess('Plan créé');
      }
      setShowPlanModal(false);
      setEditingPlan(null);
      fetchData();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur');
    }
  };

  const handleRecordPayment = async (e) => {
    e.preventDefault();
    setError('');
    try {
      await apiClient.post('/superadmin/payments', paymentForm);
      setSuccess('Paiement enregistré');
      setShowPaymentModal(false);
      setPaymentForm({
        tenant_id: '',
        amount: 0,
        payment_method: 'momo',
        payment_reference: '',
        description: '',
        extend_subscription_months: 1,
      });
      fetchData();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur');
    }
  };

  const openEditPlan = (plan) => {
    setEditingPlan(plan);
    setPlanForm({
      name: plan.name,
      display_name: plan.display_name,
      description: plan.description || '',
      price_monthly: plan.price_monthly,
      price_yearly: plan.price_yearly || 0,
      max_users: plan.max_users,
      max_pos: plan.max_pos,
      max_products: plan.max_products,
      max_warehouses: plan.max_warehouses,
      storage_limit_mb: plan.storage_limit_mb,
      trial_days: plan.trial_days,
    });
    setShowPlanModal(true);
  };

  const tabs = [
    { id: 'plans', label: 'Plans', icon: Package },
    { id: 'subscriptions', label: 'Abonnements', icon: Calendar },
    { id: 'payments', label: 'Paiements', icon: CreditCard },
  ];

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">💳 Abonnements & Paiements</h1>
          <p className="text-gray-600 mt-1">Gestion des plans et revenus de la plateforme</p>
        </div>
        <div className="flex gap-3">
          <button 
            onClick={() => { setEditingPlan(null); setPlanForm({ name: '', display_name: '', description: '', price_monthly: 0, price_yearly: 0, max_users: 5, max_pos: 1, max_products: 100, max_warehouses: 1, storage_limit_mb: 500, trial_days: 14 }); setShowPlanModal(true); }}
            className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg"
          >
            <Plus size={18} /> Nouveau Plan
          </button>
          <button 
            onClick={() => setShowPaymentModal(true)}
            className="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg"
          >
            <CreditCard size={18} /> Enregistrer Paiement
          </button>
        </div>
      </div>

      {/* Alerts */}
      {error && <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{error}</div>}
      {success && <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{success}</div>}

      {/* Revenue Stats */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-xl p-5">
          <p className="text-sm font-semibold text-green-700 mb-1">Revenus {new Date().getFullYear()}</p>
          <p className="text-2xl font-bold text-green-800">{formatCurrency(revenueStats.total)}</p>
        </div>
        <div className="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-xl p-5">
          <p className="text-sm font-semibold text-blue-700 mb-1">Plans Actifs</p>
          <p className="text-2xl font-bold text-blue-800">{plans.filter(p => p.is_active).length}</p>
        </div>
        <div className="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-xl p-5">
          <p className="text-sm font-semibold text-purple-700 mb-1">Abonnements Actifs</p>
          <p className="text-2xl font-bold text-purple-800">{subscriptions.filter(s => s.status === 'active').length}</p>
        </div>
        <div className="bg-gradient-to-br from-orange-50 to-orange-100 border border-orange-200 rounded-xl p-5">
          <p className="text-sm font-semibold text-orange-700 mb-1">En Essai</p>
          <p className="text-2xl font-bold text-orange-800">{subscriptions.filter(s => s.status === 'trial').length}</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b">
        {tabs.map(tab => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={`flex items-center gap-2 px-4 py-3 font-medium transition ${
              activeTab === tab.id 
                ? 'text-blue-600 border-b-2 border-blue-600' 
                : 'text-gray-500 hover:text-gray-700'
            }`}
          >
            <tab.icon size={18} />
            {tab.label}
          </button>
        ))}
      </div>

      {/* Plans Tab */}
      {activeTab === 'plans' && (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {loading ? (
            [1,2,3].map(i => <div key={i} className="animate-pulse bg-gray-100 h-64 rounded-xl"></div>)
          ) : plans.length === 0 ? (
            <p className="col-span-3 text-center text-gray-500 py-8">Aucun plan. Créez-en un!</p>
          ) : (
            plans.map(plan => (
              <div key={plan.id} className={`bg-white rounded-xl shadow-sm border-2 p-6 ${plan.is_active ? 'border-blue-200' : 'border-gray-200 opacity-60'}`}>
                <div className="flex justify-between items-start mb-4">
                  <div>
                    <h3 className="text-xl font-bold text-gray-900">{plan.display_name}</h3>
                    <p className="text-sm text-gray-500">{plan.name}</p>
                  </div>
                  <button onClick={() => openEditPlan(plan)} className="text-blue-600 hover:text-blue-800">
                    <Edit size={18} />
                  </button>
                </div>
                <p className="text-3xl font-bold text-blue-600 mb-4">
                  {formatCurrency(plan.price_monthly)}<span className="text-sm text-gray-500">/mois</span>
                </p>
                {plan.description && <p className="text-gray-600 text-sm mb-4">{plan.description}</p>}
                <ul className="space-y-2 text-sm">
                  <li className="flex items-center gap-2"><Users size={16} className="text-gray-400" /> {plan.max_users} utilisateurs</li>
                  <li className="flex items-center gap-2"><Package size={16} className="text-gray-400" /> {plan.max_pos} POS</li>
                  <li className="flex items-center gap-2"><Building2 size={16} className="text-gray-400" /> {plan.max_warehouses} entrepôts</li>
                  <li className="flex items-center gap-2"><Package size={16} className="text-gray-400" /> {plan.max_products} produits</li>
                </ul>
                <div className="mt-4 pt-4 border-t flex justify-between text-sm text-gray-500">
                  <span>{plan.trial_days} jours d'essai</span>
                  <span>{plan.subscriptions_count || 0} abonnés</span>
                </div>
              </div>
            ))
          )}
        </div>
      )}

      {/* Subscriptions Tab */}
      {activeTab === 'subscriptions' && (
        <div className="bg-white rounded-xl shadow-sm overflow-hidden">
          <table className="w-full">
            <thead className="bg-gray-50 border-b">
              <tr>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">Tenant</th>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">Plan</th>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">Statut</th>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">Début</th>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">Fin</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {subscriptions.map(sub => (
                <tr key={sub.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-medium">{sub.tenant?.name || `Tenant #${sub.tenant_id}`}</td>
                  <td className="px-4 py-3">{sub.plan?.display_name || sub.plan?.name || '-'}</td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-1 rounded text-xs font-medium ${
                      sub.status === 'active' ? 'bg-green-100 text-green-700' :
                      sub.status === 'trial' ? 'bg-blue-100 text-blue-700' :
                      sub.status === 'expired' ? 'bg-red-100 text-red-700' :
                      'bg-gray-100 text-gray-700'
                    }`}>
                      {sub.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-500">
                    {sub.starts_at ? new Date(sub.starts_at).toLocaleDateString('fr-FR') : '-'}
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-500">
                    {sub.ends_at ? new Date(sub.ends_at).toLocaleDateString('fr-FR') : '-'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Payments Tab */}
      {activeTab === 'payments' && (
        <div className="bg-white rounded-xl shadow-sm overflow-hidden">
          <table className="w-full">
            <thead className="bg-gray-50 border-b">
              <tr>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">Date</th>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">Tenant</th>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">Montant</th>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">Méthode</th>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">Statut</th>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">Référence</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {payments.map(payment => (
                <tr key={payment.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 text-sm">
                    {payment.paid_at ? new Date(payment.paid_at).toLocaleDateString('fr-FR') : new Date(payment.created_at).toLocaleDateString('fr-FR')}
                  </td>
                  <td className="px-4 py-3 font-medium">{payment.tenant?.name || '-'}</td>
                  <td className="px-4 py-3 font-bold text-green-600">{formatCurrency(payment.amount)}</td>
                  <td className="px-4 py-3 text-sm">{payment.payment_method}</td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-1 rounded text-xs font-medium ${
                      payment.status === 'completed' ? 'bg-green-100 text-green-700' :
                      payment.status === 'pending' ? 'bg-yellow-100 text-yellow-700' :
                      'bg-red-100 text-red-700'
                    }`}>
                      {payment.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-500 font-mono">{payment.payment_reference || '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Plan Modal */}
      {showPlanModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
            <div className="px-6 py-4 border-b flex justify-between items-center">
              <h2 className="text-xl font-bold">{editingPlan ? 'Modifier le Plan' : 'Nouveau Plan'}</h2>
              <button onClick={() => setShowPlanModal(false)} className="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <form onSubmit={handleSavePlan} className="p-6 space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Code</label>
                  <input type="text" value={planForm.name} onChange={e => setPlanForm({...planForm, name: e.target.value})} className="w-full border rounded-lg px-3 py-2" required disabled={!!editingPlan} />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Nom affiché</label>
                  <input type="text" value={planForm.display_name} onChange={e => setPlanForm({...planForm, display_name: e.target.value})} className="w-full border rounded-lg px-3 py-2" required />
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea value={planForm.description} onChange={e => setPlanForm({...planForm, description: e.target.value})} className="w-full border rounded-lg px-3 py-2" rows={2} />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Prix mensuel (XOF)</label>
                  <input type="number" value={planForm.price_monthly} onChange={e => setPlanForm({...planForm, price_monthly: parseFloat(e.target.value)})} className="w-full border rounded-lg px-3 py-2" required />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Prix annuel (XOF)</label>
                  <input type="number" value={planForm.price_yearly} onChange={e => setPlanForm({...planForm, price_yearly: parseFloat(e.target.value)})} className="w-full border rounded-lg px-3 py-2" />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Max Utilisateurs</label>
                  <input type="number" value={planForm.max_users} onChange={e => setPlanForm({...planForm, max_users: parseInt(e.target.value)})} className="w-full border rounded-lg px-3 py-2" required />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Max POS</label>
                  <input type="number" value={planForm.max_pos} onChange={e => setPlanForm({...planForm, max_pos: parseInt(e.target.value)})} className="w-full border rounded-lg px-3 py-2" required />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Max Produits</label>
                  <input type="number" value={planForm.max_products} onChange={e => setPlanForm({...planForm, max_products: parseInt(e.target.value)})} className="w-full border rounded-lg px-3 py-2" required />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Max Entrepôts</label>
                  <input type="number" value={planForm.max_warehouses} onChange={e => setPlanForm({...planForm, max_warehouses: parseInt(e.target.value)})} className="w-full border rounded-lg px-3 py-2" required />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Stockage (MB)</label>
                  <input type="number" value={planForm.storage_limit_mb} onChange={e => setPlanForm({...planForm, storage_limit_mb: parseInt(e.target.value)})} className="w-full border rounded-lg px-3 py-2" required />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Jours d'essai</label>
                  <input type="number" value={planForm.trial_days} onChange={e => setPlanForm({...planForm, trial_days: parseInt(e.target.value)})} className="w-full border rounded-lg px-3 py-2" required />
                </div>
              </div>
              <div className="flex gap-3 pt-4">
                <button type="button" onClick={() => setShowPlanModal(false)} className="flex-1 border text-gray-700 py-2 rounded-lg">Annuler</button>
                <button type="submit" className="flex-1 bg-blue-600 text-white py-2 rounded-lg">{editingPlan ? 'Modifier' : 'Créer'}</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Payment Modal */}
      {showPaymentModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl max-w-md w-full">
            <div className="px-6 py-4 border-b flex justify-between items-center">
              <h2 className="text-xl font-bold">Enregistrer un Paiement</h2>
              <button onClick={() => setShowPaymentModal(false)} className="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <form onSubmit={handleRecordPayment} className="p-6 space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Tenant ID</label>
                <input type="number" value={paymentForm.tenant_id} onChange={e => setPaymentForm({...paymentForm, tenant_id: e.target.value})} className="w-full border rounded-lg px-3 py-2" required />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Montant (XOF)</label>
                <input type="number" value={paymentForm.amount} onChange={e => setPaymentForm({...paymentForm, amount: parseFloat(e.target.value)})} className="w-full border rounded-lg px-3 py-2" required />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Méthode de paiement</label>
                <select value={paymentForm.payment_method} onChange={e => setPaymentForm({...paymentForm, payment_method: e.target.value})} className="w-full border rounded-lg px-3 py-2">
                  <option value="momo">Mobile Money</option>
                  <option value="bank_transfer">Virement Bancaire</option>
                  <option value="cash">Espèces</option>
                  <option value="card">Carte</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Référence</label>
                <input type="text" value={paymentForm.payment_reference} onChange={e => setPaymentForm({...paymentForm, payment_reference: e.target.value})} className="w-full border rounded-lg px-3 py-2" />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Prolonger abonnement (mois)</label>
                <input type="number" value={paymentForm.extend_subscription_months} onChange={e => setPaymentForm({...paymentForm, extend_subscription_months: parseInt(e.target.value)})} className="w-full border rounded-lg px-3 py-2" min={0} />
              </div>
              <div className="flex gap-3 pt-4">
                <button type="button" onClick={() => setShowPaymentModal(false)} className="flex-1 border text-gray-700 py-2 rounded-lg">Annuler</button>
                <button type="submit" className="flex-1 bg-green-600 text-white py-2 rounded-lg">Enregistrer</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
