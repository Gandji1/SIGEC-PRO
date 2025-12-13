import { useState, useEffect } from 'react';
import apiClient from '../services/apiClient';

export default function ChartOfAccountsPage() {
  const [accounts, setAccounts] = useState([]);
  const [summary, setSummary] = useState(null);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('');
  const [typeFilter, setTypeFilter] = useState('');

  useEffect(() => {
    fetchAccounts();
    fetchSummary();
  }, [typeFilter]);

  const fetchAccounts = async () => {
    try {
      setLoading(true);
      const params = {};
      if (typeFilter) params.type = typeFilter;
      
      const response = await apiClient.get('/chart-of-accounts', { params });
      setAccounts(response.data.data);
    } catch (error) {
      console.error('Erreur:', error);
    } finally {
      setLoading(false);
    }
  };

  const fetchSummary = async () => {
    try {
      const response = await apiClient.get('/chart-of-accounts/summary');
      setSummary(response.data);
    } catch (error) {
      console.error('Erreur:', error);
    }
  };

  const filteredAccounts = accounts.filter(acc =>
    acc.name.toLowerCase().includes(filter.toLowerCase()) ||
    acc.code.includes(filter)
  );

  const getTypeLabel = (type) => {
    return {
      'asset': '📊 Actifs',
      'liability': '📋 Passifs',
      'equity': '💰 Capitaux',
      'revenue': '📈 Revenus',
      'expense': '📉 Dépenses',
    }[type] || type;
  };

  const getTypeBgColor = (type) => {
    return {
      'asset': 'bg-blue-50',
      'liability': 'bg-red-50',
      'equity': 'bg-green-50',
      'revenue': 'bg-emerald-50',
      'expense': 'bg-orange-50',
    }[type] || 'bg-gray-50';
  };

  return (
    <div className="space-y-6">
      {/* Titre */}
      <div className="flex justify-between items-center">
        <h1 className="text-3xl font-bold text-gray-900">Plan Comptable</h1>
      </div>

      {/* Résumé */}
      {summary && (
        <div className="grid grid-cols-5 gap-4">
          <div className="bg-white rounded-lg shadow p-4 text-center">
            <div className="text-3xl font-bold text-gray-900">{summary.total_accounts}</div>
            <div className="text-sm text-gray-600">Total Comptes</div>
          </div>
          <div className="bg-blue-50 rounded-lg shadow p-4 text-center border border-blue-200">
            <div className="text-3xl font-bold text-blue-900">{summary.assets}</div>
            <div className="text-sm text-blue-700">Actifs</div>
          </div>
          <div className="bg-red-50 rounded-lg shadow p-4 text-center border border-red-200">
            <div className="text-3xl font-bold text-red-900">{summary.liabilities}</div>
            <div className="text-sm text-red-700">Passifs</div>
          </div>
          <div className="bg-emerald-50 rounded-lg shadow p-4 text-center border border-emerald-200">
            <div className="text-3xl font-bold text-emerald-900">{summary.revenues}</div>
            <div className="text-sm text-emerald-700">Revenus</div>
          </div>
          <div className="bg-orange-50 rounded-lg shadow p-4 text-center border border-orange-200">
            <div className="text-3xl font-bold text-orange-900">{summary.expenses}</div>
            <div className="text-sm text-orange-700">Dépenses</div>
          </div>
        </div>
      )}

      {/* Filtres */}
      <div className="flex gap-4">
        <input
          type="text"
          placeholder="Rechercher par code ou nom..."
          value={filter}
          onChange={(e) => setFilter(e.target.value)}
          className="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:border-indigo-500"
        />
        <select
          value={typeFilter}
          onChange={(e) => setTypeFilter(e.target.value)}
          className="px-4 py-2 border rounded-lg focus:outline-none focus:border-indigo-500"
        >
          <option value="">Tous les types</option>
          <option value="asset">Actifs</option>
          <option value="liability">Passifs</option>
          <option value="equity">Capitaux</option>
          <option value="revenue">Revenus</option>
          <option value="expense">Dépenses</option>
        </select>
      </div>

      {/* Tableau des comptes */}
      <div className="bg-white rounded-lg shadow overflow-hidden">
        {loading ? (
          <div className="p-8 text-center text-gray-500">
            ⏳ Chargement...
          </div>
        ) : filteredAccounts.length === 0 ? (
          <div className="p-8 text-center text-gray-500">
            Aucun compte trouvé
          </div>
        ) : (
          <table className="w-full">
            <thead className="bg-gray-50 border-b">
              <tr>
                <th className="text-left px-6 py-3 font-semibold text-gray-700">Code</th>
                <th className="text-left px-6 py-3 font-semibold text-gray-700">Nom</th>
                <th className="text-left px-6 py-3 font-semibold text-gray-700">Type</th>
                <th className="text-left px-6 py-3 font-semibold text-gray-700">Catégorie</th>
                <th className="text-left px-6 py-3 font-semibold text-gray-700">Statut</th>
              </tr>
            </thead>
            <tbody>
              {filteredAccounts.map((account) => (
                <tr key={account.id} className={`border-b ${getTypeBgColor(account.type)}`}>
                  <td className="px-6 py-3 font-mono font-bold text-gray-900">{account.code}</td>
                  <td className="px-6 py-3 text-gray-900">{account.name}</td>
                  <td className="px-6 py-3">
                    <span className="px-3 py-1 bg-white rounded text-sm font-semibold">
                      {getTypeLabel(account.type)}
                    </span>
                  </td>
                  <td className="px-6 py-3 text-gray-700">{account.category}</td>
                  <td className="px-6 py-3">
                    <span className={`px-3 py-1 rounded text-sm font-semibold ${
                      account.is_active 
                        ? 'bg-green-100 text-green-700' 
                        : 'bg-gray-100 text-gray-700'
                    }`}>
                      {account.is_active ? '✓ Actif' : 'Inactif'}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Information */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h3 className="font-semibold text-lg text-blue-900 mb-2">ℹ️ À propos du Plan Comptable</h3>
        <p className="text-blue-800 mb-4">
          Ce plan comptable a été créé automatiquement selon votre type de business. 
          Il suit les normes OHADA et peut être ajusté si nécessaire.
        </p>
        <div className="grid grid-cols-2 gap-4 text-sm text-blue-800">
          <div>
            <strong>Classe 1 (Actifs):</strong> Banque, Caisse, Stock, Clients...
          </div>
          <div>
            <strong>Classe 2 (Passifs):</strong> Fournisseurs, Dettes, Impôts...
          </div>
          <div>
            <strong>Classe 3 (Capitaux):</strong> Capital, Résultats accumulés...
          </div>
          <div>
            <strong>Classe 4 (Revenus):</strong> Ventes, Services, Autres revenus...
          </div>
          <div colSpan={2}>
            <strong>Classes 5-6 (Dépenses):</strong> Salaires, Loyer, Électricité, Fournitures...
          </div>
        </div>
      </div>
    </div>
  );
}
