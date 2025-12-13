import { useState, useEffect, memo, useCallback } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import apiClient from '../services/apiClient';
import { usePermission } from '../hooks/usePermission';
import { DollarSign, TrendingUp, TrendingDown, RefreshCw, Download, Calendar, Filter, Settings } from 'lucide-react';

const TableSkeleton = memo(() => (
  <div className="animate-pulse">
    <div className="h-10 bg-gray-200 rounded mb-2"></div>
    {[1,2,3,4,5].map(i => <div key={i} className="h-12 bg-gray-100 rounded mb-2"></div>)}
  </div>
));

/**
 * Page de gestion des caisses (Gros, Détail, POS)
 */
export default function CaissePage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const { can } = usePermission();
  const [activeTab, setActiveTab] = useState(searchParams.get('type') || 'gros');
  const [movements, setMovements] = useState([]);
  const [summary, setSummary] = useState({});
  const [loading, setLoading] = useState(true);
  const [dateFrom, setDateFrom] = useState(new Date().toISOString().split('T')[0]);
  const [dateTo, setDateTo] = useState(new Date().toISOString().split('T')[0]);

  const tabs = [
    { id: 'gros', label: '💰 Caisse Gros', permission: 'accounting.view' },
    { id: 'detail', label: '💵 Caisse Détail', permission: 'accounting.view' },
    { id: 'pos', label: '🛒 Caisses POS', permission: 'pos.supervise' },
  ];

  const fetchCaisseData = useCallback(async () => {
    setLoading(true);
    try {
      const [movementsRes, summaryRes] = await Promise.all([
        apiClient.get(`/caisse/${activeTab}/movements?from=${dateFrom}&to=${dateTo}`).catch(() => ({ data: { data: [] } })),
        apiClient.get(`/caisse/${activeTab}/summary?from=${dateFrom}&to=${dateTo}`).catch(() => ({ data: {} })),
      ]);
      setMovements(movementsRes.data?.data || []);
      setSummary(summaryRes.data?.data || summaryRes.data || {});
    } catch (error) {
      console.error('Error:', error);
    } finally {
      setLoading(false);
    }
  }, [activeTab, dateFrom, dateTo]);

  useEffect(() => {
    fetchCaisseData();
  }, [fetchCaisseData]);

  const handleTabChange = (tabId) => {
    setActiveTab(tabId);
    setSearchParams({ type: tabId });
  };

  const formatCurrency = (val) => new Intl.NumberFormat('fr-FR', { 
    style: 'currency', currency: 'XAF', maximumFractionDigits: 0 
  }).format(val || 0);

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">💰 Gestion des Caisses</h1>
          <p className="text-gray-600 mt-1">Suivi des mouvements de caisse</p>
        </div>
        <div className="flex gap-2">
          <Link to="/cash-register" className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
            <Settings size={18} /> Gérer Sessions
          </Link>
          <button onClick={fetchCaisseData} className="p-2 bg-gray-100 hover:bg-gray-200 rounded-lg">
            <RefreshCw size={20} className={loading ? 'animate-spin' : ''} />
          </button>
          <button className="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
            <Download size={18} /> Exporter
          </button>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b">
        {tabs.filter(t => can(t.permission)).map((tab) => (
          <button
            key={tab.id}
            onClick={() => handleTabChange(tab.id)}
            className={`px-4 py-3 font-medium transition border-b-2 ${
              activeTab === tab.id
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Filters */}
      <div className="flex gap-4 items-center bg-white p-4 rounded-lg shadow-sm">
        <Calendar size={20} className="text-gray-400" />
        <input
          type="date"
          value={dateFrom}
          onChange={(e) => setDateFrom(e.target.value)}
          className="border rounded-lg px-3 py-2"
        />
        <span className="text-gray-400">→</span>
        <input
          type="date"
          value={dateTo}
          onChange={(e) => setDateTo(e.target.value)}
          className="border rounded-lg px-3 py-2"
        />
        <button onClick={fetchCaisseData} className="bg-blue-600 text-white px-4 py-2 rounded-lg">
          <Filter size={18} />
        </button>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-xl p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-green-700 text-sm font-semibold">Entrées</p>
              <p className="text-2xl font-bold text-green-800">{formatCurrency(summary.total_in)}</p>
            </div>
            <TrendingUp size={28} className="text-green-300" />
          </div>
        </div>
        <div className="bg-gradient-to-br from-red-50 to-red-100 border border-red-200 rounded-xl p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-red-700 text-sm font-semibold">Sorties</p>
              <p className="text-2xl font-bold text-red-800">{formatCurrency(summary.total_out)}</p>
            </div>
            <TrendingDown size={28} className="text-red-300" />
          </div>
        </div>
        <div className="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-xl p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-blue-700 text-sm font-semibold">Solde</p>
              <p className="text-2xl font-bold text-blue-800">{formatCurrency(summary.balance)}</p>
            </div>
            <DollarSign size={28} className="text-blue-300" />
          </div>
        </div>
        <div className="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-xl p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-purple-700 text-sm font-semibold">Opérations</p>
              <p className="text-2xl font-bold text-purple-800">{summary.operations_count || 0}</p>
            </div>
            <RefreshCw size={28} className="text-purple-300" />
          </div>
        </div>
      </div>

      {/* Movements Table */}
      <div className="bg-white rounded-xl shadow-sm overflow-hidden">
        <div className="p-4 border-b">
          <h3 className="font-bold text-gray-900">Mouvements de Caisse</h3>
        </div>
        {loading ? (
          <div className="p-4"><TableSkeleton /></div>
        ) : movements.length === 0 ? (
          <div className="p-8 text-center text-gray-500">Aucun mouvement pour cette période</div>
        ) : (
          <table className="w-full">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-600">Date</th>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-600">Référence</th>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-600">Type</th>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-600">Description</th>
                <th className="px-4 py-3 text-right text-sm font-semibold text-gray-600">Entrée</th>
                <th className="px-4 py-3 text-right text-sm font-semibold text-gray-600">Sortie</th>
                <th className="px-4 py-3 text-right text-sm font-semibold text-gray-600">Solde</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {movements.map((mv, idx) => (
                <tr key={idx} className="hover:bg-gray-50">
                  <td className="px-4 py-3 text-sm">{new Date(mv.date || mv.created_at).toLocaleDateString('fr-FR')}</td>
                  <td className="px-4 py-3 text-sm font-mono">{mv.reference}</td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-1 rounded text-xs font-medium ${
                      mv.type === 'in' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                    }`}>
                      {mv.type === 'in' ? 'Entrée' : 'Sortie'}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-600">{mv.description}</td>
                  <td className="px-4 py-3 text-right text-sm text-green-600 font-medium">
                    {mv.type === 'in' ? formatCurrency(mv.amount) : '-'}
                  </td>
                  <td className="px-4 py-3 text-right text-sm text-red-600 font-medium">
                    {mv.type === 'out' ? formatCurrency(mv.amount) : '-'}
                  </td>
                  <td className="px-4 py-3 text-right text-sm font-bold">{formatCurrency(mv.running_balance)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
