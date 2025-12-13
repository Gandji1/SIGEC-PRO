import { useState, useEffect, memo, useCallback } from 'react';
import apiClient from '../services/apiClient';
import { Book, Calendar, Download, Filter, Plus, Eye } from 'lucide-react';

const TableSkeleton = memo(() => (
  <div className="animate-pulse">
    <div className="h-10 bg-gray-200 rounded mb-2"></div>
    {[1,2,3,4,5].map(i => <div key={i} className="h-12 bg-gray-100 rounded mb-2"></div>)}
  </div>
));

/**
 * Page des Journaux Comptables
 */
export default function JournauxPage() {
  const [journals, setJournals] = useState([]);
  const [entries, setEntries] = useState([]);
  const [selectedJournal, setSelectedJournal] = useState('all');
  const [loading, setLoading] = useState(true);
  const [dateFrom, setDateFrom] = useState(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]);
  const [dateTo, setDateTo] = useState(new Date().toISOString().split('T')[0]);

  const journalTypes = [
    { id: 'all', name: 'Tous les journaux', icon: '📚' },
    { id: 'ventes', name: 'Journal des Ventes', icon: '🛒' },
    { id: 'achats', name: 'Journal des Achats', icon: '📦' },
    { id: 'caisse', name: 'Journal de Caisse', icon: '💰' },
    { id: 'banque', name: 'Journal de Banque', icon: '🏦' },
    { id: 'operations', name: 'Journal des Opérations Diverses', icon: '📝' },
  ];

  const fetchEntries = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        from: dateFrom,
        to: dateTo,
        per_page: '100',
      });
      if (selectedJournal !== 'all') params.append('journal', selectedJournal);

      const res = await apiClient.get(`/accounting/entries?${params}`);
      setEntries(res.data?.data || []);
    } catch (error) {
      console.error('Error:', error);
    } finally {
      setLoading(false);
    }
  }, [selectedJournal, dateFrom, dateTo]);

  useEffect(() => {
    fetchEntries();
  }, [fetchEntries]);

  const formatCurrency = (val) => new Intl.NumberFormat('fr-FR', { 
    style: 'currency', currency: 'XAF', maximumFractionDigits: 0 
  }).format(val || 0);

  const totalDebit = entries.reduce((sum, e) => sum + (e.debit || 0), 0);
  const totalCredit = entries.reduce((sum, e) => sum + (e.credit || 0), 0);

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 dark:text-white">📚 Journaux Comptables</h1>
          <p className="text-gray-600 dark:text-gray-400 mt-1">Consultation des écritures par journal</p>
        </div>
        <div className="flex gap-2">
          <button className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
            <Plus size={18} /> Nouvelle Écriture
          </button>
          <button className="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
            <Download size={18} /> Exporter
          </button>
        </div>
      </div>

      {/* Journal Selector */}
      <div className="flex gap-2 overflow-x-auto pb-2">
        {journalTypes.map((j) => (
          <button
            key={j.id}
            onClick={() => setSelectedJournal(j.id)}
            className={`flex items-center gap-2 px-4 py-2 rounded-lg whitespace-nowrap transition ${
              selectedJournal === j.id
                ? 'bg-blue-600 text-white'
                : 'bg-white dark:bg-gray-800 border dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 dark:text-gray-300'
            }`}
          >
            <span>{j.icon}</span>
            <span>{j.name}</span>
          </button>
        ))}
      </div>

      {/* Filters */}
      <div className="flex gap-4 items-center bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm">
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
        <button onClick={fetchEntries} className="bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
          <Filter size={18} /> Filtrer
        </button>
      </div>

      {/* Summary */}
      <div className="grid grid-cols-3 gap-4">
        <div className="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
          <p className="text-sm text-gray-500 dark:text-gray-400">Total Débit</p>
          <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">{formatCurrency(totalDebit)}</p>
        </div>
        <div className="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
          <p className="text-sm text-gray-500 dark:text-gray-400">Total Crédit</p>
          <p className="text-2xl font-bold text-green-600 dark:text-green-400">{formatCurrency(totalCredit)}</p>
        </div>
        <div className="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
          <p className="text-sm text-gray-500 dark:text-gray-400">Écart</p>
          <p className={`text-2xl font-bold ${totalDebit === totalCredit ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
            {formatCurrency(totalDebit - totalCredit)}
          </p>
        </div>
      </div>

      {/* Entries Table */}
      <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        {loading ? (
          <div className="p-4"><TableSkeleton /></div>
        ) : entries.length === 0 ? (
          <div className="p-8 text-center text-gray-500 dark:text-gray-400">Aucune écriture pour cette période</div>
        ) : (
          <table className="w-full">
            <thead className="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Date</th>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Pièce</th>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Compte</th>
                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Libellé</th>
                <th className="px-4 py-3 text-right text-sm font-semibold text-gray-600 dark:text-gray-300">Débit</th>
                <th className="px-4 py-3 text-right text-sm font-semibold text-gray-600 dark:text-gray-300">Crédit</th>
                <th className="px-4 py-3 text-center text-sm font-semibold text-gray-600 dark:text-gray-300">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y dark:divide-gray-700">
              {entries.map((entry, idx) => (
                <tr key={idx} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td className="px-4 py-3 text-sm text-gray-900 dark:text-white">{new Date(entry.date).toLocaleDateString('fr-FR')}</td>
                  <td className="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">{entry.reference}</td>
                  <td className="px-4 py-3 text-sm">
                    <span className="font-mono text-blue-600 dark:text-blue-400">{entry.account_code}</span>
                    <span className="text-gray-500 dark:text-gray-400 ml-2">{entry.account_name}</span>
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{entry.description}</td>
                  <td className="px-4 py-3 text-right text-sm font-medium text-green-600 dark:text-green-400">
                    {entry.debit > 0 ? formatCurrency(entry.debit) : '-'}
                  </td>
                  <td className="px-4 py-3 text-right text-sm font-medium text-red-600 dark:text-red-400">
                    {entry.credit > 0 ? formatCurrency(entry.credit) : '-'}
                  </td>
                  <td className="px-4 py-3 text-center">
                    <button className="text-blue-600 dark:text-blue-400 hover:text-blue-800">
                      <Eye size={16} />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot className="bg-gray-100 dark:bg-gray-700 font-bold">
              <tr>
                <td colSpan="4" className="px-4 py-3 text-right text-gray-900 dark:text-white">TOTAUX</td>
                <td className="px-4 py-3 text-right text-gray-900 dark:text-white">{formatCurrency(totalDebit)}</td>
                <td className="px-4 py-3 text-right text-gray-900 dark:text-white">{formatCurrency(totalCredit)}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        )}
      </div>
    </div>
  );
}
