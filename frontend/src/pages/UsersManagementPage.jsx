import React, { useState, useEffect, useCallback, memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTenantStore } from '../stores/tenantStore';
import { useLanguageStore } from '../stores/languageStore';
import { usePermission } from '../hooks/usePermission';
import apiClient from '../services/apiClient';
import ConfirmModal from '../components/ConfirmModal';
import { useToast } from '../components/Toast';

// Skeleton optimisé
const TableSkeleton = memo(() => (
  <div className="animate-pulse">
    <div className="h-12 bg-gray-200 dark:bg-slate-700 rounded mb-3"></div>
    {[1,2,3,4,5].map(i => <div key={i} className="h-14 bg-gray-100 dark:bg-slate-800 rounded mb-2"></div>)}
  </div>
));

export default function UsersManagementPage() {
  const navigate = useNavigate();
  const { user, tenant } = useTenantStore();
  const { t } = useLanguageStore();
  const { can } = usePermission();
  const [users, setUsers] = useState([]);
  const [tableLoading, setTableLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [editingUser, setEditingUser] = useState(null);
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    phone: '',
    role: 'manager',
    status: 'active',
  });
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [posList, setPosList] = useState([]);
  const [warehouseList, setWarehouseList] = useState([]);
  const [tablesList, setTablesList] = useState([]);
  const [showAssignModal, setShowAssignModal] = useState(false);
  const [assigningUser, setAssigningUser] = useState(null);
  const [assignData, setAssignData] = useState({ pos_id: '', warehouse_id: '' });
  // Affiliations multiples pour serveurs
  const [showAffiliationModal, setShowAffiliationModal] = useState(false);
  const [affiliationData, setAffiliationData] = useState({ affiliated_tables: [], affiliated_pos: [] });

  const roles = [
    { value: 'manager', label: 'Gérant', color: 'blue' },
    { value: 'accountant', label: 'Comptable', color: 'yellow' },
    { value: 'magasinier_gros', label: 'Magasinier Gros', color: 'green' },
    { value: 'magasinier_detail', label: 'Magasinier Détail', color: 'green' },
    { value: 'caissier', label: 'Caissier', color: 'red' },
    { value: 'pos_server', label: 'Serveur POS', color: 'pink' },
    { value: 'auditor', label: 'Auditeur', color: 'gray' },
  ];

  // Fetch users - DOIT être avant tout return conditionnel
  const fetchUsers = useCallback(async () => {
    setTableLoading(true);
    try {
      const res = await apiClient.get('/users?per_page=50');
      setUsers(res.data?.data || []);
      setError('');
    } catch (err) {
      console.error('[UsersPage] Error:', err);
      setError(err.response?.data?.message || 'Erreur');
      setUsers([]);
    } finally {
      setTableLoading(false);
    }
  }, []);

  useEffect(() => {
    if (can('users.list')) {
      fetchUsers();
      // Charger POS, Warehouses et Tables pour l'assignation
      apiClient.get('/tenant-config/pos').then(r => setPosList(r.data?.data || [])).catch(() => {});
      apiClient.get('/warehouses').then(r => setWarehouseList(r.data?.data || [])).catch(() => {});
      apiClient.get('/pos/tables').then(r => setTablesList(r.data?.data || [])).catch(() => {});
    } else {
      setTableLoading(false);
    }
  }, [fetchUsers, can]);

  // RBAC Check - APRÈS les hooks
  if (!can('users.list')) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-red-600 mb-2">Accès Refusé</h1>
          <p className="text-gray-600 mb-6">Vous n'avez pas les permissions nécessaires</p>
          <button onClick={() => navigate('/dashboard')} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            Retour au dashboard
          </button>
        </div>
      </div>
    );
  }

  const handleCreateUser = async (e) => {
    e.preventDefault();
    setError('');

    try {
      if (editingUser) {
        // Update existing user
        await apiClient.put(`/users/${editingUser.id}`, {
          name: formData.name,
          email: formData.email,
          phone: formData.phone,
          role: formData.role,
          status: formData.status,
          password: formData.password || undefined,
        });
        setSuccess('Utilisateur mis à jour avec succès!');
      } else {
        // Create new user
        if (!formData.password) {
          setError('Le mot de passe est requis pour un nouvel utilisateur');
          return;
        }
        await apiClient.post('/users', formData);
        setSuccess('Utilisateur créé avec succès!');
      }

      setFormData({
        name: '',
        email: '',
        password: '',
        phone: '',
        role: 'manager',
        status: 'active',
      });
      setEditingUser(null);
      setShowModal(false);

      fetchUsers();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de l\'opération');
    }
  };

  const handleDeleteUser = async (userId) => {
    if (window.confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur?')) {
      try {
        await apiClient.delete(`/users/${userId}`);
        setSuccess('Utilisateur supprimé');
        fetchUsers();
      } catch (err) {
        setError('Erreur lors de la suppression');
      }
    }
  };

  const handleEditUser = (u) => {
    setEditingUser(u);
    setFormData({
      name: u.name,
      email: u.email,
      phone: u.phone || '',
      role: u.role,
      status: u.status || 'active',
      password: '',
    });
    setShowModal(true);
  };

  const [resetModal, setResetModal] = useState({ open: false, user: null });
  const [resetting, setResetting] = useState(false);
  const { toast } = useToast();

  const handleResetPasswordClick = (user) => {
    setResetModal({ open: true, user });
  };

  const handleResetPasswordConfirm = async () => {
    if (!resetModal.user) return;
    setResetting(true);
    try {
      const res = await apiClient.post(`/users/${resetModal.user.id}/reset-password`);
      toast.success(`Nouveau mot de passe: ${res.data.temp_password}`, { duration: 10000 });
      setResetModal({ open: false, user: null });
    } catch (err) {
      toast.error('Erreur lors de la réinitialisation');
    } finally {
      setResetting(false);
    }
  };

  const handleOpenAssign = (u) => {
    setAssigningUser(u);
    setAssignData({
      pos_id: u.assigned_pos_id || '',
      warehouse_id: u.assigned_warehouse_id || '',
    });
    setShowAssignModal(true);
  };

  const handleAssignPos = async (e) => {
    e.preventDefault();
    try {
      await apiClient.post(`/users/${assigningUser.id}/assign-pos`, assignData);
      setSuccess('Assignation mise à jour!');
      setShowAssignModal(false);
      fetchUsers();
    } catch (err) {
      setError('Erreur lors de l\'assignation');
    }
  };

  // Ouvrir le modal d'affiliation pour les serveurs
  const handleOpenAffiliation = (u) => {
    setAssigningUser(u);
    setAffiliationData({
      affiliated_tables: u.affiliated_tables?.map(t => t.id) || [],
      affiliated_pos: u.affiliated_pos?.map(p => p.id) || [],
    });
    setShowAffiliationModal(true);
  };

  // Gérer les affiliations multiples
  const handleAffiliationSubmit = async (e) => {
    e.preventDefault();
    try {
      await apiClient.put(`/users/${assigningUser.id}`, affiliationData);
      setSuccess('Affiliations mises à jour!');
      setShowAffiliationModal(false);
      fetchUsers();
    } catch (err) {
      setError('Erreur lors de la mise à jour des affiliations');
    }
  };

  // Toggle une table dans les affiliations
  const toggleTableAffiliation = (tableId) => {
    setAffiliationData(prev => ({
      ...prev,
      affiliated_tables: prev.affiliated_tables.includes(tableId)
        ? prev.affiliated_tables.filter(id => id !== tableId)
        : [...prev.affiliated_tables, tableId]
    }));
  };

  // Toggle un POS dans les affiliations
  const togglePosAffiliation = (posId) => {
    setAffiliationData(prev => ({
      ...prev,
      affiliated_pos: prev.affiliated_pos.includes(posId)
        ? prev.affiliated_pos.filter(id => id !== posId)
        : [...prev.affiliated_pos, posId]
    }));
  };

  // RENDU INSTANTANÉ - Pas de loading global
  return (
    <div className="min-h-screen p-6">
      {/* Header - Toujours visible */}
      <div className="mb-8 flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-gray-800">👥 Utilisateurs</h1>
          <p className="text-gray-600">Gérez les utilisateurs et collaborateurs du tenant</p>
        </div>
        <button onClick={() => setShowModal(true)} className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition">
          + Ajouter Utilisateur
        </button>
      </div>

      {/* Alerts */}
      {error && <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">{error}</div>}
      {success && <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">{success}</div>}

      {/* Users List - Skeleton local */}
      <div className="bg-white rounded-lg shadow overflow-hidden">
        {tableLoading ? <div className="p-4"><TableSkeleton /></div> : (
          <table className="w-full">
            <thead className="bg-gray-50 border-b">
              <tr>
                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Nom</th>
                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Email</th>
                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Rôle</th>
                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Statut</th>
                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Créé le</th>
                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Actions</th>
              </tr>
            </thead>
            <tbody>
              {users.length === 0 ? (
                <tr><td colSpan="6" className="px-6 py-8 text-center text-gray-500">Aucun utilisateur</td></tr>
              ) : users.map((u) => {
                const roleInfo = roles.find(r => r.value === u.role);
                return (
                  <tr key={u.id} className="border-b hover:bg-gray-50">
                    <td className="px-6 py-4 font-medium text-gray-800">{u.name}</td>
                    <td className="px-6 py-4 text-gray-600">{u.email}</td>
                    <td className="px-6 py-4">
                      <span className={`px-3 py-1 rounded-full text-sm font-medium bg-${roleInfo?.color}-100 text-${roleInfo?.color}-700`}>
                        {roleInfo?.label}
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      <span className={`px-3 py-1 rounded-full text-sm font-medium ${u.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                        {u.status === 'active' ? 'Actif' : 'Inactif'}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-gray-600">{u.createdAt}</td>
                    <td className="px-6 py-4 space-x-2">
                      <button onClick={() => handleEditUser(u)} className="text-blue-600 hover:text-blue-700 font-medium text-sm">Éditer</button>
                      {['caissier', 'pos_server', 'magasinier_gros', 'magasinier_detail'].includes(u.role) && (
                        <button onClick={() => handleOpenAssign(u)} className="text-purple-600 hover:text-purple-700 font-medium text-sm">Assigner</button>
                      )}
                      {['pos_server', 'serveur'].includes(u.role) && (
                        <button onClick={() => handleOpenAffiliation(u)} className="text-indigo-600 hover:text-indigo-700 font-medium text-sm">Affilier</button>
                      )}
                      <button onClick={() => handleResetPasswordClick(u)} className="text-yellow-600 hover:text-yellow-700 font-medium text-sm">MDP</button>
                      <button onClick={() => handleDeleteUser(u.id)} className="text-red-600 hover:text-red-700 font-medium text-sm">Supprimer</button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </div>

      {/* Modal Ajouter/Éditer Utilisateur */}
      {showModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
              <h2 className="text-xl font-bold">
                {editingUser ? 'Éditer Utilisateur' : 'Ajouter un Utilisateur'}
              </h2>
              <button
                onClick={() => {
                  setShowModal(false);
                  setEditingUser(null);
                  setFormData({
                    name: '',
                    email: '',
                    password: '',
                    phone: '',
                    role: 'manager',
                    status: 'active',
                  });
                }}
                className="text-gray-400 hover:text-gray-600"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleCreateUser} className="p-6 space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Nom Complet <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Email <span className="text-red-500">*</span>
                </label>
                <input
                  type="email"
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  disabled={!!editingUser}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Téléphone
                </label>
                <input
                  type="tel"
                  value={formData.phone}
                  onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                  placeholder="+229 XXXXXXXX"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Mot de passe {!editingUser && <span className="text-red-500">*</span>}
                </label>
                <input
                  type="password"
                  value={formData.password}
                  onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                  placeholder={editingUser ? 'Laisser vide pour ne pas changer' : ''}
                  required={!editingUser}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Rôle <span className="text-red-500">*</span>
                </label>
                <select
                  value={formData.role}
                  onChange={(e) => setFormData({ ...formData, role: e.target.value })}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                  required
                >
                  {roles.map((r) => (
                    <option key={r.value} value={r.value}>
                      {r.label}
                    </option>
                  ))}
                </select>
              </div>

              {editingUser && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Statut
                  </label>
                  <select
                    value={formData.status}
                    onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="active">Actif</option>
                    <option value="inactive">Inactif</option>
                    <option value="suspended">Suspendu</option>
                  </select>
                </div>
              )}

              <div className="flex gap-3 pt-4 border-t">
                <button
                  type="button"
                  onClick={() => {
                    setShowModal(false);
                    setEditingUser(null);
                    setFormData({
                      name: '',
                      email: '',
                      password: '',
                      phone: '',
                      role: 'manager',
                      status: 'active',
                    });
                  }}
                  className="flex-1 border border-gray-300 text-gray-700 hover:bg-gray-50 py-2 rounded-lg font-medium transition"
                >
                  Annuler
                </button>
                <button
                  type="submit"
                  className="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg font-medium transition"
                >
                  {editingUser ? 'Mettre à jour' : 'Créer'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Assignation POS/Warehouse */}
      {showAssignModal && assigningUser && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
              <h2 className="text-xl font-bold">Assigner {assigningUser.name}</h2>
              <button onClick={() => setShowAssignModal(false)} className="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <form onSubmit={handleAssignPos} className="p-6 space-y-4">
              {['caissier', 'pos_server'].includes(assigningUser.role) && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Point de Vente (POS)</label>
                  <select
                    value={assignData.pos_id}
                    onChange={(e) => setAssignData({ ...assignData, pos_id: e.target.value })}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2"
                  >
                    <option value="">-- Aucun --</option>
                    {posList.map(p => <option key={p.id} value={p.id}>{p.name} ({p.code})</option>)}
                  </select>
                </div>
              )}
              {['magasinier_gros', 'magasinier_detail'].includes(assigningUser.role) && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Entrepôt</label>
                  <select
                    value={assignData.warehouse_id}
                    onChange={(e) => setAssignData({ ...assignData, warehouse_id: e.target.value })}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2"
                  >
                    <option value="">-- Aucun --</option>
                    {warehouseList.map(w => <option key={w.id} value={w.id}>{w.name}</option>)}
                  </select>
                </div>
              )}
              <div className="flex gap-3 pt-4 border-t">
                <button type="button" onClick={() => setShowAssignModal(false)} className="flex-1 border border-gray-300 text-gray-700 hover:bg-gray-50 py-2 rounded-lg font-medium transition">
                  Annuler
                </button>
                <button type="submit" className="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg font-medium transition">
                  Assigner
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal Affiliations Multiples (Serveurs) */}
      {showAffiliationModal && assigningUser && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center sticky top-0 bg-white">
              <div>
                <h2 className="text-xl font-bold">Affiliations de {assigningUser.name}</h2>
                <p className="text-sm text-gray-500">Sélectionnez les tables et POS auxquels ce serveur est affilié</p>
              </div>
              <button onClick={() => setShowAffiliationModal(false)} className="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <form onSubmit={handleAffiliationSubmit} className="p-6 space-y-6">
              {/* Affiliations POS */}
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-3">
                  Points de Vente (POS) affiliés
                </label>
                <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
                  {posList.length === 0 ? (
                    <p className="text-gray-500 text-sm col-span-3">Aucun POS disponible</p>
                  ) : posList.map(pos => (
                    <label 
                      key={pos.id} 
                      className={`flex items-center p-3 border rounded-lg cursor-pointer transition ${
                        affiliationData.affiliated_pos.includes(pos.id) 
                          ? 'border-indigo-500 bg-indigo-50' 
                          : 'border-gray-200 hover:border-gray-300'
                      }`}
                    >
                      <input
                        type="checkbox"
                        checked={affiliationData.affiliated_pos.includes(pos.id)}
                        onChange={() => togglePosAffiliation(pos.id)}
                        className="mr-2 h-4 w-4 text-indigo-600 rounded"
                      />
                      <span className="text-sm font-medium">{pos.name}</span>
                    </label>
                  ))}
                </div>
              </div>

              {/* Affiliations Tables */}
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-3">
                  Tables affiliées
                </label>
                <div className="grid grid-cols-3 md:grid-cols-4 gap-2">
                  {tablesList.length === 0 ? (
                    <p className="text-gray-500 text-sm col-span-4">Aucune table disponible</p>
                  ) : tablesList.map(table => (
                    <label 
                      key={table.id} 
                      className={`flex items-center justify-center p-3 border rounded-lg cursor-pointer transition ${
                        affiliationData.affiliated_tables.includes(table.id) 
                          ? 'border-green-500 bg-green-50' 
                          : 'border-gray-200 hover:border-gray-300'
                      }`}
                    >
                      <input
                        type="checkbox"
                        checked={affiliationData.affiliated_tables.includes(table.id)}
                        onChange={() => toggleTableAffiliation(table.id)}
                        className="mr-2 h-4 w-4 text-green-600 rounded"
                      />
                      <span className="text-sm font-medium">Table {table.number || table.name}</span>
                    </label>
                  ))}
                </div>
              </div>

              {/* Résumé */}
              <div className="bg-gray-50 rounded-lg p-4">
                <h4 className="font-medium text-gray-700 mb-2">Résumé des affiliations</h4>
                <div className="flex gap-4 text-sm">
                  <span className="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full">
                    {affiliationData.affiliated_pos.length} POS
                  </span>
                  <span className="bg-green-100 text-green-700 px-3 py-1 rounded-full">
                    {affiliationData.affiliated_tables.length} Tables
                  </span>
                </div>
              </div>

              <div className="flex gap-3 pt-4 border-t">
                <button 
                  type="button" 
                  onClick={() => setShowAffiliationModal(false)} 
                  className="flex-1 border border-gray-300 text-gray-700 hover:bg-gray-50 py-2 rounded-lg font-medium transition"
                >
                  Annuler
                </button>
                <button 
                  type="submit" 
                  className="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg font-medium transition"
                >
                  Enregistrer les affiliations
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal de confirmation de réinitialisation mot de passe */}
      <ConfirmModal
        isOpen={resetModal.open}
        onClose={() => setResetModal({ open: false, user: null })}
        onConfirm={handleResetPasswordConfirm}
        title="Réinitialiser le mot de passe"
        message={`Voulez-vous réinitialiser le mot de passe de "${resetModal.user?.name}" ? Un nouveau mot de passe temporaire sera généré.`}
        confirmText="Réinitialiser"
        cancelText="Annuler"
        variant="warning"
        loading={resetting}
      />
    </div>
  );
}
