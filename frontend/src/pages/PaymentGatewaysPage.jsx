import { useState, useEffect } from 'react';
import apiClient from '../services/apiClient';
import { CreditCard, Key, Save, Eye, EyeOff, CheckCircle, AlertCircle, RefreshCw } from 'lucide-react';

/**
 * Page de configuration des passerelles de paiement pour le SuperAdmin
 * Permet de configurer les clés API pour recevoir les paiements d'abonnement
 */
export default function PaymentGatewaysPage() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState('');
  const [error, setError] = useState('');
  const [showSecrets, setShowSecrets] = useState({});

  const [settings, setSettings] = useState({
    payment_environment: 'sandbox',
    // Fedapay
    fedapay_public_key: '',
    fedapay_secret_key: '',
    // Kkiapay
    kkiapay_public_key: '',
    kkiapay_private_key: '',
    kkiapay_secret: '',
    // MTN MoMo
    momo_subscription_key: '',
    momo_api_user: '',
    momo_api_key: '',
    // PayPal
    paypal_client_id: '',
    paypal_secret: '',
    // Virement
    bank_name: '',
    bank_iban: '',
    bank_bic: '',
  });

  useEffect(() => {
    fetchSettings();
  }, []);

  const fetchSettings = async () => {
    try {
      setLoading(true);
      const res = await apiClient.get('/super-admin/settings?group=payment_gateways');
      const data = res.data?.data || {};
      setSettings(prev => ({ ...prev, ...data }));
    } catch (err) {
      console.error('Error fetching settings:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    try {
      setSaving(true);
      setError('');
      
      await apiClient.put('/super-admin/settings', {
        group: 'payment_gateways',
        settings: settings,
      });
      
      setSuccess('Configuration sauvegardée avec succès!');
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la sauvegarde');
    } finally {
      setSaving(false);
    }
  };

  const toggleShowSecret = (key) => {
    setShowSecrets(prev => ({ ...prev, [key]: !prev[key] }));
  };

  const gateways = [
    {
      id: 'fedapay',
      name: 'FedaPay',
      icon: '💳',
      description: 'Paiements mobile money et cartes en Afrique de l\'Ouest',
      fields: [
        { key: 'fedapay_public_key', label: 'Clé Publique', secret: false },
        { key: 'fedapay_secret_key', label: 'Clé Secrète', secret: true },
      ],
    },
    {
      id: 'kkiapay',
      name: 'Kkiapay',
      icon: '📱',
      description: 'Solution de paiement mobile pour l\'Afrique',
      fields: [
        { key: 'kkiapay_public_key', label: 'Clé Publique', secret: false },
        { key: 'kkiapay_private_key', label: 'Clé Privée', secret: true },
        { key: 'kkiapay_secret', label: 'Secret', secret: true },
      ],
    },
    {
      id: 'momo',
      name: 'MTN MoMo',
      icon: '📲',
      description: 'MTN Mobile Money API',
      fields: [
        { key: 'momo_subscription_key', label: 'Subscription Key', secret: true },
        { key: 'momo_api_user', label: 'API User', secret: false },
        { key: 'momo_api_key', label: 'API Key', secret: true },
      ],
    },
    {
      id: 'paypal',
      name: 'PayPal',
      icon: '🅿️',
      description: 'Paiements internationaux',
      fields: [
        { key: 'paypal_client_id', label: 'Client ID', secret: false },
        { key: 'paypal_secret', label: 'Secret', secret: true },
      ],
    },
    {
      id: 'bank',
      name: 'Virement Bancaire',
      icon: '🏦',
      description: 'Coordonnées bancaires pour virements',
      fields: [
        { key: 'bank_name', label: 'Nom de la Banque', secret: false },
        { key: 'bank_iban', label: 'IBAN', secret: false },
        { key: 'bank_bic', label: 'BIC/SWIFT', secret: false },
      ],
    },
  ];

  if (loading) {
    return (
      <div className="p-6 flex items-center justify-center min-h-[400px]">
        <RefreshCw className="animate-spin text-blue-600" size={32} />
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6 max-w-4xl mx-auto">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">💳 Passerelles de Paiement</h1>
          <p className="text-gray-600 mt-1">Configuration des moyens de paiement pour les abonnements</p>
        </div>
        <button
          onClick={handleSave}
          disabled={saving}
          className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg disabled:opacity-50"
        >
          <Save size={18} />
          {saving ? 'Sauvegarde...' : 'Sauvegarder'}
        </button>
      </div>

      {/* Messages */}
      {success && (
        <div className="flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
          <CheckCircle size={20} />
          {success}
        </div>
      )}
      {error && (
        <div className="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
          <AlertCircle size={20} />
          {error}
        </div>
      )}

      {/* Environment Toggle */}
      <div className="bg-white rounded-xl shadow-sm p-6">
        <h2 className="font-bold text-lg mb-4">Environnement</h2>
        <div className="flex gap-4">
          <label className={`flex-1 p-4 border-2 rounded-lg cursor-pointer transition ${
            settings.payment_environment === 'sandbox' ? 'border-yellow-500 bg-yellow-50' : 'border-gray-200'
          }`}>
            <input
              type="radio"
              name="environment"
              value="sandbox"
              checked={settings.payment_environment === 'sandbox'}
              onChange={(e) => setSettings({ ...settings, payment_environment: e.target.value })}
              className="sr-only"
            />
            <div className="text-center">
              <span className="text-2xl">🧪</span>
              <p className="font-medium mt-2">Sandbox (Test)</p>
              <p className="text-sm text-gray-500">Pour les tests de développement</p>
            </div>
          </label>
          <label className={`flex-1 p-4 border-2 rounded-lg cursor-pointer transition ${
            settings.payment_environment === 'production' ? 'border-green-500 bg-green-50' : 'border-gray-200'
          }`}>
            <input
              type="radio"
              name="environment"
              value="production"
              checked={settings.payment_environment === 'production'}
              onChange={(e) => setSettings({ ...settings, payment_environment: e.target.value })}
              className="sr-only"
            />
            <div className="text-center">
              <span className="text-2xl">🚀</span>
              <p className="font-medium mt-2">Production</p>
              <p className="text-sm text-gray-500">Paiements réels</p>
            </div>
          </label>
        </div>
      </div>

      {/* Gateway Cards */}
      {gateways.map((gateway) => (
        <div key={gateway.id} className="bg-white rounded-xl shadow-sm overflow-hidden">
          <div className="p-4 border-b bg-gray-50 flex items-center gap-3">
            <span className="text-2xl">{gateway.icon}</span>
            <div>
              <h3 className="font-bold text-gray-900">{gateway.name}</h3>
              <p className="text-sm text-gray-500">{gateway.description}</p>
            </div>
            {gateway.fields.some(f => settings[f.key]) && (
              <span className="ml-auto px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded">
                Configuré
              </span>
            )}
          </div>
          <div className="p-4 space-y-4">
            {gateway.fields.map((field) => (
              <div key={field.key}>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  {field.label}
                </label>
                <div className="relative">
                  <input
                    type={field.secret && !showSecrets[field.key] ? 'password' : 'text'}
                    value={settings[field.key] || ''}
                    onChange={(e) => setSettings({ ...settings, [field.key]: e.target.value })}
                    placeholder={field.secret ? '••••••••••••••••' : `Entrez ${field.label.toLowerCase()}`}
                    className="w-full border rounded-lg px-3 py-2 pr-10"
                  />
                  {field.secret && (
                    <button
                      type="button"
                      onClick={() => toggleShowSecret(field.key)}
                      className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    >
                      {showSecrets[field.key] ? <EyeOff size={18} /> : <Eye size={18} />}
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      ))}

      {/* Info Box */}
      <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
        <h4 className="font-semibold text-blue-900 mb-2 flex items-center gap-2">
          <Key size={18} />
          Important
        </h4>
        <ul className="text-sm text-blue-800 space-y-1">
          <li>• Ces clés sont utilisées uniquement pour recevoir les paiements d'abonnement des tenants.</li>
          <li>• Chaque tenant configure ses propres clés pour recevoir les paiements de ses clients.</li>
          <li>• En mode Sandbox, utilisez les clés de test fournies par chaque passerelle.</li>
          <li>• Ne partagez jamais vos clés secrètes.</li>
        </ul>
      </div>
    </div>
  );
}
