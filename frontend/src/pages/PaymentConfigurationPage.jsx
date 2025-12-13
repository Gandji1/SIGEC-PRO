import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTenantStore } from '../stores/tenantStore';
import apiClient from '../services/apiClient';

export default function PaymentConfigurationPage() {
  const { user, tenant } = useTenantStore();
  const navigate = useNavigate();

  const [paymentMethods, setPaymentMethods] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [editingMethod, setEditingMethod] = useState(null);
  const [formData, setFormData] = useState({
    type: '',
    name: '',
    is_active: false,
    api_key: '',
    api_secret: '',
  });

  useEffect(() => {
    if (!user || user.role !== 'owner') {
      navigate('/dashboard');
      return;
    }
    fetchPaymentMethods();
  }, [user]);

  const fetchPaymentMethods = async () => {
    try {
      setLoading(true);
      const response = await apiClient.get('/tenant-config/payment-methods');
      setPaymentMethods(response.data.data || []);
    } catch (err) {
      setError('Erreur lors du chargement des moyens de paiement');
    } finally {
      setLoading(false);
    }
  };

  const handleSaveMethod = async (e) => {
    e.preventDefault();
    try {
      setLoading(true);
      await apiClient.post('/tenant-config/payment-methods', formData);
      setSuccess('Moyen de paiement configuré!');
      setFormData({ type: '', name: '', is_active: false, api_key: '', api_secret: '' });
      fetchPaymentMethods();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la configuration');
    } finally {
      setLoading(false);
    }
  };

  if (!user || user.role !== 'owner') {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="text-center">
          <h2 className="text-2xl font-bold text-gray-800">Accès Refusé</h2>
        </div>
      </div>
    );
  }

  const paymentTypes = [
    { value: 'cash', label: 'Espèces', icon: '💵', requiresApi: false },
    { value: 'momo', label: 'MTN MoMo', icon: '📱', requiresApi: true },
    { value: 'kkiapay', label: 'Kkiapay', icon: '📱', requiresApi: true },
    { value: 'fedapay', label: 'FedaPay', icon: '💳', requiresApi: true },
    { value: 'card', label: 'Carte Bancaire', icon: '💳', requiresApi: true },
    { value: 'virement', label: 'Virement Bancaire', icon: '🏦', requiresApi: false },
    { value: 'cheque', label: 'Chèque', icon: '📄', requiresApi: false },
  ];

  const getPaymentTypeInfo = (type) => {
    return paymentTypes.find((t) => t.value === type);
  };

  const isConfigured = (type) => {
    return paymentMethods.some((m) => m.type === type && m.is_active);
  };

  return (
    <div className="min-h-screen bg-gray-50 p-4">
      <div className="max-w-6xl mx-auto">
        {/* Header */}
        <div className="mb-6">
          <h1 className="text-3xl font-bold text-gray-800">Configuration Moyens de Paiement</h1>
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

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Formulaire Configuration */}
          <div className="bg-white rounded-lg shadow p-6">
            <h2 className="text-xl font-bold mb-4">Configurer un Moyen de Paiement</h2>
            <form onSubmit={handleSaveMethod} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Type <span className="text-red-500">*</span>
                </label>
                <select
                  value={formData.type}
                  onChange={(e) => setFormData({ ...formData, type: e.target.value })}
                  required
                  disabled={loading}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">-- Sélectionner --</option>
                  {paymentTypes.map((type) => (
                    <option key={type.value} value={type.value}>
                      {type.icon} {type.label}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Nom <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  required
                  placeholder="Ex: Kkiapay Prod"
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                />
              </div>

              {getPaymentTypeInfo(formData.type)?.requiresApi && (
                <>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Clé API <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="password"
                      value={formData.api_key}
                      onChange={(e) => setFormData({ ...formData, api_key: e.target.value })}
                      placeholder="Votre clé API"
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Clé Secrète <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="password"
                      value={formData.api_secret}
                      onChange={(e) => setFormData({ ...formData, api_secret: e.target.value })}
                      placeholder="Votre clé secrète"
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                </>
              )}

              <div className="border-t pt-4">
                <label className="flex items-center">
                  <input
                    type="checkbox"
                    checked={formData.is_active}
                    onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                    className="rounded border-gray-300"
                  />
                  <span className="ml-2 text-sm font-medium text-gray-700">
                    Activer ce moyen de paiement
                  </span>
                </label>
              </div>

              <button
                type="submit"
                disabled={loading}
                className="w-full bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700 disabled:bg-gray-400 transition"
              >
                {loading ? 'Configuration...' : 'Configurer'}
              </button>
            </form>
          </div>

          {/* Liste des Moyens Configurés */}
          <div className="bg-white rounded-lg shadow p-6">
            <h2 className="text-xl font-bold mb-4">Moyens de Paiement Disponibles</h2>
            <div className="space-y-3">
              {paymentTypes.map((ptype) => (
                <div
                  key={ptype.value}
                  className="border rounded-lg p-4 flex justify-between items-center hover:bg-gray-50"
                >
                  <div>
                    <h3 className="font-medium text-gray-900">
                      {ptype.icon} {ptype.label}
                    </h3>
                    <p className="text-sm text-gray-600">
                      {isConfigured(ptype.value) ? '✅ Configuré' : '⏱️ Non configuré'}
                    </p>
                  </div>
                  <button
                    onClick={() => {
                      const method = paymentMethods.find((m) => m.type === ptype.value);
                      if (method) {
                        setFormData({
                          type: method.type,
                          name: method.name,
                          is_active: method.is_active,
                          api_key: method.api_key || '',
                          api_secret: method.api_secret || '',
                        });
                      } else {
                        setFormData({
                          type: ptype.value,
                          name: ptype.label,
                          is_active: false,
                          api_key: '',
                          api_secret: '',
                        });
                      }
                    }}
                    className={`px-3 py-1 text-sm rounded font-medium ${
                      isConfigured(ptype.value)
                        ? 'bg-green-50 text-green-600 hover:bg-green-100'
                        : 'bg-blue-50 text-blue-600 hover:bg-blue-100'
                    }`}
                  >
                    {isConfigured(ptype.value) ? 'Éditer' : 'Configurer'}
                  </button>
                </div>
              ))}
            </div>

            {/* Info Box */}
            <div className="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
              <h4 className="font-semibold text-blue-900 mb-2">💡 Info</h4>
              <p className="text-sm text-blue-800">
                Activez les moyens de paiement que vous souhaitez utiliser. Les clients pourront ensuite choisir leur moyen
                de paiement préféré lors du paiement.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
