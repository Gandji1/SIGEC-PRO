import { useState, useEffect, memo, useCallback } from 'react';
import apiClient from '../services/apiClient';
import { BookOpen, Calendar, Download, Filter, Search, ChevronDown, ChevronRight } from 'lucide-react';

const TableSkeleton = memo(() => (
  <div className="animate-pulse">
    <div className="h-10 bg-gray-200 rounded mb-2"></div>
    {[1,2,3,4,5].map(i => <div key={i} className="h-12 bg-gray-100 rounded mb-2"></div>)}
  </div>
));

/**
 * Page du Grand Livre Comptable
 */
export default function GrandLivrePage() {
  const [accounts, setAccounts] = useState([]);
  const [selectedAccount, setSelectedAccount] = useState(null);
  const [movements, setMovements] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadingMovements, setLoadingMovements] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [dateFrom, setDateFrom] = useState(new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0]);
  const [dateTo, setDateTo] = useState(new Date().toISOString().split('T')[0]);
  const [expandedClasses, setExpandedClasses] = useState({});

  useEffect(() => {
    fetchAccounts();
  }, []);

  const fetchAccounts = async () => {
    setLoading(true);
    try {
      const res = await apiClient.get('/chart-of-accounts?with_balances=true');
      setAccounts(res.data?.data || []);
    } catch (error) {
      console.error('Error:', error);
    } finally {
      setLoading(false);
    }
  };

  const fetchAccountMovements = async (accountId) => {
    setLoadingMovements(true);
    try {
      const res = await apiClient.get(`/accounting/grand-livre/${accountId}?from=${dateFrom}&to=${dateTo}`);
      setMovements(res.data?.data || []);
    } catch (error) {
      console.error('Error:', error);
      setMovements([]);
    } finally {
      setLoadingMovements(false);
    }
  };

  const handleSelectAccount = (account) => {
    setSelectedAccount(account);
    fetchAccountMovements(account.id);
  };

  const toggleClass = (classNum) => {
    setExpandedClasses(prev => ({ ...prev, [classNum]: !prev[classNum] }));
  };

  const formatCurrency = (val) => new Intl.NumberFormat('fr-FR', { 
    style: 'currency', currency: 'XAF', maximumFractionDigits: 0 
  }).format(val || 0);

  // Grouper les comptes par classe
  const groupedAccounts = accounts.reduce((acc, account) => {
    const classNum = account.code?.charAt(0) || '0';
    if (!acc[classNum]) acc[classNum] = [];
    acc[classNum].push(account);
    return acc;
  }, {});

  const classNames = {
    '1': 'Comptes de Capitaux',
    '2': 'Comptes d\'Immobilisations',
    '3': 'Comptes de Stocks',
    '4': 'Comptes de Tiers',
    '5': 'Comptes Financiers',
    '6': 'Comptes de Charges',
    '7': 'Comptes de Produits',
  };

  const filteredAccounts = searchTerm
    ? accounts.filter(a => 
        a.code?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        a.name?.toLowerCase().includes(searchTerm.toLowerCase())
      )
    : null;

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 dark:text-white">📖 Grand Livre</h1>
          <p className="text-gray-600 dark:text-gray-400 mt-1">Détail des mouvements par compte</p>
        </div>
        <button className="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
          <Download size={18} /> Exporter PDF
        </button>
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
        {selectedAccount && (
          <button 
            onClick={() => fetchAccountMovements(selectedAccount.id)} 
            className="bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2"
          >
            <Filter size={18} /> Actualiser
          </button>
        )}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Accounts List */}
        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
          <div className="p-4 border-b dark:border-gray-700">
            <div className="relative">
              <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                placeholder="Rechercher un compte..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pl-10 pr-4 py-2 border dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg"
              />
            </div>
          </div>
          <div className="max-h-[600px] overflow-y-auto">
            {loading ? (
              <div className="p-4"><TableSkeleton /></div>
            ) : filteredAccounts ? (
              // Résultats de recherche
              <div className="divide-y dark:divide-gray-700">
                {filteredAccounts.map((account) => (
                  <button
                    key={account.id}
                    onClick={() => handleSelectAccount(account)}
                    className={`w-full text-left px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 ${
                      selectedAccount?.id === account.id ? 'bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-600' : ''
                    }`}
                  >
                    <span className="font-mono text-blue-600 dark:text-blue-400">{account.code}</span>
                    <span className="ml-2 text-gray-700 dark:text-gray-300">{account.name}</span>
                  </button>
                ))}
              </div>
            ) : (
              // Liste groupée par classe
              Object.entries(groupedAccounts).sort().map(([classNum, classAccounts]) => (
                <div key={classNum}>
                  <button
                    onClick={() => toggleClass(classNum)}
                    className="w-full flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 font-semibold text-gray-900 dark:text-white"
                  >
                    <span>Classe {classNum} - {classNames[classNum] || 'Autres'}</span>
                    {expandedClasses[classNum] ? <ChevronDown size={18} /> : <ChevronRight size={18} />}
                  </button>
                  {expandedClasses[classNum] && (
                    <div className="divide-y dark:divide-gray-700">
                      {classAccounts.map((account) => (
                        <button
                          key={account.id}
                          onClick={() => handleSelectAccount(account)}
                          className={`w-full text-left px-6 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm ${
                            selectedAccount?.id === account.id ? 'bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-600' : ''
                          }`}
                        >
                          <span className="font-mono text-blue-600 dark:text-blue-400">{account.code}</span>
                          <span className="ml-2 text-gray-700 dark:text-gray-300">{account.name}</span>
                        </button>
                      ))}
                    </div>
                  )}
                </div>
              ))
            )}
          </div>
        </div>

        {/* Account Movements */}
        <div className="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
          {!selectedAccount ? (
            <div className="p-8 text-center text-gray-500 dark:text-gray-400">
              <BookOpen size={48} className="mx-auto mb-4 text-gray-300 dark:text-gray-600" />
              <p>Sélectionnez un compte pour voir ses mouvements</p>
            </div>
          ) : (
            <>
              <div className="p-4 border-b dark:border-gray-700 bg-blue-50 dark:bg-blue-900/30">
                <h3 className="font-bold text-lg text-gray-900 dark:text-white">
                  <span className="font-mono text-blue-600 dark:text-blue-400">{selectedAccount.code}</span>
                  <span className="ml-2">{selectedAccount.name}</span>
                </h3>
                <p className="text-sm text-gray-600 dark:text-gray-400">
                  Solde: <span className="font-bold">{formatCurrency(selectedAccount.balance)}</span>
                </p>
              </div>
              {loadingMovements ? (
                <div className="p-4"><TableSkeleton /></div>
              ) : movements.length === 0 ? (
                <div className="p-8 text-center text-gray-500 dark:text-gray-400">Aucun mouvement pour cette période</div>
              ) : (
                <table className="w-full">
                  <thead className="bg-gray-50 dark:bg-gray-700">
                    <tr>
                      <th className="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Date</th>
                      <th className="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Pièce</th>
                      <th className="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Libellé</th>
                      <th className="px-4 py-3 text-right text-sm font-semibold text-gray-600 dark:text-gray-300">Débit</th>
                      <th className="px-4 py-3 text-right text-sm font-semibold text-gray-600 dark:text-gray-300">Crédit</th>
                      <th className="px-4 py-3 text-right text-sm font-semibold text-gray-600 dark:text-gray-300">Solde</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y dark:divide-gray-700">
                    {movements.map((mv, idx) => (
                      <tr key={idx} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td className="px-4 py-3 text-sm text-gray-900 dark:text-white">{new Date(mv.date).toLocaleDateString('fr-FR')}</td>
                        <td className="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">{mv.reference}</td>
                        <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{mv.description}</td>
                        <td className="px-4 py-3 text-right text-sm text-green-600 dark:text-green-400">{mv.debit > 0 ? formatCurrency(mv.debit) : '-'}</td>
                        <td className="px-4 py-3 text-right text-sm text-red-600 dark:text-red-400">{mv.credit > 0 ? formatCurrency(mv.credit) : '-'}</td>
                        <td className="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-white">{formatCurrency(mv.running_balance)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}
