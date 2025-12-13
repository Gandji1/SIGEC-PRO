import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTenantStore } from '../stores/tenantStore';
import apiClient from '../services/apiClient';

export default function ExpenseTrackingPage() {
  const { user, tenant } = useTenantStore();
  const navigate = useNavigate();

  const [expenses, setExpenses] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [editingExpense, setEditingExpense] = useState(null);
  const [filter, setFilter] = useState('all');
  const [formData, setFormData] = useState({
    description: '',
    amount: '',
    category: '',
    expense_date: new Date().toISOString().split('T')[0],
    is_fixed: false,
    payment_method: '',
    notes: '',
  });

  useEffect(() => {
    if (!user || !['owner', 'accountant', 'manager'].includes(user.role)) {
      navigate('/dashboard');
      return;
    }
    fetchExpenses();
  }, [user]);

  const fetchExpenses = async () => {
    try {
      setLoading(true);
      const response = await apiClient.get('/expenses');
      setExpenses(response.data.data || []);
    } catch (err) {
      setError('Erreur lors du chargement des charges');
    } finally {
      setLoading(false);
    }
  };

  const handleOpenModal = (expense = null) => {
    if (expense) {
      setEditingExpense(expense);
      setFormData({
        description: expense.description,
        amount: expense.amount,
        category: expense.category,
        expense_date: expense.expense_date,
        is_fixed: expense.is_fixed,
        payment_method: expense.payment_method || '',
        notes: expense.notes || '',
      });
    } else {
      setEditingExpense(null);
      setFormData({
        description: '',
        amount: '',
        category: '',
        expense_date: new Date().toISOString().split('T')[0],
        is_fixed: false,
        payment_method: '',
        notes: '',
      });
    }
    setShowModal(true);
  };

  const handleSaveExpense = async (e) => {
    e.preventDefault();
    try {
      setLoading(true);
      if (editingExpense) {
        await apiClient.put(`/expenses/${editingExpense.id}`, formData);
        setSuccess('Charge mise à jour avec succès!');
      } else {
        await apiClient.post('/expenses', formData);
        setSuccess('Charge créée avec succès!');
      }
      setShowModal(false);
      fetchExpenses();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la sauvegarde');
    } finally {
      setLoading(false);
    }
  };

  const handleDeleteExpense = async (id) => {
    if (!window.confirm('Êtes-vous sûr de vouloir supprimer cette charge?')) return;
    try {
      setLoading(true);
      await apiClient.delete(`/expenses/${id}`);
      setSuccess('Charge supprimée avec succès!');
      fetchExpenses();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError('Erreur lors de la suppression');
    } finally {
      setLoading(false);
    }
  };

  const categories = [
    { value: 'loyer', label: '🏠 Loyer/Bureau', type: 'fixed' },
    { value: 'salaires', label: '👥 Salaires', type: 'fixed' },
    { value: 'utilites', label: '⚡ Électricité/Eau', type: 'variable' },
    { value: 'fournitures', label: '📝 Fournitures', type: 'variable' },
    { value: 'maintenance', label: '🔧 Maintenance', type: 'variable' },
    { value: 'transport', label: '🚚 Transport', type: 'variable' },
    { value: 'communication', label: '📱 Communication', type: 'variable' },
    { value: 'assurance', label: '🛡️ Assurance', type: 'fixed' },
    { value: 'publicite', label: '📢 Publicité', type: 'variable' },
    { value: 'formation', label: '📚 Formation', type: 'variable' },
    { value: 'autres', label: '📌 Autres', type: 'variable' },
  ];

  const getCategoryLabel = (category) => {
    const cat = categories.find((c) => c.value === category);
    return cat ? cat.label : category;
  };

  const getFilteredExpenses = () => {
    if (filter === 'fixed') return expenses.filter((e) => e.is_fixed);
    if (filter === 'variable') return expenses.filter((e) => !e.is_fixed);
    return expenses;
  };

  const filteredExpenses = getFilteredExpenses();
  const totalExpenses = filteredExpenses.reduce((sum, e) => sum + parseFloat(e.amount || 0), 0);
  const fixedExpenses = expenses.filter((e) => e.is_fixed).reduce((sum, e) => sum + parseFloat(e.amount || 0), 0);
  const variableExpenses = expenses.filter((e) => !e.is_fixed).reduce((sum, e) => sum + parseFloat(e.amount || 0), 0);

  if (!user || !['owner', 'accountant', 'manager'].includes(user.role)) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="text-center">
          <h2 className="text-2xl font-bold text-gray-800">Accès Refusé</h2>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 p-4">
      <div className="max-w-6xl mx-auto">
        {/* Header */}
        <div className="flex justify-between items-center mb-6">
          <div>
            <h1 className="text-3xl font-bold text-gray-800">Suivi des Charges</h1>
            <p className="text-gray-600 mt-1">{tenant?.name}</p>
          </div>
          <button
            onClick={() => handleOpenModal()}
            className="bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition"
          >
            + Nouvelle Charge
          </button>
        </div>

        {/* Messages */}
        {error && (
          <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
            {error}
          </div>
        )}
        {success && (
          <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4">
            {success}
          </div>
        )}

        {/* Statistics */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div className="bg-white rounded-lg shadow p-6">
            <h3 className="text-gray-600 font-medium text-sm">Total Charges</h3>
            <p className="text-3xl font-bold text-gray-900 mt-2">
              {totalExpenses.toLocaleString('fr-FR', { style: 'currency', currency: 'USD' })}
            </p>
          </div>
          <div className="bg-white rounded-lg shadow p-6">
            <h3 className="text-gray-600 font-medium text-sm">Charges Fixes</h3>
            <p className="text-3xl font-bold text-orange-600 mt-2">
              {fixedExpenses.toLocaleString('fr-FR', { style: 'currency', currency: 'USD' })}
            </p>
          </div>
          <div className="bg-white rounded-lg shadow p-6">
            <h3 className="text-gray-600 font-medium text-sm">Charges Variables</h3>
            <p className="text-3xl font-bold text-blue-600 mt-2">
              {variableExpenses.toLocaleString('fr-FR', { style: 'currency', currency: 'USD' })}
            </p>
          </div>
        </div>

        {/* Filter */}
        <div className="bg-white rounded-lg shadow p-4 mb-6">
          <div className="flex gap-2">
            <button
              onClick={() => setFilter('all')}
              className={`px-4 py-2 rounded-lg font-medium transition ${
                filter === 'all'
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              }`}
            >
              Toutes
            </button>
            <button
              onClick={() => setFilter('fixed')}
              className={`px-4 py-2 rounded-lg font-medium transition ${
                filter === 'fixed'
                  ? 'bg-orange-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              }`}
            >
              Fixes
            </button>
            <button
              onClick={() => setFilter('variable')}
              className={`px-4 py-2 rounded-lg font-medium transition ${
                filter === 'variable'
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              }`}
            >
              Variables
            </button>
          </div>
        </div>

        {/* Expenses List */}
        <div className="bg-white rounded-lg shadow overflow-hidden">
          {loading && filteredExpenses.length === 0 ? (
            <div className="p-8 text-center text-gray-500">Chargement...</div>
          ) : filteredExpenses.length === 0 ? (
            <div className="p-8 text-center text-gray-500">Aucune charge trouvée</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-gray-50 border-b">
                  <tr>
                    <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">Date</th>
                    <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">Description</th>
                    <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">Catégorie</th>
                    <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">Type</th>
                    <th className="px-6 py-3 text-right text-sm font-semibold text-gray-900">Montant</th>
                    <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">Moyen</th>
                    <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {filteredExpenses.map((expense) => (
                    <tr key={expense.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 text-sm text-gray-900">
                        {new Date(expense.expense_date).toLocaleDateString('fr-FR')}
                      </td>
                      <td className="px-6 py-4 text-sm text-gray-900 font-medium">{expense.description}</td>
                      <td className="px-6 py-4 text-sm">{getCategoryLabel(expense.category)}</td>
                      <td className="px-6 py-4 text-sm">
                        <span
                          className={`px-2 py-1 rounded text-xs font-medium ${
                            expense.is_fixed
                              ? 'bg-orange-100 text-orange-800'
                              : 'bg-blue-100 text-blue-800'
                          }`}
                        >
                          {expense.is_fixed ? 'Fixe' : 'Variable'}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-sm font-bold text-gray-900 text-right">
                        {parseFloat(expense.amount).toLocaleString('fr-FR', {
                          style: 'currency',
                          currency: 'USD',
                        })}
                      </td>
                      <td className="px-6 py-4 text-sm text-gray-600">
                        {expense.payment_method || '-'}
                      </td>
                      <td className="px-6 py-4 text-sm space-x-2">
                        <button
                          onClick={() => handleOpenModal(expense)}
                          className="text-blue-600 hover:text-blue-800 font-medium"
                        >
                          Éditer
                        </button>
                        <button
                          onClick={() => handleDeleteExpense(expense.id)}
                          className="text-red-600 hover:text-red-800 font-medium"
                        >
                          Supprimer
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Modal */}
        {showModal && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
              <h2 className="text-xl font-bold mb-4">
                {editingExpense ? 'Modifier une Charge' : 'Nouvelle Charge'}
              </h2>

              <form onSubmit={handleSaveExpense} className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Description <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    value={formData.description}
                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                    required
                    placeholder="Ex: Loyer bureau"
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Montant <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="number"
                    value={formData.amount}
                    onChange={(e) => setFormData({ ...formData, amount: e.target.value })}
                    required
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Catégorie <span className="text-red-500">*</span>
                  </label>
                  <select
                    value={formData.category}
                    onChange={(e) => setFormData({ ...formData, category: e.target.value })}
                    required
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="">-- Sélectionner --</option>
                    {categories.map((cat) => (
                      <option key={cat.value} value={cat.value}>
                        {cat.label}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Date <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="date"
                    value={formData.expense_date}
                    onChange={(e) => setFormData({ ...formData, expense_date: e.target.value })}
                    required
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Moyen de Paiement
                  </label>
                  <select
                    value={formData.payment_method}
                    onChange={(e) => setFormData({ ...formData, payment_method: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="">-- Sélectionner --</option>
                    <option value="especes">Espèces</option>
                    <option value="cheque">Chèque</option>
                    <option value="virement">Virement</option>
                    <option value="credit_card">Carte Crédit</option>
                    <option value="kkiapay">KkiaPay</option>
                    <option value="fedapay">FedaPay</option>
                  </select>
                </div>

                <div className="border-t pt-4">
                  <label className="flex items-center">
                    <input
                      type="checkbox"
                      checked={formData.is_fixed}
                      onChange={(e) => setFormData({ ...formData, is_fixed: e.target.checked })}
                      className="rounded border-gray-300"
                    />
                    <span className="ml-2 text-sm font-medium text-gray-700">
                      Charge fixe (récurrente)
                    </span>
                  </label>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                  <textarea
                    value={formData.notes}
                    onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                    placeholder="Notes supplémentaires"
                    rows="2"
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div className="flex gap-2 border-t pt-4">
                  <button
                    type="submit"
                    disabled={loading}
                    className="flex-1 bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700 disabled:bg-gray-400 transition"
                  >
                    {loading ? 'Sauvegarde...' : 'Enregistrer'}
                  </button>
                  <button
                    type="button"
                    onClick={() => setShowModal(false)}
                    className="flex-1 bg-gray-100 text-gray-700 py-2 rounded-lg font-medium hover:bg-gray-200 transition"
                  >
                    Annuler
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
