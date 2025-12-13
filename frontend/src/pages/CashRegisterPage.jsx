import { useState, useEffect, useCallback } from 'react';
import apiClient from '../services/apiClient';
import { usePermission } from '../hooks/usePermission';
import { useLanguageStore } from '../stores/languageStore';
import { 
  DollarSign, TrendingUp, TrendingDown, RefreshCw, Calendar, 
  Play, Square, Send, CheckCircle, Clock, AlertTriangle,
  Wallet, ArrowUpRight, ArrowDownRight, FileText
} from 'lucide-react';

export default function CashRegisterPage() {
  const { can } = usePermission();
  const { t } = useLanguageStore();
  const [activeTab, setActiveTab] = useState('session');
  const [loading, setLoading] = useState(true);
  const [currentSession, setCurrentSession] = useState(null);
  const [sessions, setSessions] = useState([]);
  const [movements, setMovements] = useState([]);
  const [remittances, setRemittances] = useState([]);
  const [pendingRemittances, setPendingRemittances] = useState([]);
  const [managerDashboard, setManagerDashboard] = useState(null);
  const [dateFrom, setDateFrom] = useState(new Date().toISOString().split('T')[0]);
  const [dateTo, setDateTo] = useState(new Date().toISOString().split('T')[0]);

  // Modals
  const [showOpenModal, setShowOpenModal] = useState(false);
  const [showCloseModal, setShowCloseModal] = useState(false);
  const [showMovementModal, setShowMovementModal] = useState(false);
  const [showRemittanceModal, setShowRemittanceModal] = useState(false);

  // Form data
  const [openingBalance, setOpeningBalance] = useState('');
  const [closingBalance, setClosingBalance] = useState('');
  const [closingNotes, setClosingNotes] = useState('');
  const [movementData, setMovementData] = useState({ type: 'out', category: 'expense', amount: '', description: '' });
  const [remittanceAmount, setRemittanceAmount] = useState('');
  const [remittanceNotes, setRemittanceNotes] = useState('');

  const formatCurrency = (val) => new Intl.NumberFormat('fr-FR', { 
    style: 'currency', currency: 'XAF', maximumFractionDigits: 0 
  }).format(val || 0);

  const [salesStats, setSalesStats] = useState({ totals: { ca: 0, cmv: 0, benefice_brut: 0 }, daily: [] });

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      // Récupérer la session en cours
      const sessionRes = await apiClient.get('/cash-register/current-session').catch(() => ({ data: { data: null } }));
      const session = sessionRes.data?.data;
      setCurrentSession(session);

      // Récupérer les mouvements et statistiques de vente avec CMV
      const fromDate = `${dateFrom} 00:00:00`;
      const toDate = `${dateTo} 23:59:59`;
      const sevenDaysAgo = new Date(Date.now() - 7*24*60*60*1000).toISOString().split('T')[0];
      
      let movementsUrl = `/cash-register/movements?from=${fromDate}&to=${toDate}`;
      if (session?.id) {
        movementsUrl += `&session_id=${session.id}`;
      }
      
      const [movementsRes, remittancesRes, salesStatsRes] = await Promise.all([
        apiClient.get(movementsUrl).catch(() => ({ data: { data: [] } })),
        apiClient.get('/cash-register/remittances/pending').catch(() => ({ data: { data: [] } })),
        // Statistiques de vente avec CMV (Charges Variables = Coût des Marchandises Vendues)
        apiClient.get(`/pos/orders/sales-stats?from=${sevenDaysAgo} 00:00:00&to=${toDate}`).catch(() => ({ 
          data: { totals: { ca: 0, cmv: 0, benefice_brut: 0 }, daily: [] } 
        })),
      ]);
      
      setMovements(movementsRes.data?.data || movementsRes.data || []);
      setPendingRemittances(remittancesRes.data?.data || []);
      setSalesStats(salesStatsRes.data || { totals: { ca: 0, cmv: 0, benefice_brut: 0 }, daily: [] });

      // Manager dashboard
      if (can('pos.supervise')) {
        const dashRes = await apiClient.get('/cash-register/manager-dashboard').catch(() => ({ data: { data: null } }));
        setManagerDashboard(dashRes.data?.data);
      }
    } catch (error) {
      console.error('Error fetching cash register data:', error);
    } finally {
      setLoading(false);
    }
  }, [dateFrom, dateTo, can]);

  useEffect(() => {
    fetchData();
    // Rafraîchissement automatique toutes les 10 secondes pour temps réel
    const interval = setInterval(fetchData, 10000);
    return () => clearInterval(interval);
  }, [fetchData]);

  const handleOpenSession = async () => {
    try {
      await apiClient.post('/cash-register/open-session', { opening_balance: parseFloat(openingBalance) });
      setShowOpenModal(false);
      setOpeningBalance('');
      fetchData();
    } catch (error) {
      alert(error.response?.data?.message || 'Erreur');
    }
  };

  const handleCloseSession = async () => {
    if (!currentSession) return;
    try {
      await apiClient.post('/cash-register/close-session', {
        session_id: currentSession.id,
        closing_balance: parseFloat(closingBalance),
        notes: closingNotes,
      });
      setShowCloseModal(false);
      setClosingBalance('');
      setClosingNotes('');
      fetchData();
    } catch (error) {
      alert(error.response?.data?.message || 'Erreur');
    }
  };

  const handleRecordMovement = async () => {
    try {
      await apiClient.post('/cash-register/movements', {
        ...movementData,
        amount: parseFloat(movementData.amount),
        session_id: currentSession?.id,
      });
      setShowMovementModal(false);
      setMovementData({ type: 'out', category: 'expense', amount: '', description: '' });
      fetchData();
    } catch (error) {
      alert(error.response?.data?.message || 'Erreur');
    }
  };

  const handleCreateRemittance = async () => {
    try {
      await apiClient.post('/cash-register/remittances', {
        amount: parseFloat(remittanceAmount),
        session_id: currentSession?.id,
        notes: remittanceNotes,
      });
      setShowRemittanceModal(false);
      setRemittanceAmount('');
      setRemittanceNotes('');
      fetchData();
    } catch (error) {
      alert(error.response?.data?.message || 'Erreur');
    }
  };

  const handleReceiveRemittance = async (id) => {
    try {
      await apiClient.post(`/cash-register/remittances/${id}/receive`);
      fetchData();
    } catch (error) {
      alert(error.response?.data?.message || 'Erreur');
    }
  };

  const tabs = [
    { id: 'session', label: '💰 Ma Caisse', icon: Wallet },
    { id: 'movements', label: '📊 Mouvements', icon: TrendingUp },
    { id: 'remittances', label: '📤 Remises', icon: Send },
    ...(can('pos.supervise') ? [{ id: 'manager', label: '👔 Gérant', icon: FileText }] : []),
  ];

  // Données pour le graphique d'évolution - Utiliser les stats de vente avec CMV
  // Charges Variables = Coût des Marchandises Vendues (CMV)
  const chartPoints = (salesStats.daily || []).map(d => ({
    date: d.date_formatted,
    ca: d.ca || 0,
    charges: d.cmv || 0, // CMV = Charges Variables
    benefice: d.benefice_brut || 0,
    count: d.transactions || 0
  }));
  
  // Totaux de la session actuelle avec CMV
  const sessionCA = currentSession ? 
    parseFloat(currentSession.cash_sales || 0) + parseFloat(currentSession.card_sales || 0) + parseFloat(currentSession.mobile_sales || 0) 
    : 0;
  
  // Utiliser les stats de vente pour les charges variables (CMV)
  const sessionTotals = {
    ca: salesStats.totals?.ca || sessionCA,
    charges: salesStats.totals?.cmv || 0, // CMV = Charges Variables
    benefice: salesStats.totals?.benefice_brut || 0,
    transactions: currentSession?.transactions_count || salesStats.totals?.transactions || 0
  };

  return (
    <div className="p-6 space-y-4">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">💰 Caisse</h1>
          <p className="text-gray-600 dark:text-gray-300 text-sm">Gestion des encaissements et remises</p>
        </div>
        <button onClick={fetchData} className="p-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg">
          <RefreshCw size={18} className={loading ? 'animate-spin' : ''} />
        </button>
      </div>

      {/* Layout 40% / 60% */}
      <div className="grid grid-cols-1 lg:grid-cols-5 gap-4">
        {/* Colonne gauche - 40% (2/5) - Caisse */}
        <div className="lg:col-span-2 space-y-4">
          {/* Session Status Card - Compact */}
          <div className={`p-3 rounded-xl border-2 ${currentSession ? 'bg-green-50 dark:bg-green-900/30 border-green-200 dark:border-green-700' : 'bg-yellow-50 dark:bg-yellow-900/30 border-yellow-200 dark:border-yellow-700'}`}>
            <div className="flex items-center justify-between mb-3">
              <div className="flex items-center gap-2">
                {currentSession ? (
                  <CheckCircle className="text-green-600" size={20} />
                ) : (
                  <AlertTriangle className="text-yellow-600" size={20} />
                )}
                <div>
                  <h3 className="font-bold text-gray-900 dark:text-white text-sm">
                    {currentSession ? 'Session Ouverte' : 'Aucune Session'}
                  </h3>
                  {currentSession && (
                    <p className="text-xs text-gray-600 dark:text-gray-300">
                      {new Date(currentSession.opened_at).toLocaleTimeString('fr-FR')} • {formatCurrency(currentSession.opening_balance)}
                    </p>
                  )}
                </div>
              </div>
            </div>
            
            {/* Boutons actions */}
            <div className="flex flex-wrap gap-2">
              {!currentSession ? (
                <button onClick={() => setShowOpenModal(true)} className="flex items-center gap-1 bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-sm">
                  <Play size={14} /> Ouvrir
                </button>
              ) : (
                <>
                  <button onClick={() => setShowMovementModal(true)} className="flex items-center gap-1 bg-blue-600 hover:bg-blue-700 text-white px-2 py-1.5 rounded-lg text-xs">
                    <DollarSign size={12} /> Mouvement
                  </button>
                  <button onClick={() => setShowRemittanceModal(true)} className="flex items-center gap-1 bg-purple-600 hover:bg-purple-700 text-white px-2 py-1.5 rounded-lg text-xs">
                    <Send size={12} /> Remise
                  </button>
                  <button onClick={() => setShowCloseModal(true)} className="flex items-center gap-1 bg-red-600 hover:bg-red-700 text-white px-2 py-1.5 rounded-lg text-xs">
                    <Square size={12} /> Fermer
                  </button>
                </>
              )}
            </div>

            {/* Session Stats - Compact */}
            {currentSession && (
              <div className="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-green-200 dark:border-green-700">
                <div className="text-center p-2 bg-white/50 dark:bg-gray-800/50 rounded-lg">
                  <p className="text-[10px] text-gray-500 dark:text-gray-400">Espèces</p>
                  <p className="font-bold text-green-700 dark:text-green-400 text-sm">{formatCurrency(currentSession.cash_sales)}</p>
                </div>
                <div className="text-center p-2 bg-white/50 dark:bg-gray-800/50 rounded-lg">
                  <p className="text-[10px] text-gray-500 dark:text-gray-400">Carte</p>
                  <p className="font-bold text-blue-700 dark:text-blue-400 text-sm">{formatCurrency(currentSession.card_sales)}</p>
                </div>
                <div className="text-center p-2 bg-white/50 dark:bg-gray-800/50 rounded-lg">
                  <p className="text-[10px] text-gray-500 dark:text-gray-400">Mobile</p>
                  <p className="font-bold text-purple-700 dark:text-purple-400 text-sm">{formatCurrency(currentSession.mobile_sales)}</p>
                </div>
                <div className="text-center p-2 bg-white/50 dark:bg-gray-800/50 rounded-lg">
                  <p className="text-[10px] text-gray-500 dark:text-gray-400">Sorties</p>
                  <p className="font-bold text-red-700 dark:text-red-400 text-sm">{formatCurrency(currentSession.cash_out)}</p>
                </div>
                <div className="text-center p-2 bg-white/50 dark:bg-gray-800/50 rounded-lg">
                  <p className="text-[10px] text-gray-500 dark:text-gray-400">Transactions</p>
                  <p className="font-bold text-gray-700 dark:text-gray-200 text-sm">{currentSession.transactions_count}</p>
                </div>
                <div className="text-center p-2 bg-blue-100 dark:bg-blue-900/50 rounded-lg">
                  <p className="text-[10px] text-gray-500 dark:text-gray-400">Solde</p>
                  <p className="font-bold text-blue-700 dark:text-blue-400 text-sm">
                    {formatCurrency(parseFloat(currentSession.opening_balance) + parseFloat(currentSession.cash_sales || 0) - parseFloat(currentSession.cash_out || 0))}
                  </p>
                </div>
              </div>
            )}
          </div>

          {/* Tabs - Compact */}
          <div className="flex gap-1 border-b dark:border-gray-700 text-sm">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`flex items-center gap-1 px-3 py-2 font-medium transition border-b-2 ${
                  activeTab === tab.id ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'
                }`}
              >
                <tab.icon size={14} />
                <span className="hidden sm:inline">{tab.label}</span>
              </button>
            ))}
          </div>

          {/* Tab Content - Compact */}
          {activeTab === 'session' && currentSession && (
            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
              <h3 className="font-bold text-sm mb-3 text-gray-900 dark:text-white">Détails Session</h3>
              <div className="grid grid-cols-2 gap-2 text-sm">
                <div className="p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                  <p className="text-xs text-gray-500 dark:text-gray-400">Ouvert par</p>
                  <p className="font-medium text-gray-900 dark:text-white">{currentSession.opened_by_user?.name || '-'}</p>
                </div>
                <div className="p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                  <p className="text-xs text-gray-500 dark:text-gray-400">Heure</p>
                  <p className="font-medium text-gray-900 dark:text-white">{new Date(currentSession.opened_at).toLocaleString('fr-FR')}</p>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Colonne droite - 60% (3/5) - Graphique et Session en temps réel */}
        <div className="lg:col-span-3 space-y-4">
          {/* Session actuelle - Temps réel */}
          {currentSession && (
            <div className="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-4 text-white">
              <div className="flex items-center justify-between mb-3">
                <h3 className="font-bold text-base">🔴 Session en cours</h3>
                <span className="flex items-center gap-1 text-xs bg-white/20 px-2 py-1 rounded-full">
                  <span className="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                  Temps réel
                </span>
              </div>
              <div className="grid grid-cols-4 gap-3">
                <div className="bg-white/10 rounded-lg p-3 text-center">
                  <p className="text-xs text-white/70">Chiffre d'Affaires</p>
                  <p className="font-bold text-lg">{formatCurrency(sessionTotals.ca)}</p>
                </div>
                <div className="bg-white/10 rounded-lg p-3 text-center">
                  <p className="text-xs text-white/70">Charges Var. (CMV)</p>
                  <p className="font-bold text-lg">{formatCurrency(sessionTotals.charges)}</p>
                </div>
                <div className="bg-white/10 rounded-lg p-3 text-center">
                  <p className="text-xs text-white/70">Bénéfice Brut</p>
                  <p className="font-bold text-lg">{formatCurrency(sessionTotals.benefice)}</p>
                </div>
                <div className="bg-white/10 rounded-lg p-3 text-center">
                  <p className="text-xs text-white/70">Transactions</p>
                  <p className="font-bold text-lg">{sessionTotals.transactions}</p>
                </div>
              </div>
            </div>
          )}

          {/* Graphique d'évolution */}
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border border-gray-200 dark:border-gray-700">
            <h3 className="font-bold text-base mb-2 text-gray-900 dark:text-white">📈 Évolution Financière (7 jours)</h3>
            <div className="flex gap-4 mb-3 text-xs text-gray-700 dark:text-gray-300">
              <span className="flex items-center gap-1"><span className="w-2.5 h-2.5 bg-blue-500 rounded-full"></span> CA</span>
              <span className="flex items-center gap-1"><span className="w-2.5 h-2.5 bg-red-500 rounded-full"></span> CMV (Charges Var.)</span>
              <span className="flex items-center gap-1"><span className="w-2.5 h-2.5 bg-green-500 rounded-full"></span> Bénéfice Brut</span>
            </div>
            
            {chartPoints.length > 0 ? (
              <div className="relative h-44">
                {/* Grille */}
                <div className="absolute inset-0 flex flex-col justify-between">
                  {[0,1,2,3,4].map(i => <div key={i} className="border-b border-gray-100 dark:border-gray-700"></div>)}
                </div>
                {/* Courbes SVG */}
                <svg className="w-full h-full" viewBox="0 0 700 170" preserveAspectRatio="none">
                  {(() => {
                    const maxVal = Math.max(...chartPoints.flatMap(d => [d.ca, d.charges, Math.abs(d.benefice)]), 1);
                    const getY = v => 160 - (Math.max(0, v) / maxVal) * 140;
                    const getX = i => (i / (chartPoints.length - 1 || 1)) * 650 + 25;
                    const caPath = chartPoints.map((d, i) => `${i === 0 ? 'M' : 'L'}${getX(i)},${getY(d.ca)}`).join(' ');
                    const chPath = chartPoints.map((d, i) => `${i === 0 ? 'M' : 'L'}${getX(i)},${getY(d.charges)}`).join(' ');
                    const bnPath = chartPoints.map((d, i) => `${i === 0 ? 'M' : 'L'}${getX(i)},${getY(d.benefice)}`).join(' ');
                    return (
                      <>
                        <path d={caPath} fill="none" stroke="#3B82F6" strokeWidth="3" strokeLinecap="round"/>
                        <path d={chPath} fill="none" stroke="#EF4444" strokeWidth="3" strokeLinecap="round"/>
                        <path d={bnPath} fill="none" stroke="#22C55E" strokeWidth="3" strokeLinecap="round"/>
                        {chartPoints.map((d, i) => (
                          <g key={i}>
                            <circle cx={getX(i)} cy={getY(d.ca)} r="5" fill="#3B82F6"/>
                            <circle cx={getX(i)} cy={getY(d.charges)} r="5" fill="#EF4444"/>
                            <circle cx={getX(i)} cy={getY(d.benefice)} r="5" fill="#22C55E"/>
                          </g>
                        ))}
                      </>
                    );
                  })()}
                </svg>
                {/* Labels dates */}
                <div className="absolute bottom-0 left-0 right-0 flex justify-between px-4 text-[10px] text-gray-500 dark:text-gray-400">
                  {chartPoints.map((d, i) => <span key={i}>{d.date}</span>)}
                </div>
              </div>
            ) : (
              <div className="h-44 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                <p className="text-sm">Aucune donnée de vente disponible</p>
                <p className="text-xs mt-1">Les ventes POS apparaîtront ici</p>
              </div>
            )}
          
            {/* Résumé rapide */}
            <div className="grid grid-cols-3 gap-3 mt-4 pt-3 border-t dark:border-gray-700">
              <div className="text-center">
                <p className="text-xs text-gray-500 dark:text-gray-400">Total CA</p>
                <p className="font-bold text-blue-600 dark:text-blue-400">{formatCurrency(chartPoints.reduce((s, d) => s + d.ca, 0))}</p>
              </div>
              <div className="text-center">
                <p className="text-xs text-gray-500 dark:text-gray-400">Total Charges</p>
                <p className="font-bold text-red-600 dark:text-red-400">{formatCurrency(chartPoints.reduce((s, d) => s + d.charges, 0))}</p>
              </div>
              <div className="text-center">
                <p className="text-xs text-gray-500 dark:text-gray-400">Bénéfice Net</p>
                <p className="font-bold text-green-600 dark:text-green-400">{formatCurrency(chartPoints.reduce((s, d) => s + d.benefice, 0))}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {activeTab === 'movements' && (
        <div className="space-y-4">
          {/* Filters */}
          <div className="flex gap-4 items-center bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm">
            <Calendar size={20} className="text-gray-400" />
            <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} className="border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2" />
            <span className="text-gray-400">→</span>
            <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} className="border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2" />
          </div>

          {/* Movements Table */}
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
            <table className="w-full">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th className="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Date</th>
                  <th className="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Référence</th>
                  <th className="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Type</th>
                  <th className="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Description</th>
                  <th className="px-4 py-3 text-right text-sm font-semibold text-gray-600 dark:text-gray-300">Montant</th>
                </tr>
              </thead>
              <tbody className="divide-y dark:divide-gray-700">
                {(movements.data || movements).map((mv, idx) => (
                  <tr key={mv.id || idx} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td className="px-4 py-3 text-sm text-gray-900 dark:text-white">{new Date(mv.created_at).toLocaleString('fr-FR')}</td>
                    <td className="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">{mv.reference}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium ${
                        mv.type === 'in' ? 'bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-400' : 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-400'
                      }`}>
                        {mv.type === 'in' ? <ArrowDownRight size={14} /> : <ArrowUpRight size={14} />}
                        {mv.type === 'in' ? 'Entrée' : 'Sortie'}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{mv.description}</td>
                    <td className={`px-4 py-3 text-right font-bold ${mv.type === 'in' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                      {mv.type === 'in' ? '+' : '-'}{formatCurrency(mv.amount)}
                    </td>
                  </tr>
                ))}
                {(movements.data || movements).length === 0 && (
                  <tr><td colSpan="5" className="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Aucun mouvement</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {activeTab === 'remittances' && (
        <div className="space-y-4">
          {/* Pending Remittances */}
          {pendingRemittances.length > 0 && (
            <div className="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded-xl p-4">
              <h3 className="font-bold text-yellow-800 dark:text-yellow-400 mb-3 flex items-center gap-2">
                <Clock size={18} /> Remises en Attente ({pendingRemittances.length})
              </h3>
              <div className="space-y-2">
                {pendingRemittances.map((rem) => (
                  <div key={rem.id} className="flex items-center justify-between bg-white dark:bg-gray-800 p-3 rounded-lg">
                    <div>
                      <p className="font-medium text-gray-900 dark:text-white">{formatCurrency(rem.amount)}</p>
                      <p className="text-sm text-gray-500 dark:text-gray-400">
                        De {rem.from_user?.name} • {new Date(rem.remitted_at).toLocaleString('fr-FR')}
                      </p>
                    </div>
                    {can('pos.supervise') && (
                      <button
                        onClick={() => handleReceiveRemittance(rem.id)}
                        className="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm"
                      >
                        Recevoir
                      </button>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {activeTab === 'manager' && managerDashboard && (
        <div className="space-y-6">
          {/* Summary Cards */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/30 dark:to-green-800/30 border border-green-200 dark:border-green-700 rounded-xl p-5">
              <p className="text-green-700 dark:text-green-400 text-sm font-semibold">Total Entrées</p>
              <p className="text-2xl font-bold text-green-800 dark:text-green-300">{formatCurrency(managerDashboard.today?.total_in)}</p>
            </div>
            <div className="bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/30 dark:to-red-800/30 border border-red-200 dark:border-red-700 rounded-xl p-5">
              <p className="text-red-700 dark:text-red-400 text-sm font-semibold">Total Sorties</p>
              <p className="text-2xl font-bold text-red-800 dark:text-red-300">{formatCurrency(managerDashboard.today?.total_out)}</p>
            </div>
            <div className="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-800/30 border border-blue-200 dark:border-blue-700 rounded-xl p-5">
              <p className="text-blue-700 dark:text-blue-400 text-sm font-semibold">Solde Net</p>
              <p className="text-2xl font-bold text-blue-800 dark:text-blue-300">{formatCurrency(managerDashboard.today?.balance)}</p>
            </div>
            <div className="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/30 dark:to-purple-800/30 border border-purple-200 dark:border-purple-700 rounded-xl p-5">
              <p className="text-purple-700 dark:text-purple-400 text-sm font-semibold">Remises en Attente</p>
              <p className="text-2xl font-bold text-purple-800 dark:text-purple-300">{formatCurrency(managerDashboard.remittances?.pending)}</p>
            </div>
          </div>

          {/* Open Sessions */}
          {managerDashboard.open_sessions?.length > 0 && (
            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
              <h3 className="font-bold text-lg mb-4 text-gray-900 dark:text-white">Sessions Ouvertes</h3>
              <div className="space-y-3">
                {managerDashboard.open_sessions.map((session) => (
                  <div key={session.id} className="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div>
                      <p className="font-medium text-gray-900 dark:text-white">{session.pos?.name || 'Caisse Principale'}</p>
                      <p className="text-sm text-gray-500 dark:text-gray-400">
                        {session.opened_by_user?.name} • Depuis {new Date(session.opened_at).toLocaleTimeString('fr-FR')}
                      </p>
                    </div>
                    <div className="text-right">
                      <p className="font-bold text-green-600 dark:text-green-400">{formatCurrency(session.cash_sales)}</p>
                      <p className="text-xs text-gray-500 dark:text-gray-400">{session.transactions_count} transactions</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Sales by Method */}
          {managerDashboard.sales_by_method?.length > 0 && (
            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
              <h3 className="font-bold text-lg mb-4 text-gray-900 dark:text-white">Ventes par Méthode</h3>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                {managerDashboard.sales_by_method.map((item) => (
                  <div key={item.payment_method} className="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg text-center">
                    <p className="text-sm text-gray-500 dark:text-gray-400 capitalize">{item.payment_method || 'Autre'}</p>
                    <p className="font-bold text-lg text-gray-900 dark:text-white">{formatCurrency(item.total)}</p>
                    <p className="text-xs text-gray-400">{item.count} ventes</p>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {/* Open Session Modal */}
      {showOpenModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md">
            <h3 className="text-xl font-bold mb-4 text-gray-900 dark:text-white">Ouvrir la Caisse</h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Solde Initial (Fond de caisse)</label>
                <input
                  type="number"
                  value={openingBalance}
                  onChange={(e) => setOpeningBalance(e.target.value)}
                  placeholder="0"
                  className="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2"
                />
              </div>
              <div className="flex gap-2">
                <button onClick={() => setShowOpenModal(false)} className="flex-1 px-4 py-2 border dark:border-gray-600 dark:text-gray-300 rounded-lg">Annuler</button>
                <button onClick={handleOpenSession} className="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg">Ouvrir</button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Close Session Modal */}
      {showCloseModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md">
            <h3 className="text-xl font-bold mb-4 text-gray-900 dark:text-white">Fermer la Caisse</h3>
            <div className="space-y-4">
              <div className="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                <p className="text-sm text-blue-700 dark:text-blue-400">
                  Solde attendu: <strong>{formatCurrency(
                    parseFloat(currentSession?.opening_balance || 0) +
                    parseFloat(currentSession?.cash_sales || 0) +
                    parseFloat(currentSession?.cash_in || 0) -
                    parseFloat(currentSession?.cash_out || 0)
                  )}</strong>
                </p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Solde Réel Compté</label>
                <input
                  type="number"
                  value={closingBalance}
                  onChange={(e) => setClosingBalance(e.target.value)}
                  placeholder="0"
                  className="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes (optionnel)</label>
                <textarea
                  value={closingNotes}
                  onChange={(e) => setClosingNotes(e.target.value)}
                  className="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2"
                  rows="2"
                />
              </div>
              <div className="flex gap-2">
                <button onClick={() => setShowCloseModal(false)} className="flex-1 px-4 py-2 border dark:border-gray-600 dark:text-gray-300 rounded-lg">Annuler</button>
                <button onClick={handleCloseSession} className="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg">Fermer</button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Movement Modal */}
      {showMovementModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md">
            <h3 className="text-xl font-bold mb-4 text-gray-900 dark:text-white">Enregistrer un Mouvement</h3>
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-2">
                <button
                  onClick={() => setMovementData({ ...movementData, type: 'in' })}
                  className={`p-3 rounded-lg border-2 ${movementData.type === 'in' ? 'border-green-500 bg-green-50 dark:bg-green-900/30' : 'border-gray-200 dark:border-gray-600'}`}
                >
                  <ArrowDownRight className="mx-auto text-green-600" />
                  <span className="text-sm text-gray-900 dark:text-white">Entrée</span>
                </button>
                <button
                  onClick={() => setMovementData({ ...movementData, type: 'out' })}
                  className={`p-3 rounded-lg border-2 ${movementData.type === 'out' ? 'border-red-500 bg-red-50 dark:bg-red-900/30' : 'border-gray-200 dark:border-gray-600'}`}
                >
                  <ArrowUpRight className="mx-auto text-red-600" />
                  <span className="text-sm text-gray-900 dark:text-white">Sortie</span>
                </button>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Catégorie</label>
                <select
                  value={movementData.category}
                  onChange={(e) => setMovementData({ ...movementData, category: e.target.value })}
                  className="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2"
                >
                  <option value="deposit">Dépôt</option>
                  <option value="withdrawal">Retrait</option>
                  <option value="expense">Dépense/Charge</option>
                  <option value="adjustment">Ajustement</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Montant</label>
                <input
                  type="number"
                  value={movementData.amount}
                  onChange={(e) => setMovementData({ ...movementData, amount: e.target.value })}
                  className="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                <input
                  type="text"
                  value={movementData.description}
                  onChange={(e) => setMovementData({ ...movementData, description: e.target.value })}
                  className="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2"
                />
              </div>
              <div className="flex gap-2">
                <button onClick={() => setShowMovementModal(false)} className="flex-1 px-4 py-2 border dark:border-gray-600 dark:text-gray-300 rounded-lg">Annuler</button>
                <button onClick={handleRecordMovement} className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg">Enregistrer</button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Remittance Modal */}
      {showRemittanceModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md">
            <h3 className="text-xl font-bold mb-4 text-gray-900 dark:text-white">Remise au Gérant</h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Montant à Remettre</label>
                <input
                  type="number"
                  value={remittanceAmount}
                  onChange={(e) => setRemittanceAmount(e.target.value)}
                  className="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes (optionnel)</label>
                <textarea
                  value={remittanceNotes}
                  onChange={(e) => setRemittanceNotes(e.target.value)}
                  className="w-full border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2"
                  rows="2"
                />
              </div>
              <div className="flex gap-2">
                <button onClick={() => setShowRemittanceModal(false)} className="flex-1 px-4 py-2 border dark:border-gray-600 dark:text-gray-300 rounded-lg">Annuler</button>
                <button onClick={handleCreateRemittance} className="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg">Envoyer</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
