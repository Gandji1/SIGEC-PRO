import { useEffect, useState, memo } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useTenantStore } from '../stores/tenantStore';
import { useLanguageStore } from '../stores/languageStore';
import apiClient from '../services/apiClient';
import { DollarSign, ShoppingCart, CreditCard, Clock, TrendingUp, Receipt, Play, Square, Send, Wallet, AlertCircle } from 'lucide-react';

// Skeleton
const CardSkeleton = memo(() => (
  <div className="animate-pulse bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm">
    <div className="h-4 bg-gray-200 dark:bg-slate-700 rounded w-24 mb-3"></div>
    <div className="h-8 bg-gray-200 dark:bg-slate-700 rounded w-32"></div>
  </div>
));

export default function CaissierDashboard() {
  const navigate = useNavigate();
  const { user } = useTenantStore();
  const { t } = useLanguageStore();
  const [stats, setStats] = useState({});
  const [recentSales, setRecentSales] = useState([]);
  const [currentSession, setCurrentSession] = useState(null);
  const [loading, setLoading] = useState(true);
  const [showOpenModal, setShowOpenModal] = useState(false);
  const [showCloseModal, setShowCloseModal] = useState(false);
  const [showRemittanceModal, setShowRemittanceModal] = useState(false);
  const [openingBalance, setOpeningBalance] = useState('');
  const [closingBalance, setClosingBalance] = useState('');
  const [remittanceAmount, setRemittanceAmount] = useState('');

  useEffect(() => {
    fetchDashboard();
  }, []);

  const fetchDashboard = async () => {
    try {
      const [statsRes, salesRes, sessionRes] = await Promise.all([
        apiClient.get('/dashboard/cashier/stats').catch(() => ({ data: {} })),
        apiClient.get('/approvisionnement/orders?per_page=5&status=completed').catch(() => ({ data: { data: [] } })),
        apiClient.get('/cash-register/current-session').catch(() => ({ data: { data: null } })),
      ]);
      setStats(statsRes.data?.data || statsRes.data || {});
      setRecentSales(salesRes.data?.data || []);
      setCurrentSession(sessionRes.data?.data);
    } catch (error) {
      console.error('Error:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleOpenSession = async () => {
    try {
      await apiClient.post('/cash-register/open-session', { opening_balance: parseFloat(openingBalance) || 0 });
      setShowOpenModal(false);
      setOpeningBalance('');
      fetchDashboard();
    } catch (error) {
      alert(error.response?.data?.message || 'Erreur');
    }
  };

  const handleCloseSession = async () => {
    if (!currentSession) return;
    try {
      await apiClient.post('/cash-register/close-session', {
        session_id: currentSession.id,
        closing_balance: parseFloat(closingBalance) || 0,
      });
      setShowCloseModal(false);
      setClosingBalance('');
      fetchDashboard();
    } catch (error) {
      alert(error.response?.data?.message || 'Erreur');
    }
  };

  const handleCreateRemittance = async () => {
    try {
      await apiClient.post('/cash-register/remittances', {
        amount: parseFloat(remittanceAmount),
        session_id: currentSession?.id,
      });
      setShowRemittanceModal(false);
      setRemittanceAmount('');
      fetchDashboard();
      alert('Remise envoyée au gérant!');
    } catch (error) {
      alert(error.response?.data?.message || 'Erreur');
    }
  };

  const formatCurrency = (val) => new Intl.NumberFormat('fr-FR', { 
    style: 'currency', currency: 'XAF', maximumFractionDigits: 0 
  }).format(val || 0);

  const kpis = [
    { title: 'Ventes Aujourd\'hui', value: formatCurrency(stats.today_sales), icon: DollarSign, color: 'green' },
    { title: 'Transactions', value: stats.today_transactions || 0, icon: ShoppingCart, color: 'blue' },
    { title: 'Panier Moyen', value: formatCurrency(stats.avg_basket), icon: TrendingUp, color: 'purple' },
    { title: 'En Attente', value: stats.pending_orders || 0, icon: Clock, color: 'orange' },
  ];

  const colorMap = {
    green: 'from-green-50 to-green-100 dark:from-green-900/30 dark:to-green-800/30 border-green-200 dark:border-green-700 text-green-700 dark:text-green-400',
    blue: 'from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-800/30 border-blue-200 dark:border-blue-700 text-blue-700 dark:text-blue-400',
    purple: 'from-purple-50 to-purple-100 dark:from-purple-900/30 dark:to-purple-800/30 border-purple-200 dark:border-purple-700 text-purple-700 dark:text-purple-400',
    orange: 'from-orange-50 to-orange-100 dark:from-orange-900/30 dark:to-orange-800/30 border-orange-200 dark:border-orange-700 text-orange-700 dark:text-orange-400',
  };

  return (
    <div className="space-y-4">
      {/* Header compact */}
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
        <div>
          <h1 className="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <Wallet className="text-orange-500" size={24} />
            Caisse
          </h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">Bienvenue, {user?.name}</p>
        </div>
        <button 
          onClick={() => navigate('/pos')}
          className="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2.5 rounded-xl font-medium flex items-center gap-2"
        >
          <ShoppingCart size={18} />
          Nouvelle Vente
        </button>
      </div>

      {/* Session Status */}
      <div className={`p-4 rounded-xl border-2 ${currentSession ? 'bg-green-50 dark:bg-green-900/30 border-green-300 dark:border-green-700' : 'bg-yellow-50 dark:bg-yellow-900/30 border-yellow-300 dark:border-yellow-700'}`}>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Wallet size={28} className={currentSession ? 'text-green-600' : 'text-yellow-600'} />
            <div>
              <h3 className="font-bold text-gray-900 dark:text-white">
                {currentSession ? '✅ Session Ouverte' : '⚠️ Aucune Session Active'}
              </h3>
              {currentSession ? (
                <p className="text-sm text-gray-600 dark:text-gray-300">
                  Ouverte à {new Date(currentSession.opened_at).toLocaleTimeString('fr-FR')} • 
                  Solde initial: {formatCurrency(currentSession.opening_balance)}
                </p>
              ) : (
                <p className="text-sm text-yellow-700 dark:text-yellow-400">Ouvrez une session pour commencer à encaisser</p>
              )}
            </div>
          </div>
          <div className="flex gap-2">
            {!currentSession ? (
              <button
                onClick={() => setShowOpenModal(true)}
                className="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium"
              >
                <Play size={18} /> Ouvrir Session
              </button>
            ) : (
              <>
                <button
                  onClick={() => setShowRemittanceModal(true)}
                  className="flex items-center gap-2 bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded-lg text-sm"
                >
                  <Send size={16} /> Remise
                </button>
                <button
                  onClick={() => setShowCloseModal(true)}
                  className="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg text-sm"
                >
                  <Square size={16} /> Fermer
                </button>
              </>
            )}
          </div>
        </div>

        {/* Session Stats */}
        {currentSession && (
          <div className="grid grid-cols-2 md:grid-cols-5 gap-3 mt-4 pt-4 border-t border-green-200 dark:border-green-700">
            <div className="text-center p-2 bg-white dark:bg-gray-800/50 rounded-lg">
              <p className="text-xs text-gray-500 dark:text-gray-400">Espèces</p>
              <p className="font-bold text-green-700 dark:text-green-400">{formatCurrency(currentSession.cash_sales)}</p>
            </div>
            <div className="text-center p-2 bg-white dark:bg-gray-800/50 rounded-lg">
              <p className="text-xs text-gray-500 dark:text-gray-400">Carte</p>
              <p className="font-bold text-blue-700 dark:text-blue-400">{formatCurrency(currentSession.card_sales)}</p>
            </div>
            <div className="text-center p-2 bg-white dark:bg-gray-800/50 rounded-lg">
              <p className="text-xs text-gray-500 dark:text-gray-400">Mobile</p>
              <p className="font-bold text-purple-700 dark:text-purple-400">{formatCurrency(currentSession.mobile_sales)}</p>
            </div>
            <div className="text-center p-2 bg-white dark:bg-gray-800/50 rounded-lg">
              <p className="text-xs text-gray-500 dark:text-gray-400">Sorties</p>
              <p className="font-bold text-red-700 dark:text-red-400">{formatCurrency(currentSession.cash_out)}</p>
            </div>
            <div className="text-center p-2 bg-white dark:bg-gray-800/50 rounded-lg">
              <p className="text-xs text-gray-500 dark:text-gray-400">Transactions</p>
              <p className="font-bold text-gray-700 dark:text-gray-200">{currentSession.transactions_count}</p>
            </div>
          </div>
        )}
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {loading ? (
          [1,2,3,4].map(i => <CardSkeleton key={i} />)
        ) : (
          kpis.map((kpi, idx) => (
            <div key={idx} className={`bg-gradient-to-br ${colorMap[kpi.color]} border rounded-xl p-5`}>
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-semibold mb-1">{kpi.title}</p>
                  <p className="text-2xl font-bold">{kpi.value}</p>
                </div>
                <kpi.icon size={32} className="opacity-30" />
              </div>
            </div>
          ))
        )}
      </div>

      {/* Actions Rapides */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <button 
          onClick={() => navigate('/pos')}
          className="bg-green-600 hover:bg-green-700 text-white font-bold py-6 px-4 rounded-xl transition flex items-center justify-center gap-3 text-xl"
        >
          <ShoppingCart size={28} />
          Nouvelle Vente
        </button>
        <button 
          onClick={() => navigate('/pos/orders')}
          className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-6 px-4 rounded-xl transition flex items-center justify-center gap-3 text-xl"
        >
          <Receipt size={28} />
          Historique
        </button>
        <button 
          onClick={() => navigate('/pos/close')}
          className="bg-orange-600 hover:bg-orange-700 text-white font-bold py-6 px-4 rounded-xl transition flex items-center justify-center gap-3 text-xl"
        >
          <CreditCard size={28} />
          Clôture Caisse
        </button>
      </div>

      {/* Ventes Récentes */}
      <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h2 className="text-lg font-bold text-gray-900 dark:text-white mb-4">📋 Dernières Ventes</h2>
        {recentSales.length === 0 ? (
          <p className="text-gray-500 dark:text-gray-400 text-center py-8">Aucune vente aujourd'hui</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th className="px-4 py-2 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Référence</th>
                  <th className="px-4 py-2 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Client</th>
                  <th className="px-4 py-2 text-right text-sm font-semibold text-gray-600 dark:text-gray-300">Montant</th>
                  <th className="px-4 py-2 text-center text-sm font-semibold text-gray-600 dark:text-gray-300">Paiement</th>
                  <th className="px-4 py-2 text-center text-sm font-semibold text-gray-600 dark:text-gray-300">Heure</th>
                </tr>
              </thead>
              <tbody className="divide-y dark:divide-gray-700">
                {recentSales.map((sale) => (
                  <tr key={sale.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td className="px-4 py-3 font-mono font-medium text-gray-900 dark:text-white">{sale.reference}</td>
                    <td className="px-4 py-3 text-gray-900 dark:text-white">{sale.customer?.name || 'Comptoir'}</td>
                    <td className="px-4 py-3 text-right font-bold text-green-600 dark:text-green-400">{formatCurrency(sale.total)}</td>
                    <td className="px-4 py-3 text-center">
                      <span className="px-2 py-1 bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-400 rounded text-xs font-medium">
                        {sale.payment_method || 'Cash'}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-center text-gray-500 dark:text-gray-400 text-sm">
                      {new Date(sale.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Raccourcis Clavier */}
      <div className="bg-gray-50 dark:bg-gray-800 rounded-xl p-4">
        <h3 className="font-semibold text-gray-700 dark:text-gray-300 mb-2">⌨️ Raccourcis Clavier</h3>
        <div className="flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-400">
          <span><kbd className="px-2 py-1 bg-white dark:bg-gray-700 dark:text-white rounded border dark:border-gray-600">F2</kbd> Nouvelle vente</span>
          <span><kbd className="px-2 py-1 bg-white dark:bg-gray-700 dark:text-white rounded border dark:border-gray-600">F4</kbd> Paiement</span>
          <span><kbd className="px-2 py-1 bg-white dark:bg-gray-700 dark:text-white rounded border dark:border-gray-600">F8</kbd> Annuler</span>
          <span><kbd className="px-2 py-1 bg-white dark:bg-gray-700 dark:text-white rounded border dark:border-gray-600">F12</kbd> Clôture</span>
        </div>
      </div>

      {/* Open Session Modal */}
      {showOpenModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md">
            <h3 className="text-xl font-bold mb-4 flex items-center gap-2 text-gray-900 dark:text-white"><Play size={20} className="text-green-600" /> Ouvrir la Caisse</h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fond de caisse initial</label>
                <input
                  type="number"
                  value={openingBalance}
                  onChange={(e) => setOpeningBalance(e.target.value)}
                  placeholder="0"
                  className="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-lg"
                  autoFocus
                />
              </div>
              <div className="flex gap-2">
                <button onClick={() => setShowOpenModal(false)} className="flex-1 px-4 py-2 border dark:border-gray-600 dark:text-gray-300 rounded-lg">Annuler</button>
                <button onClick={handleOpenSession} className="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg font-medium">Ouvrir</button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Close Session Modal */}
      {showCloseModal && currentSession && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md">
            <h3 className="text-xl font-bold mb-4 flex items-center gap-2 text-gray-900 dark:text-white"><Square size={20} className="text-red-600" /> Fermer la Caisse</h3>
            <div className="space-y-4">
              <div className="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                <p className="text-sm text-blue-700 dark:text-blue-400">
                  Solde attendu: <strong>{formatCurrency(
                    parseFloat(currentSession.opening_balance || 0) +
                    parseFloat(currentSession.cash_sales || 0) +
                    parseFloat(currentSession.cash_in || 0) -
                    parseFloat(currentSession.cash_out || 0)
                  )}</strong>
                </p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Montant réel compté</label>
                <input
                  type="number"
                  value={closingBalance}
                  onChange={(e) => setClosingBalance(e.target.value)}
                  placeholder="0"
                  className="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-lg"
                  autoFocus
                />
              </div>
              <div className="flex gap-2">
                <button onClick={() => setShowCloseModal(false)} className="flex-1 px-4 py-2 border dark:border-gray-600 dark:text-gray-300 rounded-lg">Annuler</button>
                <button onClick={handleCloseSession} className="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg font-medium">Fermer</button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Remittance Modal */}
      {showRemittanceModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md">
            <h3 className="text-xl font-bold mb-4 flex items-center gap-2 text-gray-900 dark:text-white"><Send size={20} className="text-purple-600" /> Remise au Gérant</h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Montant à remettre</label>
                <input
                  type="number"
                  value={remittanceAmount}
                  onChange={(e) => setRemittanceAmount(e.target.value)}
                  placeholder="0"
                  className="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2 text-lg"
                  autoFocus
                />
              </div>
              <div className="flex gap-2">
                <button onClick={() => setShowRemittanceModal(false)} className="flex-1 px-4 py-2 border dark:border-gray-600 dark:text-gray-300 rounded-lg">Annuler</button>
                <button onClick={handleCreateRemittance} className="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg font-medium">Envoyer</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
