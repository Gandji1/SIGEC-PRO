import React, { useState, useEffect } from 'react';
import { useTenantStore } from '../stores/tenantStore';
import apiClient from '../services/apiClient';

export default function TenantManagementPage() {
  const { user, tenant } = useTenantStore();
  const [tenants, setTenants] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    country: 'BJ',
    currency: 'XOF',
    business_type: 'retail',
    owner_name: '',
    owner_email: '',
    owner_password: 'password123',
  });
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  useEffect(() => {
    if (user?.role === 'super_admin') {
      fetchTenants();
    }
  }, [user]);

  const fetchTenants = async () => {
    setLoading(true);
    try {
      const res = await apiClient.get('/superadmin/tenants');
      // L'API retourne une réponse paginée avec data dans res.data.data
      const list = Array.isArray(res.data?.data) ? res.data.data : (Array.isArray(res.data) ? res.data : []);
      setTenants(list.filter(t => t.slug !== 'system-admin').map(t => ({
        id: t.id,
        name: t.name,
        email: t.email || '-',
        status: t.status || 'active',
        users: t.users_count || t.users?.length || 0,
        plan: t.plan_id ? `Plan #${t.plan_id}` : 'Aucun'
      })));
      setError('');
    } catch (err) {
      console.error('Fetch tenants error:', err);
      setError(err.response?.data?.message || 'Erreur lors du chargement des tenants');
      setTenants([]);
    } finally {
      setLoading(false);
    }
  };

  const handleCreateTenant = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    try {
      const payload = {
        name: formData.name.trim(),
        email: formData.email.trim(),
        phone: formData.phone.trim(),
        business_type: formData.business_type,
        country: formData.country,
        currency: formData.currency,
        owner_name: formData.owner_name.trim() || formData.name.trim() + ' Owner',
        owner_email: formData.owner_email.trim(),
        owner_password: formData.owner_password || 'password123'
      };
      const res = await apiClient.post('/superadmin/tenants', payload);
      if (res.data?.success) {
        setSuccess(`✅ Tenant créé: ${res.data.data.name}`);
        setShowModal(false);
        setFormData({
          name: '',
          email: '',
          phone: '',
          country: 'BJ',
          currency: 'XOF',
          business_type: 'retail',
          owner_name: '',
          owner_email: '',
          owner_password: 'password123',
        });
        fetchTenants();
        setTimeout(() => setSuccess(''), 4000);
      } else {
        setError('Réponse inattendue du serveur');
      }
    } catch (err) {
      setError(err.response?.data?.message || err.message || 'Erreur lors de la création');
    }
  };

  if (!user) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-red-600 mb-2">Non authentifié</h1>
          <p className="text-gray-600">Veuillez vous connecter en tant que SuperAdmin</p>
          <a href="/login" className="mt-4 inline-block text-blue-600 hover:underline">Se connecter</a>
        </div>
      </div>
    );
  }

  if (user.role !== 'super_admin') {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-red-600 mb-2">Accès Refusé</h1>
          <p className="text-gray-600">Cette page est réservée au SuperAdmin</p>
          <p className="text-sm text-gray-500 mt-2">Votre rôle: {user.role}</p>
        </div>
      </div>
    );
  }

  if (loading) {
    return <div className="flex items-center justify-center h-screen">Chargement...</div>;
  }

  return (
    <div className="min-h-screen p-6">
      {/* Header */}
      <div className="mb-8 flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-gray-800">Gestion des Tenants</h1>
          <p className="text-gray-600">Gérez tous les tenants de la plateforme</p>
        </div>
        <button
          onClick={() => setShowModal(true)}
          className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition"
        >
          + Nouveau Tenant
        </button>
      </div>

      {/* Alerts */}
      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
          {error}
        </div>
      )}
      {success && (
        <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
          {success}
        </div>
      )}

      {/* Tenants List */}
      <div className="bg-white rounded-lg shadow overflow-hidden">
        <table className="w-full">
          <thead className="bg-gray-50 border-b">
            <tr>
              <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Nom</th>
              <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Email</th>
              <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Statut</th>
              <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Utilisateurs</th>
              <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Plan</th>
              <th className="px-6 py-3 text-left text-sm font-semibold text-gray-700">Actions</th>
            </tr>
          </thead>
          <tbody>
            {tenants.map((t) => (
              <tr key={t.id} className="border-b hover:bg-gray-50">
                <td className="px-6 py-4 font-medium text-gray-800">{t.name}</td>
                <td className="px-6 py-4 text-gray-600">{t.email}</td>
                <td className="px-6 py-4">
                  <span className={`px-3 py-1 rounded-full text-sm font-medium ${
                    t.status === 'active' 
                      ? 'bg-green-100 text-green-700' 
                      : 'bg-red-100 text-red-700'
                  }`}>
                    {t.status === 'active' ? 'Actif' : 'Suspendu'}
                  </span>
                </td>
                <td className="px-6 py-4 text-gray-600">{t.users}</td>
                <td className="px-6 py-4">
                  <span className="px-3 py-1 bg-blue-100 text-blue-700 rounded text-sm font-medium">
                    {t.plan}
                  </span>
                </td>
                <td className="px-6 py-4 space-x-2">
                  <button className="text-blue-600 hover:text-blue-700 font-medium text-sm">
                    Éditer
                  </button>
                  <button className="text-red-600 hover:text-red-700 font-medium text-sm">
                    Supprimer
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Modal Créer Tenant */}
      {showModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
              <h2 className="text-xl font-bold">Créer un Nouveau Tenant</h2>
              <button
                onClick={() => setShowModal(false)}
                className="text-gray-400 hover:text-gray-600"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleCreateTenant} className="p-6 space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Nom du Tenant
                </label>
                <input
                  type="text"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Email
                </label>
                <input
                  type="email"
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2"
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
                  className="w-full border border-gray-300 rounded-lg px-3 py-2"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Pays
                  </label>
                  <select
                    value={formData.country}
                    onChange={(e) => setFormData({ ...formData, country: e.target.value })}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2"
                  >
                    <option value="BJ">Bénin</option>
                    <option value="SN">Sénégal</option>
                    <option value="CI">Côte d'Ivoire</option>
                    <option value="TG">Togo</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Devise
                  </label>
                  <select
                    value={formData.currency}
                    onChange={(e) => setFormData({ ...formData, currency: e.target.value })}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2"
                  >
                    <option value="XOF">XOF (CFA)</option>
                    <option value="EUR">EUR (€)</option>
                    <option value="USD">USD ($)</option>
                  </select>
                </div>
              </div>

              <div className="border-t pt-4 mt-4">
                <h3 className="font-medium text-gray-800 mb-3">Propriétaire du Tenant</h3>
                <div className="space-y-3">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Nom du propriétaire
                    </label>
                    <input
                      type="text"
                      value={formData.owner_name}
                      onChange={(e) => setFormData({ ...formData, owner_name: e.target.value })}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2"
                      placeholder="Nom complet"
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Email du propriétaire
                    </label>
                    <input
                      type="email"
                      value={formData.owner_email}
                      onChange={(e) => setFormData({ ...formData, owner_email: e.target.value })}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2"
                      placeholder="email@exemple.com"
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Mot de passe
                    </label>
                    <input
                      type="text"
                      value={formData.owner_password}
                      onChange={(e) => setFormData({ ...formData, owner_password: e.target.value })}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2"
                      placeholder="Mot de passe initial"
                    />
                  </div>
                </div>
              </div>

              <div className="flex gap-3 pt-4">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="flex-1 border border-gray-300 text-gray-700 hover:bg-gray-50 py-2 rounded-lg font-medium transition"
                >
                  Annuler
                </button>
                <button
                  type="submit"
                  className="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg font-medium transition"
                >
                  Créer
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
