import { useState, useEffect, useCallback, useMemo, memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Plus, Trash2, Edit2, Phone, Mail, MapPin, DollarSign, ShoppingCart, Key, XCircle, CheckCircle, Eye, EyeOff } from 'lucide-react';
import apiClient from '../services/apiClient';
import { usePermission } from '../hooks/usePermission';
import ConfirmModal from '../components/ConfirmModal';
import { useToast } from '../components/Toast';
import { useTenantStore } from '../stores/tenantStore';

// Skeleton optimisé
const TableSkeleton = memo(() => (
  <div className="animate-pulse">
    <div className="h-12 bg-gray-200 rounded mb-3"></div>
    {[1,2,3,4,5].map(i => <div key={i} className="h-16 bg-gray-100 rounded mb-2"></div>)}
  </div>
));

export default function SuppliersPage() {
  const navigate = useNavigate();
  const { can } = usePermission();
  const { toast } = useToast();
  const { user } = useTenantStore();
  const [suppliers, setSuppliers] = useState([]);
  const [tableLoading, setTableLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [deleteModal, setDeleteModal] = useState({ open: false, supplier: null });
  const [deleting, setDeleting] = useState(false);
  const [portalModal, setPortalModal] = useState({ open: false, supplier: null });
  const [portalEmail, setPortalEmail] = useState('');
  const [portalPassword, setPortalPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [portalLoading, setPortalLoading] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    contact_person: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    country: '',
    tax_id: '',
  });

  const fetchSuppliers = useCallback(async () => {
    setTableLoading(true);
    try {
      const response = await apiClient.get('/suppliers?per_page=50');
      setSuppliers(response.data?.data || response.data || []);
    } catch (error) {
      console.error('Error fetching suppliers:', error);
      setSuppliers([]);
    } finally {
      setTableLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchSuppliers();
  }, [fetchSuppliers]);

  // Filtrage local
  const filteredSuppliers = useMemo(() => {
    if (!searchTerm) return suppliers;
    const term = searchTerm.toLowerCase();
    return suppliers.filter(s => 
      s.name?.toLowerCase().includes(term) ||
      s.contact_person?.toLowerCase().includes(term) ||
      s.email?.toLowerCase().includes(term)
    );
  }, [suppliers, searchTerm]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!can('suppliers.create') && !editingId && !can('suppliers.edit')) {
      alert('Pas de permission pour créer/modifier un fournisseur');
      return;
    }

    try {
      if (editingId) {
        await apiClient.put(`/suppliers/${editingId}`, formData);
      } else {
        await apiClient.post('/suppliers', formData);
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
      });
      setEditingId(null);
      setShowForm(false);
      fetchSuppliers();
    } catch (error) {
      console.error('Error saving supplier:', error);
      alert(error.response?.data?.message || 'Erreur lors de la sauvegarde');
    }
  };

  const handleEdit = (supplier) => {
    setFormData(supplier);
    setEditingId(supplier.id);
    setShowForm(true);
  };

  const handleDeleteClick = (supplier) => {
    if (!can('suppliers.delete')) {
      toast.error('Vous n\'avez pas la permission de supprimer');
      return;
    }
    setDeleteModal({ open: true, supplier });
  };

  const handleDeleteConfirm = async () => {
    if (!deleteModal.supplier) return;
    setDeleting(true);
    try {
      await apiClient.delete(`/suppliers/${deleteModal.supplier.id}`);
      toast.success(`Fournisseur "${deleteModal.supplier.name}" supprimé`);
      fetchSuppliers();
      setDeleteModal({ open: false, supplier: null });
    } catch (error) {
      console.error('Error deleting supplier:', error);
      toast.error(error.response?.data?.message || 'Erreur lors de la suppression');
    } finally {
      setDeleting(false);
    }
  };

  // Activer le portail fournisseur
  const handleEnablePortal = async () => {
    if (!portalModal.supplier || !portalEmail || !portalPassword) {
      toast.error('Email et mot de passe requis');
      return;
    }
    if (portalPassword.length < 6) {
      toast.error('Le mot de passe doit contenir au moins 6 caractères');
      return;
    }
    setPortalLoading(true);
    try {
      await apiClient.post(`/suppliers/${portalModal.supplier.id}/enable-portal`, {
        email: portalEmail,
        password: portalPassword
      });
      toast.success('Portail activé! Le fournisseur peut se connecter avec: ' + portalEmail);
      setPortalModal({ open: false, supplier: null });
      setPortalEmail('');
      setPortalPassword('');
      fetchSuppliers();
    } catch (error) {
      console.error('Error enabling portal:', error);
      toast.error(error.response?.data?.message || 'Erreur lors de l\'activation');
    } finally {
      setPortalLoading(false);
    }
  };

  // Désactiver le portail fournisseur
  const handleDisablePortal = async (supplier) => {
    if (!window.confirm(`Désactiver l'accès portail pour ${supplier.name}?`)) return;
    try {
      await apiClient.post(`/suppliers/${supplier.id}/disable-portal`);
      toast.success('Portail désactivé');
      fetchSuppliers();
    } catch (error) {
      console.error('Error disabling portal:', error);
      toast.error(error.response?.data?.message || 'Erreur');
    }
  };

  const isOwnerOrManager = user?.role === 'owner' || user?.role === 'manager' || user?.role === 'admin';

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Fournisseurs</h1>
          <p className="text-gray-600 mt-1">Gestion des fournisseurs et approvisionnements</p>
        </div>
        {can('suppliers.create') && (
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
              });
              setEditingId(null);
              setShowForm(!showForm);
            }}
            className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition"
          >
            <Plus size={20} />
            Ajouter Fournisseur
          </button>
        )}
      </div>

      {/* Form */}
      {showForm && (
        <div className="bg-white rounded-lg shadow-md p-6">
          <h2 className="text-xl font-bold mb-4">
            {editingId ? 'Modifier Fournisseur' : 'Nouveau Fournisseur'}
          </h2>
          <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input
              type="text"
              placeholder="Nom du fournisseur *"
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

      {/* Suppliers List - Skeleton local */}
      {tableLoading ? (
        <div className="bg-white rounded-lg shadow p-6"><TableSkeleton /></div>
      ) : filteredSuppliers.length === 0 ? (
        <div className="bg-gray-100 rounded-lg p-12 text-center">
          <p className="text-gray-600">Aucun fournisseur</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {filteredSuppliers.map((supplier) => (
            <div key={supplier.id} className="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
              <div className="flex justify-between items-start mb-4">
                <div className="flex-1">
                  <h3 className="text-lg font-bold text-gray-900">{supplier.name}</h3>
                  {supplier.contact_person && (
                    <p className="text-sm text-gray-600">{supplier.contact_person}</p>
                  )}
                </div>
                <div className="flex gap-2">
                  {can('suppliers.edit') && (
                    <button
                      onClick={() => handleEdit(supplier)}
                      className="text-blue-600 hover:text-blue-800 transition"
                    >
                      <Edit2 size={16} />
                    </button>
                  )}
                  {can('suppliers.delete') && (
                    <button
                      onClick={() => handleDeleteClick(supplier)}
                      className="text-red-600 hover:text-red-800 transition"
                    >
                      <Trash2 size={16} />
                    </button>
                  )}
                </div>
              </div>

              <div className="space-y-2 text-sm text-gray-600">
                {supplier.phone && (
                  <div className="flex items-center gap-2">
                    <Phone size={16} />
                    {supplier.phone}
                  </div>
                )}
                {supplier.email && (
                  <div className="flex items-center gap-2">
                    <Mail size={16} />
                    {supplier.email}
                  </div>
                )}
                {supplier.address && (
                  <div className="flex items-center gap-2">
                    <MapPin size={16} />
                    {supplier.address} {supplier.city && `(${supplier.city})`}
                  </div>
                )}
              </div>

              {/* Badge portail */}
              {supplier.has_portal_access && (
                <div className="mt-3 flex items-center gap-1 text-xs text-green-600 bg-green-50 px-2 py-1 rounded">
                  <CheckCircle size={12} />
                  Portail actif ({supplier.portal_email})
                </div>
              )}

              <div className="mt-4 pt-4 border-t border-gray-200 flex gap-2">
                <button
                  onClick={() => navigate(`/purchases?supplier=${supplier.id}`)}
                  className="flex-1 bg-purple-100 text-purple-700 hover:bg-purple-200 text-xs font-semibold py-2 rounded transition"
                >
                  <ShoppingCart size={14} className="inline mr-1" />
                  Achats
                </button>
                
                {/* Bouton portail - Owner/Manager uniquement */}
                {isOwnerOrManager && (
                  supplier.has_portal_access ? (
                    <button
                      onClick={() => handleDisablePortal(supplier)}
                      className="flex-1 bg-red-100 text-red-700 hover:bg-red-200 text-xs font-semibold py-2 rounded transition"
                      title="Désactiver le portail"
                    >
                      <XCircle size={14} className="inline mr-1" />
                      Désactiver
                    </button>
                  ) : (
                    <button
                      onClick={() => {
                        setPortalModal({ open: true, supplier });
                        setPortalEmail(supplier.email || '');
                      }}
                      className="flex-1 bg-green-100 text-green-700 hover:bg-green-200 text-xs font-semibold py-2 rounded transition"
                      title="Activer le portail fournisseur"
                    >
                      <Key size={14} className="inline mr-1" />
                      Portail
                    </button>
                  )
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Modal de confirmation de suppression */}
      <ConfirmModal
        isOpen={deleteModal.open}
        onClose={() => setDeleteModal({ open: false, supplier: null })}
        onConfirm={handleDeleteConfirm}
        title="Supprimer le fournisseur"
        message={`Êtes-vous sûr de vouloir supprimer "${deleteModal.supplier?.name}" ? Cette action est irréversible.`}
        confirmText="Supprimer"
        cancelText="Annuler"
        variant="danger"
        loading={deleting}
      />

      {/* Modal d'activation du portail fournisseur */}
      {portalModal.open && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
            <h2 className="text-xl font-bold mb-2">Activer le Portail Fournisseur</h2>
            <p className="text-gray-600 mb-4">
              Fournisseur: <strong>{portalModal.supplier?.name}</strong>
            </p>
            
            <div className="mb-4">
              <label className="block text-sm font-medium mb-1">Email de connexion</label>
              <input
                type="email"
                value={portalEmail}
                onChange={(e) => setPortalEmail(e.target.value)}
                className="w-full border rounded-lg px-3 py-2"
                placeholder="email@fournisseur.com"
                required
              />
            </div>

            <div className="mb-4">
              <label className="block text-sm font-medium mb-1">Mot de passe</label>
              <div className="relative">
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={portalPassword}
                  onChange={(e) => setPortalPassword(e.target.value)}
                  className="w-full border rounded-lg px-3 py-2 pr-10"
                  placeholder="Minimum 6 caractères"
                  minLength={6}
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                >
                  {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
              <p className="text-xs text-gray-500 mt-1">
                Communiquez ces identifiants au fournisseur de manière sécurisée.
              </p>
            </div>

            <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 text-sm text-blue-800">
              <strong>Le fournisseur pourra :</strong>
              <ul className="list-disc list-inside mt-1">
                <li>Voir les commandes reçues</li>
                <li>Confirmer ou rejeter les commandes</li>
                <li>Indiquer les dates de livraison</li>
                <li>Marquer les commandes comme expédiées</li>
              </ul>
            </div>

            <div className="flex gap-3">
              <button
                onClick={() => {
                  setPortalModal({ open: false, supplier: null });
                  setPortalEmail('');
                }}
                className="flex-1 border border-gray-300 py-2 rounded-lg font-medium"
              >
                Annuler
              </button>
              <button
                onClick={handleEnablePortal}
                disabled={portalLoading || !portalEmail || !portalPassword || portalPassword.length < 6}
                className="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg font-medium disabled:opacity-50"
              >
                {portalLoading ? 'Activation...' : 'Activer le Portail'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
