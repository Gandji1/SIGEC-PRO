import { useState, useEffect, useCallback, useMemo, memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Plus, Trash2, Edit2, Phone, Mail, MapPin, ShoppingCart, TrendingUp, Users, RefreshCw, Search } from 'lucide-react';
import apiClient from '../services/apiClient';
import { usePermission } from '../hooks/usePermission';
import ExportButton from '../components/ExportButton';
import ConfirmModal from '../components/ConfirmModal';
import { useToast } from '../components/Toast';

// Skeleton optimisé
const TableSkeleton = memo(() => (
  <div className="animate-pulse">
    <div className="h-12 bg-gray-200 dark:bg-gray-700 rounded mb-3"></div>
    {[1,2,3,4,5].map(i => <div key={i} className="h-16 bg-gray-100 dark:bg-gray-800 rounded mb-2"></div>)}
  </div>
));

export default function CustomersPage() {
  const navigate = useNavigate();
  const { can } = usePermission();
  const { toast } = useToast();
  const [customers, setCustomers] = useState([]);
  const [tableLoading, setTableLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [deleteModal, setDeleteModal] = useState({ open: false, customer: null });
  const [deleting, setDeleting] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    contact_person: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    country: '',
    tax_id: '',
    category: 'individual',
    credit_limit: '',
  });

  const fetchCustomers = useCallback(async () => {
    setTableLoading(true);
    try {
      const response = await apiClient.get('/customers?per_page=50');
      setCustomers(response.data?.data || response.data || []);
    } catch (error) {
      console.error('Error fetching customers:', error);
      setCustomers([]);
    } finally {
      setTableLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchCustomers();
  }, [fetchCustomers]);

  // Filtrage local
  const filteredCustomers = useMemo(() => {
    if (!searchTerm) return customers;
    const term = searchTerm.toLowerCase();
    return customers.filter(c => 
      c.name?.toLowerCase().includes(term) ||
      c.email?.toLowerCase().includes(term) ||
      c.phone?.includes(term)
    );
  }, [customers, searchTerm]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!can('customers.create') && !editingId && !can('customers.edit')) {
      alert('Pas de permission');
      return;
    }

    try {
      if (editingId) {
        await apiClient.put(`/customers/${editingId}`, formData);
      } else {
        await apiClient.post('/customers', formData);
      }
      
      setFormData({
        name: '',
        contact_person: '',
        email: '',
        phone: '',
        address: '',
        city: '',
        country: '',
        tax_id: '',
        category: 'individual',
        credit_limit: '',
      });
      setEditingId(null);
      setShowForm(false);
      fetchCustomers();
    } catch (error) {
      console.error('Error saving customer:', error);
      alert(error.response?.data?.message || 'Erreur');
    }
  };

  const handleEdit = (customer) => {
    setFormData(customer);
    setEditingId(customer.id);
    setShowForm(true);
  };

  const handleDeleteClick = (customer) => {
    if (!can('customers.delete')) {
      toast.error('Vous n\'avez pas la permission de supprimer');
      return;
    }
    setDeleteModal({ open: true, customer });
  };

  const handleDeleteConfirm = async () => {
    if (!deleteModal.customer) return;
    setDeleting(true);
    try {
      await apiClient.delete(`/customers/${deleteModal.customer.id}`);
      toast.success(`Client "${deleteModal.customer.name}" supprimé`);
      fetchCustomers();
      setDeleteModal({ open: false, customer: null });
    } catch (error) {
      console.error('Error deleting customer:', error);
      toast.error(error.response?.data?.message || 'Erreur lors de la suppression');
    } finally {
      setDeleting(false);
    }
  };

  // Données pour l'export
  const exportData = filteredCustomers.map(c => ({
    nom: c.name,
    contact: c.contact_person || '',
    email: c.email || '',
    telephone: c.phone || '',
    ville: c.city || '',
    categorie: c.category === 'company' ? 'Entreprise' : 'Particulier',
    limite_credit: c.credit_limit || 0,
    total_achats: c.total_purchases || 0
  }));

  const exportColumns = [
    { key: 'nom', header: 'Nom' },
    { key: 'contact', header: 'Contact' },
    { key: 'email', header: 'Email' },
    { key: 'telephone', header: 'Téléphone' },
    { key: 'ville', header: 'Ville' },
    { key: 'categorie', header: 'Catégorie' },
    { key: 'limite_credit', header: 'Limite Crédit', type: 'currency' },
    { key: 'total_achats', header: 'Total Achats', type: 'currency' }
  ];

  return (
    <div className="p-4 sm:p-6 space-y-6 bg-gray-50 dark:bg-gray-900 min-h-screen">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
            <Users className="text-brand-600" size={28} />
            Clients
          </h1>
          <p className="text-gray-500 dark:text-gray-400 mt-1">{filteredCustomers.length} clients</p>
        </div>
        <div className="flex items-center gap-3">
          <button
            onClick={fetchCustomers}
            disabled={tableLoading}
            className="p-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition"
          >
            <RefreshCw size={18} className={`text-gray-600 dark:text-gray-300 ${tableLoading ? 'animate-spin' : ''}`} />
          </button>
          <ExportButton
            data={exportData}
            columns={exportColumns}
            filename="clients"
            title="Liste des Clients"
            subtitle={`${filteredCustomers.length} clients`}
            variant="secondary"
          />
          {can('customers.create') && (
            <button
              onClick={() => {
                setFormData({
                  name: '',
                  contact_person: '',
                  email: '',
                  phone: '',
                  address: '',
                  city: '',
                  country: '',
                  tax_id: '',
                  category: 'individual',
                  credit_limit: '',
                });
                setEditingId(null);
                setShowForm(true);
              }}
              className="px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-medium flex items-center gap-2 transition"
            >
              <Plus size={18} />
              Nouveau
            </button>
          )}
        </div>
      </div>

      {/* Form */}
      {showForm && (
        <div className="bg-white rounded-lg shadow-md p-6">
          <h2 className="text-xl font-bold mb-4">
            {editingId ? 'Modifier Client' : 'Nouveau Client'}
          </h2>
          <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input
              type="text"
              placeholder="Nom du client *"
              value={formData.name}
              onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              className="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            />
            <input
              type="text"
              placeholder="Personne de contact"
              value={formData.contact_person}
              onChange={(e) => setFormData({ ...formData, contact_person: e.target.value })}
              className="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <input
              type="email"
              placeholder="Email"
              value={formData.email}
              onChange={(e) => setFormData({ ...formData, email: e.target.value })}
              className="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <input
              type="tel"
              placeholder="Téléphone"
              value={formData.phone}
              onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
              className="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <input
              type="text"
              placeholder="Adresse"
              value={formData.address}
              onChange={(e) => setFormData({ ...formData, address: e.target.value })}
              className="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <input
              type="text"
              placeholder="Ville"
              value={formData.city}
              onChange={(e) => setFormData({ ...formData, city: e.target.value })}
              className="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <input
              type="text"
              placeholder="Pays"
              value={formData.country}
              onChange={(e) => setFormData({ ...formData, country: e.target.value })}
              className="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <input
              type="text"
              placeholder="SIRET/Numéro fiscal"
              value={formData.tax_id}
              onChange={(e) => setFormData({ ...formData, tax_id: e.target.value })}
              className="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <select
              value={formData.category || 'retail'}
              onChange={(e) => setFormData({ ...formData, category: e.target.value })}
              className="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            >
              <option value="retail">Détail</option>
              <option value="wholesale">Gros</option>
              <option value="distributor">Distributeur</option>
              <option value="other">Autre</option>
            </select>
            <input
              type="number"
              placeholder="Limite de crédit"
              value={formData.credit_limit}
              onChange={(e) => setFormData({ ...formData, credit_limit: e.target.value })}
              className="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              min="0"
              step="0.01"
            />
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

      {/* Search */}
      <div className="flex gap-2">
        <input
          type="text"
          placeholder="Rechercher..."
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          className="border border-gray-300 rounded-lg px-4 py-2 flex-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>

      {/* Customers List - Skeleton local */}
      {tableLoading ? (
        <div className="bg-white rounded-lg shadow p-6"><TableSkeleton /></div>
      ) : filteredCustomers.length === 0 ? (
        <div className="bg-gray-100 rounded-lg p-12 text-center">
          <p className="text-gray-600">Aucun client</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {filteredCustomers.map((customer) => (
            <div key={customer.id} className="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
              <div className="flex justify-between items-start mb-4">
                <div className="flex-1">
                  <h3 className="text-lg font-bold text-gray-900">{customer.name}</h3>
                  {customer.contact_person && (
                    <p className="text-sm text-gray-600">{customer.contact_person}</p>
                  )}
                </div>
                <div className="flex gap-2">
                  {can('customers.edit') && (
                    <button
                      onClick={() => handleEdit(customer)}
                      className="text-blue-600 hover:text-blue-800 transition"
                    >
                      <Edit2 size={16} />
                    </button>
                  )}
                  {can('customers.delete') && (
                    <button
                      onClick={() => handleDeleteClick(customer)}
                      className="text-red-600 hover:text-red-800 transition"
                    >
                      <Trash2 size={16} />
                    </button>
                  )}
                </div>
              </div>

              <div className="space-y-2 text-sm text-gray-600">
                {customer.phone && (
                  <div className="flex items-center gap-2">
                    <Phone size={16} />
                    {customer.phone}
                  </div>
                )}
                {customer.email && (
                  <div className="flex items-center gap-2">
                    <Mail size={16} />
                    {customer.email}
                  </div>
                )}
                {customer.address && (
                  <div className="flex items-center gap-2">
                    <MapPin size={16} />
                    {customer.address} {customer.city && `(${customer.city})`}
                  </div>
                )}
              </div>

              <div className="mt-4 pt-4 border-t border-gray-200">
                <button
                  onClick={() => navigate(`/sales?customer=${customer.id}`)}
                  className="w-full bg-green-100 text-green-700 hover:bg-green-200 text-xs font-semibold py-2 rounded transition"
                >
                  <TrendingUp size={14} className="inline mr-1" />
                  Voir Ventes
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Modal de confirmation de suppression */}
      <ConfirmModal
        isOpen={deleteModal.open}
        onClose={() => setDeleteModal({ open: false, customer: null })}
        onConfirm={handleDeleteConfirm}
        title="Supprimer le client"
        message={`Êtes-vous sûr de vouloir supprimer "${deleteModal.customer?.name}" ? Cette action est irréversible.`}
        confirmText="Supprimer"
        cancelText="Annuler"
        variant="danger"
        loading={deleting}
      />
    </div>
  );
}
