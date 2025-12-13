import { useState, useEffect, useCallback, useMemo } from 'react';
import { Plus, Trash2, Edit2, DollarSign, Calendar, Tag } from 'lucide-react';
import apiClient from '../services/apiClient';
import { usePermission } from '../hooks/usePermission';

const categories = [
  { value: 'utilities', label: 'Utilitaires' },
  { value: 'rent', label: 'Loyer' },
  { value: 'salary', label: 'Salaires' },
  { value: 'marketing', label: 'Marketing' },
  { value: 'supplies', label: 'Fournitures' },
  { value: 'transport', label: 'Transport' },
  { value: 'maintenance', label: 'Maintenance' },
  { value: 'insurance', label: 'Assurance' },
  { value: 'tax', label: 'Impôts' },
  { value: 'other', label: 'Autre' },
];

export default function ExpensesPage() {
  const { can } = usePermission();
  const [expenses, setExpenses] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [filterCategory, setFilterCategory] = useState('');
  const [formData, setFormData] = useState({
    description: '',
    amount: '',
    category: 'other',
    date: new Date().toISOString().split('T')[0],
    notes: '',
  });

  const fetchExpenses = useCallback(async () => {
    try {
      const response = await apiClient.get('/expenses?per_page=50');
      setExpenses(response.data.data || []);
    } catch (error) {
      console.error('Error fetching expenses:', error);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchExpenses();
  }, [fetchExpenses]);

  // Filtrage local
  const filteredExpenses = useMemo(() => {
    return expenses.filter(e => {
      const matchSearch = !searchTerm || 
        e.description?.toLowerCase().includes(searchTerm.toLowerCase());
      const matchCategory = !filterCategory || e.category === filterCategory;
      return matchSearch && matchCategory;
    });
  }, [expenses, searchTerm, filterCategory]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!can('expenses.create') && !editingId && !can('expenses.edit')) {
      alert('Pas de permission');
      return;
    }

    try {
      const payload = {
        ...formData,
        amount: parseFloat(formData.amount),
      };

      if (editingId) {
        await apiClient.put(`/expenses/${editingId}`, payload);
      } else {
        await apiClient.post('/expenses', payload);
      }
      
      setFormData({
        description: '',
        amount: '',
        category: 'other',
        date: new Date().toISOString().split('T')[0],
        notes: '',
      });
      setEditingId(null);
      setShowForm(false);
      fetchExpenses();
    } catch (error) {
      console.error('Error saving expense:', error);
      alert(error.response?.data?.message || 'Erreur');
    }
  };

  const handleEdit = (expense) => {
    setFormData({
      ...expense,
      date: expense.date?.split('T')[0] || new Date().toISOString().split('T')[0],
    });
    setEditingId(expense.id);
    setShowForm(true);
  };

  const handleDelete = async (id) => {
    if (!can('expenses.delete')) {
      alert('Pas de permission');
      return;
    }

    if (!window.confirm('Êtes-vous sûr?')) return;

    try {
      await apiClient.delete(`/expenses/${id}`);
      fetchExpenses();
    } catch (error) {
      console.error('Error deleting expense:', error);
    }
  };

  const getCategoryLabel = (cat) => {
    return categories.find(c => c.value === cat)?.label || cat;
  };

  const totalExpenses = filteredExpenses.reduce((sum, e) => sum + (parseFloat(e.amount) || 0), 0);

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Charges</h1>
          <p className="text-gray-600 mt-1">Gestion des dépenses et charges</p>
        </div>
        {can('expenses.create') && (
          <button
            onClick={() => {
              setFormData({
                description: '',
                amount: '',
                category: 'other',
                date: new Date().toISOString().split('T')[0],
                notes: '',
              });
              setEditingId(null);
              setShowForm(!showForm);
            }}
            className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition"
          >
            <Plus size={20} />
            Ajouter Charge
          </button>
        )}
      </div>

      {/* Form */}
      {showForm && (
        <div className="bg-white rounded-lg shadow-md p-6">
          <h2 className="text-xl font-bold mb-4">
            {editingId ? 'Modifier Charge' : 'Nouvelle Charge'}
          </h2>
          <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input
              type="text"
              placeholder="Description *"
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              className="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            />
            <input
              type="number"
              placeholder="Montant *"
              step="0.01"
              value={formData.amount}
              onChange={(e) => setFormData({ ...formData, amount: e.target.value })}
              className="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            />
            <select
              value={formData.category}
              onChange={(e) => setFormData({ ...formData, category: e.target.value })}
              className="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              {categories.map(cat => (
                <option key={cat.value} value={cat.value}>{cat.label}</option>
              ))}
            </select>
            <input
              type="date"
              value={formData.date}
              onChange={(e) => setFormData({ ...formData, date: e.target.value })}
              className="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <textarea
              placeholder="Notes"
              value={formData.notes}
              onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
              className="md:col-span-2 border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
              rows="3"
            ></textarea>
            <div className="md:col-span-2 flex gap-2">
              <button
                type="submit"
                className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition flex-1"
              >
                {editingId ? 'Modifier' : 'Créer'}
              </button>
              <button
                type="button"
                onClick={() => {
                  setShowForm(false);
                  setEditingId(null);
                }}
                className="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded-lg transition flex-1"
              >
                Annuler
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-red-50 border border-red-200 rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-red-600 text-sm font-semibold">Total Charges</p>
              <p className="text-3xl font-bold text-red-700 mt-2">
                {totalExpenses.toLocaleString('fr-FR', { style: 'currency', currency: 'XOF' })}
              </p>
            </div>
            <DollarSign size={40} className="text-red-300" />
          </div>
        </div>

        <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-blue-600 text-sm font-semibold">Nombre de Charges</p>
              <p className="text-3xl font-bold text-blue-700 mt-2">{filteredExpenses.length}</p>
            </div>
            <Tag size={40} className="text-blue-300" />
          </div>
        </div>

        <div className="bg-purple-50 border border-purple-200 rounded-lg p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-purple-600 text-sm font-semibold">Moyenne</p>
              <p className="text-3xl font-bold text-purple-700 mt-2">
                {filteredExpenses.length > 0 
                  ? (totalExpenses / filteredExpenses.length).toLocaleString('fr-FR', { style: 'currency', currency: 'XOF' })
                  : '0 XOF'}
              </p>
            </div>
            <Calendar size={40} className="text-purple-300" />
          </div>
        </div>
      </div>

      {/* Filters */}
      <div className="flex gap-2">
        <input
          type="text"
          placeholder="Rechercher..."
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          className="border border-gray-300 rounded-lg px-4 py-2 flex-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        <select
          value={filterCategory}
          onChange={(e) => setFilterCategory(e.target.value)}
          className="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">Toutes les catégories</option>
          {categories.map(cat => (
            <option key={cat.value} value={cat.value}>{cat.label}</option>
          ))}
        </select>
      </div>

      {/* Expenses Table */}
      {loading ? (
        <div className="text-center py-12">Chargement...</div>
      ) : filteredExpenses.length === 0 ? (
        <div className="bg-gray-100 rounded-lg p-12 text-center">
          <p className="text-gray-600">Aucune charge</p>
        </div>
      ) : (
        <div className="bg-white rounded-lg shadow-md overflow-hidden">
          <table className="w-full">
            <thead className="bg-gray-100 border-b">
              <tr>
                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Description</th>
                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Catégorie</th>
                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Montant</th>
                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Date</th>
                <th className="px-6 py-3 text-center text-sm font-semibold text-gray-700">Actions</th>
              </tr>
            </thead>
            <tbody>
              {filteredExpenses.map((expense) => (
                <tr key={expense.id} className="border-b hover:bg-gray-50 transition">
                  <td className="px-6 py-4 text-sm text-gray-900">{expense.description}</td>
                  <td className="px-6 py-4 text-sm">
                    <span className="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-xs font-semibold">
                      {getCategoryLabel(expense.category)}
                    </span>
                  </td>
                  <td className="px-6 py-4 text-sm font-bold text-red-600">
                    {parseFloat(expense.amount).toLocaleString('fr-FR', { style: 'currency', currency: 'XOF' })}
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-600">
                    {new Date(expense.date).toLocaleDateString('fr-FR')}
                  </td>
                  <td className="px-6 py-4 text-center flex gap-2 justify-center">
                    {can('expenses.edit') && (
                      <button
                        onClick={() => handleEdit(expense)}
                        className="text-blue-600 hover:text-blue-800 transition"
                      >
                        <Edit2 size={16} />
                      </button>
                    )}
                    {can('expenses.delete') && (
                      <button
                        onClick={() => handleDelete(expense.id)}
                        className="text-red-600 hover:text-red-800 transition"
                      >
                        <Trash2 size={16} />
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
