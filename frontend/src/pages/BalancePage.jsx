import { useState, useEffect, memo } from 'react';
import apiClient from '../services/apiClient';
import { Scale, Calendar, Download, Filter, TrendingUp, TrendingDown } from 'lucide-react';

const TableSkeleton = memo(() => (
  <div className="animate-pulse">
    <div className="h-10 bg-gray-200 rounded mb-2"></div>
    {[1,2,3,4,5,6,7,8].map(i => <div key={i} className="h-10 bg-gray-100 rounded mb-2"></div>)}
  </div>
));

/**
 * Page de la Balance Comptable
 */
export default function BalancePage() {
  const [balanceData, setBalanceData] = useState([]);
  const [totals, setTotals] = useState({});
  const [loading, setLoading] = useState(true);
  const [dateFrom, setDateFrom] = useState(new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0]);
  const [dateTo, setDateTo] = useState(new Date().toISOString().split('T')[0]);
  const [viewMode, setViewMode] = useState('all'); // all, classes, detailed

  useEffect(() => {
    fetchBalance();
  }, [dateFrom, dateTo]);

  const fetchBalance = async () => {
    setLoading(true);
    try {
      const res = await apiClient.get(`/accounting/balance?from=${dateFrom}&to=${dateTo}`);
      const data = res.data?.data || [];
      setBalanceData(data);
      
      // Calculer les totaux
      const tots = data.reduce((acc, item) => ({
        debit_ouverture: (acc.debit_ouverture || 0) + (item.debit_ouverture || 0),
        credit_ouverture: (acc.credit_ouverture || 0) + (item.credit_ouverture || 0),
        debit_mouvement: (acc.debit_mouvement || 0) + (item.debit_mouvement || 0),
        credit_mouvement: (acc.credit_mouvement || 0) + (item.credit_mouvement || 0),
        debit_solde: (acc.debit_solde || 0) + (item.debit_solde || 0),
        credit_solde: (acc.credit_solde || 0) + (item.credit_solde || 0),
      }), {});
      setTotals(tots);
    } catch (error) {
      console.error('Error:', error);
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (val) => new Intl.NumberFormat('fr-FR', { 
    style: 'currency', currency: 'XAF', maximumFractionDigits: 0 
  }).format(val || 0);

  const classNames = {
    '1': 'Capitaux',
    '2': 'Immobilisations',
    '3': 'Stocks',
    '4': 'Tiers',
    '5': 'Financiers',
    '6': 'Charges',
    '7': 'Produits',
  };

  // Grouper par classe si nécessaire
  const groupedData = viewMode === 'classes' 
    ? Object.entries(
        balanceData.reduce((acc, item) => {
          const classNum = item.code?.charAt(0) || '0';
          if (!acc[classNum]) acc[classNum] = { 
            code: classNum, 
            name: `Classe ${classNum} - ${classNames[classNum] || 'Autres'}`,
            debit_ouverture: 0, credit_ouverture: 0,
            debit_mouvement: 0, credit_mouvement: 0,
            debit_solde: 0, credit_solde: 0,
          };
          acc[classNum].debit_ouverture += item.debit_ouverture || 0;
          acc[classNum].credit_ouverture += item.credit_ouverture || 0;
          acc[classNum].debit_mouvement += item.debit_mouvement || 0;
          acc[classNum].credit_mouvement += item.credit_mouvement || 0;
          acc[classNum].debit_solde += item.debit_solde || 0;
          acc[classNum].credit_solde += item.credit_solde || 0;
          return acc;
        }, {})
      ).map(([_, data]) => data).sort((a, b) => a.code.localeCompare(b.code))
    : balanceData;

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 dark:text-white">⚖️ Balance Comptable</h1>
          <p className="text-gray-600 dark:text-gray-400 mt-1">État de synthèse des comptes</p>
        </div>
        <button className="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
          <Download size={18} /> Exporter PDF
        </button>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-4 items-center bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm">
        <Calendar size={20} className="text-gray-400" />
        <input
          type="date"
          value={dateFrom}
          onChange={(e) => setDateFrom(e.target.value)}
          className="border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2"
        />
        <span className="text-gray-400">→</span>
        <input
          type="date"
          value={dateTo}
          onChange={(e) => setDateTo(e.target.value)}
          className="border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg px-3 py-2"
        />
        <div className="flex gap-2 ml-auto">
          <button
            onClick={() => setViewMode('all')}
            className={`px-3 py-2 rounded-lg ${viewMode === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700 dark:text-gray-300'}`}
          >
            Détaillée
          </button>
          <button
            onClick={() => setViewMode('classes')}
            className={`px-3 py-2 rounded-lg ${viewMode === 'classes' ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700 dark:text-gray-300'}`}
          >
            Par Classe
          </button>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div className="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
          <p className="text-sm text-gray-500 dark:text-gray-400">Total Débit Mouvements</p>
          <p className="text-xl font-bold text-blue-600 dark:text-blue-400">{formatCurrency(totals.debit_mouvement)}</p>
        </div>
        <div className="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
          <p className="text-sm text-gray-500 dark:text-gray-400">Total Crédit Mouvements</p>
          <p className="text-xl font-bold text-green-600 dark:text-green-400">{formatCurrency(totals.credit_mouvement)}</p>
        </div>
        <div className="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
          <p className="text-sm text-gray-500 dark:text-gray-400">Équilibre</p>
          <p className={`text-xl font-bold ${Math.abs((totals.debit_mouvement || 0) - (totals.credit_mouvement || 0)) < 1 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
            {Math.abs((totals.debit_mouvement || 0) - (totals.credit_mouvement || 0)) < 1 ? '✓ Équilibrée' : '✗ Déséquilibrée'}
          </p>
        </div>
      </div>

      {/* Balance Table */}
      <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        {loading ? (
          <div className="p-4"><TableSkeleton /></div>
        ) : groupedData.length === 0 ? (
          <div className="p-8 text-center text-gray-500 dark:text-gray-400">Aucune donnée pour cette période</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th className="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Compte</th>
                  <th className="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Libellé</th>
                  <th colSpan="2" className="px-4 py-3 text-center text-sm font-semibold text-gray-600 dark:text-gray-300 bg-blue-50 dark:bg-blue-900/30">Solde Ouverture</th>
                  <th colSpan="2" className="px-4 py-3 text-center text-sm font-semibold text-gray-600 dark:text-gray-300 bg-green-50 dark:bg-green-900/30">Mouvements</th>
                  <th colSpan="2" className="px-4 py-3 text-center text-sm font-semibold text-gray-600 dark:text-gray-300 bg-purple-50 dark:bg-purple-900/30">Solde Clôture</th>
                </tr>
                <tr className="bg-gray-100 dark:bg-gray-600">
                  <th></th>
                  <th></th>
                  <th className="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400 bg-blue-50 dark:bg-blue-900/30">Débit</th>
                  <th className="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400 bg-blue-50 dark:bg-blue-900/30">Crédit</th>
                  <th className="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400 bg-green-50 dark:bg-green-900/30">Débit</th>
                  <th className="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400 bg-green-50 dark:bg-green-900/30">Crédit</th>
                  <th className="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400 bg-purple-50 dark:bg-purple-900/30">Débit</th>
                  <th className="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400 bg-purple-50 dark:bg-purple-900/30">Crédit</th>
                </tr>
              </thead>
              <tbody className="divide-y dark:divide-gray-700">
                {groupedData.map((item, idx) => (
                  <tr key={idx} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td className="px-4 py-3 text-sm font-mono text-blue-600 dark:text-blue-400">{item.code}</td>
                    <td className="px-4 py-3 text-sm text-gray-900 dark:text-white">{item.name}</td>
                    <td className="px-4 py-3 text-right text-sm bg-blue-50/30 dark:bg-blue-900/10 text-gray-900 dark:text-white">{item.debit_ouverture > 0 ? formatCurrency(item.debit_ouverture) : '-'}</td>
                    <td className="px-4 py-3 text-right text-sm bg-blue-50/30 dark:bg-blue-900/10 text-gray-900 dark:text-white">{item.credit_ouverture > 0 ? formatCurrency(item.credit_ouverture) : '-'}</td>
                    <td className="px-4 py-3 text-right text-sm bg-green-50/30 dark:bg-green-900/10 text-gray-900 dark:text-white">{item.debit_mouvement > 0 ? formatCurrency(item.debit_mouvement) : '-'}</td>
                    <td className="px-4 py-3 text-right text-sm bg-green-50/30 dark:bg-green-900/10 text-gray-900 dark:text-white">{item.credit_mouvement > 0 ? formatCurrency(item.credit_mouvement) : '-'}</td>
                    <td className="px-4 py-3 text-right text-sm font-medium bg-purple-50/30 dark:bg-purple-900/10 text-gray-900 dark:text-white">{item.debit_solde > 0 ? formatCurrency(item.debit_solde) : '-'}</td>
                    <td className="px-4 py-3 text-right text-sm font-medium bg-purple-50/30 dark:bg-purple-900/10 text-gray-900 dark:text-white">{item.credit_solde > 0 ? formatCurrency(item.credit_solde) : '-'}</td>
                  </tr>
                ))}
              </tbody>
              <tfoot className="bg-gray-100 dark:bg-gray-700 font-bold">
                <tr>
                  <td colSpan="2" className="px-4 py-3 text-right text-gray-900 dark:text-white">TOTAUX</td>
                  <td className="px-4 py-3 text-right bg-blue-100 dark:bg-blue-900/40 text-gray-900 dark:text-white">{formatCurrency(totals.debit_ouverture)}</td>
                  <td className="px-4 py-3 text-right bg-blue-100 dark:bg-blue-900/40 text-gray-900 dark:text-white">{formatCurrency(totals.credit_ouverture)}</td>
                  <td className="px-4 py-3 text-right bg-green-100 dark:bg-green-900/40 text-gray-900 dark:text-white">{formatCurrency(totals.debit_mouvement)}</td>
                  <td className="px-4 py-3 text-right bg-green-100 dark:bg-green-900/40 text-gray-900 dark:text-white">{formatCurrency(totals.credit_mouvement)}</td>
                  <td className="px-4 py-3 text-right bg-purple-100 dark:bg-purple-900/40 text-gray-900 dark:text-white">{formatCurrency(totals.debit_solde)}</td>
                  <td className="px-4 py-3 text-right bg-purple-100 dark:bg-purple-900/40 text-gray-900 dark:text-white">{formatCurrency(totals.credit_solde)}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
