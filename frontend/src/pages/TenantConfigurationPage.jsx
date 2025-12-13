import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTenantStore } from '../stores/tenantStore';
import apiClient from '../services/apiClient';

// Skeleton pour chargement rapide
const FormSkeleton = () => (
  <div className="animate-pulse space-y-4">
    {[1,2,3,4].map(i => (
      <div key={i} className="h-12 bg-gray-200 rounded"></div>
    ))}
  </div>
);

export default function TenantConfigurationPage() {
  const { tenant, user } = useTenantStore();
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState('general');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const [config, setConfig] = useState({
    tva_rate: 18,
    default_markup: 30,
    stock_policy: 'cmp',
    allow_credit: false,
    credit_limit: 0,
  });

  const [paymentMethods, setPaymentMethods] = useState([]);
  const [posList, setPosList] = useState([]);
  const [showPosModal, setShowPosModal] = useState(false);
  const [posForm, setPosForm] = useState({
    name: '',
    code: '',
    location: '',
    responsible_user_id: '',
  });

  // Fetch config - AVANT tout return conditionnel
  const fetchConfig = useCallback(async () => {
    try {
      // Chargement parallèle pour rapidité
      const [configRes, paymentRes, posRes] = await Promise.all([
        apiClient.get('/tenant-config').catch(() => ({ data: { data: {} } })),
        apiClient.get('/tenant-config/payment-methods').catch(() => ({ data: { data: [] } })),
        apiClient.get('/tenant-config/pos').catch(() => ({ data: { data: [] } })),
      ]);

      if (configRes.data?.data?.tenant) {
        setConfig(configRes.data.data.tenant);
      }
      setPaymentMethods(paymentRes.data?.data || []);
      setPosList(posRes.data?.data || []);
    } catch (err) {
      setError('Erreur lors du chargement');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (user && user.role === 'owner') {
      fetchConfig();
    } else {
      setLoading(false);
    }
  }, [user, fetchConfig]);

  // Redirection si pas owner - APRÈS les hooks
  if (user && user.role !== 'owner') {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-red-600 mb-2">Accès Refusé</h1>
          <p className="text-gray-600 mb-6">Réservé au propriétaire</p>
          <button onClick={() => navigate('/dashboard')} className="px-4 py-2 bg-blue-600 text-white rounded-lg">
            Retour
          </button>
        </div>
      </div>
    );
  }

  const handleUpdateConfig = async (e) => {
    e.preventDefault();
    try {
      setLoading(true);
      await apiClient.put('/tenant-config', config);
      setSuccess('Configuration mise à jour!');
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError('Erreur lors de la mise à jour');
    } finally {
      setLoading(false);
    }
  };

  const handleCreatePos = async (e) => {
    e.preventDefault();
    try {
      setLoading(true);
      const response = await apiClient.post('/tenant-config/pos', posForm);
      setPosList([...posList, response.data.data]);
      setPosForm({ name: '', code: '', location: '', responsible_user_id: '' });
      setShowPosModal(false);
      setSuccess('POS créé avec succès!');
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la création du POS');
    } finally {
      setLoading(false);
    }
  };

  if (!user || user.role !== 'owner') {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="text-center">
          <h2 className="text-2xl font-bold text-gray-800">Accès Refusé</h2>
          <p className="text-gray-600 mt-2">Seul le propriétaire peut accéder à cette page</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 p-4">
      <div className="max-w-6xl mx-auto">
        {/* Header */}
        <div className="mb-6">
          <h1 className="text-3xl font-bold text-gray-800">Configuration de l'Entreprise</h1>
          <p className="text-gray-600 mt-1">{tenant?.name}</p>
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

        {/* Tabs */}
        <div className="bg-white rounded-lg shadow mb-6">
          <div className="border-b border-gray-200 flex">
            {[
              { id: 'general', label: 'Général', icon: '⚙️' },
              { id: 'finance', label: 'Finances', icon: '💰' },
              { id: 'payment', label: 'Paiements', icon: '💳' },
              { id: 'pos', label: 'Points de Vente', icon: '🏪' },
            ].map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`flex-1 py-4 px-6 border-b-2 font-medium transition ${
                  activeTab === tab.id
                    ? 'border-blue-500 text-blue-600'
                    : 'border-transparent text-gray-600 hover:text-gray-800'
                }`}
              >
                <span className="mr-2">{tab.icon}</span>
                {tab.label}
              </button>
            ))}
          </div>

          <div className="p-6">
            {/* Onglet Général */}
            {activeTab === 'general' && (
              <form onSubmit={handleUpdateConfig} className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Mode POS <span className="text-red-500">*</span>
                    </label>
                    <select
                      value={tenant?.mode_pos || 'A'}
                      onChange={(e) => {
                        // Vous pouvez implémenter un appel API pour changer le mode si nécessaire
                        alert('Le mode POS ne peut être changé qu\'à la création du tenant.');
                      }}
                      disabled
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50"
                    >
                      <option value="A">Mode A (Gros + Détail)</option>
                      <option value="B">Mode B (Gros + Détail + POS)</option>
                    </select>
                    <p className="text-xs text-gray-500 mt-1">
                      {tenant?.mode_pos === 'A' 
                        ? 'Mode A: 2 entrepôts (Gros + Détail)' 
                        : 'Mode B: 3 entrepôts (Gros + Détail + POS)'}
                    </p>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Devise
                    </label>
                    <input
                      type="text"
                      value={tenant?.currency || 'XOF'}
                      disabled
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Adresse
                  </label>
                  <input
                    type="text"
                    value={tenant?.address || ''}
                    disabled
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50"
                  />
                </div>
              </form>
            )}

            {/* Onglet Finances */}
            {activeTab === 'finance' && (
              <form onSubmit={handleUpdateConfig} className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Taux TVA (%) <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      value={config.tva_rate}
                      onChange={(e) => setConfig({ ...config, tva_rate: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Marge Par Défaut (%) <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      value={config.default_markup}
                      onChange={(e) => setConfig({ ...config, default_markup: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Méthode d'Évaluation du Stock <span className="text-red-500">*</span>
                  </label>
                  <select
                    value={config.stock_policy}
                    onChange={(e) => setConfig({ ...config, stock_policy: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="fifo">FIFO (First In First Out)</option>
                    <option value="lifo">LIFO (Last In First Out)</option>
                    <option value="cmp">CMP (Coût Moyen Pondéré)</option>
                  </select>
                </div>

                <div className="border-t pt-4">
                  <label className="flex items-center">
                    <input
                      type="checkbox"
                      checked={config.allow_credit}
                      onChange={(e) => setConfig({ ...config, allow_credit: e.target.checked })}
                      className="rounded border-gray-300"
                    />
                    <span className="ml-2 text-sm font-medium text-gray-700">
                      Autoriser le Crédit Client
                    </span>
                  </label>
                  {config.allow_credit && (
                    <div className="mt-3">
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Limite de Crédit
                      </label>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={config.credit_limit}
                        onChange={(e) => setConfig({ ...config, credit_limit: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                      />
                    </div>
                  )}
                </div>

                <button
                  type="submit"
                  disabled={loading}
                  className="w-full bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700 disabled:bg-gray-400 transition"
                >
                  {loading ? 'Mise à jour...' : 'Sauvegarder'}
                </button>
              </form>
            )}

            {/* Onglet Paiements */}
            {activeTab === 'payment' && (
              <div className="space-y-4">
                {[
                  { type: 'especes', label: 'Espèces', icon: '💵' },
                  { type: 'kkiapay', label: 'Kkiapay', icon: '📱' },
                  { type: 'fedapay', label: 'FedaPay', icon: '💳' },
                  { type: 'virement', label: 'Virement Bancaire', icon: '🏦' },
                ].map((method) => {
                  const active = paymentMethods.find((m) => m.type === method.type);
                  return (
                    <div key={method.type} className="border rounded-lg p-4 flex justify-between items-center">
                      <div>
                        <h3 className="font-medium text-gray-800">
                          {method.icon} {method.label}
                        </h3>
                        <p className="text-sm text-gray-600">
                          {active ? '✅ Activé' : '⏱️ Non configuré'}
                        </p>
                      </div>
                      <button
                        onClick={() => setActiveTab('pos')}
                        className="px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100"
                      >
                        Configurer
                      </button>
                    </div>
                  );
                })}
              </div>
            )}

            {/* Onglet POS */}
            {activeTab === 'pos' && (
              <div className="space-y-4">
                <button
                  onClick={() => setShowPosModal(true)}
                  className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
                >
                  + Ajouter un POS
                </button>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {posList.map((pos) => (
                    <div key={pos.id} className="border rounded-lg p-4 bg-gray-50">
                      <h3 className="font-medium text-gray-800">{pos.name}</h3>
                      <p className="text-sm text-gray-600">Code: {pos.code}</p>
                      <p className="text-sm text-gray-600">Localisation: {pos.location || 'Non spécifiée'}</p>
                      <div className="mt-2">
                        <span
                          className={`inline-block px-2 py-1 text-xs font-medium rounded ${
                            pos.status === 'active'
                              ? 'bg-green-100 text-green-800'
                              : 'bg-red-100 text-red-800'
                          }`}
                        >
                          {pos.status === 'active' ? 'Actif' : 'Inactif'}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>

                {/* Modal Créer POS */}
                {showPosModal && (
                  <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                    <div className="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                      <h2 className="text-xl font-bold mb-4">Créer un POS</h2>
                      <form onSubmit={handleCreatePos} className="space-y-4">
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Nom <span className="text-red-500">*</span>
                          </label>
                          <input
                            type="text"
                            value={posForm.name}
                            onChange={(e) => setPosForm({ ...posForm, name: e.target.value })}
                            required
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="Ex: POS Cantine"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Code <span className="text-red-500">*</span>
                          </label>
                          <input
                            type="text"
                            value={posForm.code}
                            onChange={(e) => setPosForm({ ...posForm, code: e.target.value })}
                            required
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="Ex: POS-001"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Localisation
                          </label>
                          <input
                            type="text"
                            value={posForm.location}
                            onChange={(e) => setPosForm({ ...posForm, location: e.target.value })}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="Ex: Dakar - Centre Ville"
                          />
                        </div>
                        <div className="flex gap-2">
                          <button
                            type="submit"
                            disabled={loading}
                            className="flex-1 bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700 disabled:bg-gray-400"
                          >
                            {loading ? 'Création...' : 'Créer'}
                          </button>
                          <button
                            type="button"
                            onClick={() => setShowPosModal(false)}
                            className="flex-1 bg-gray-200 text-gray-800 py-2 rounded-lg font-medium hover:bg-gray-300"
                          >
                            Annuler
                          </button>
                        </div>
                      </form>
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
