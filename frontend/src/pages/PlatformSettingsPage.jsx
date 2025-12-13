import { useState, useEffect } from 'react';
import apiClient from '../services/apiClient';
import { Settings, Mail, MessageSquare, CreditCard, Shield, Database, Save, RefreshCw, Package } from 'lucide-react';

export default function PlatformSettingsPage() {
  const [settings, setSettings] = useState({});
  const [modules, setModules] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [activeTab, setActiveTab] = useState('general');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    setLoading(true);
    try {
      const [settingsRes, modulesRes] = await Promise.all([
        apiClient.get('/superadmin/settings').catch(() => ({ data: { data: {} } })),
        apiClient.get('/superadmin/modules').catch(() => ({ data: { data: [] } })),
      ]);
      
      // Transformer les settings groupés en objet plat
      const grouped = settingsRes.data?.data || {};
      const flat = {};
      Object.entries(grouped).forEach(([group, items]) => {
        Object.entries(items).forEach(([key, data]) => {
          flat[key] = data.value;
        });
      });
      setSettings(flat);
      setModules(modulesRes.data?.data || []);
    } catch (err) {
      setError('Erreur de chargement');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    setSaving(true);
    setError('');
    try {
      await apiClient.put('/superadmin/settings', { settings });
      setSuccess('Paramètres sauvegardés');
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur de sauvegarde');
    } finally {
      setSaving(false);
    }
  };

  const handleInitDefaults = async () => {
    try {
      await apiClient.post('/superadmin/settings/init-defaults');
      await apiClient.post('/superadmin/modules/init');
      setSuccess('Paramètres par défaut initialisés');
      fetchData();
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError('Erreur');
    }
  };

  const updateSetting = (key, value) => {
    setSettings(prev => ({ ...prev, [key]: value }));
  };

  const tabs = [
    { id: 'general', label: 'Général', icon: Settings },
    { id: 'email', label: 'Email', icon: Mail },
    { id: 'sms', label: 'SMS', icon: MessageSquare },
    { id: 'payment', label: 'Paiement', icon: CreditCard },
    { id: 'security', label: 'Sécurité', icon: Shield },
    { id: 'modules', label: 'Modules', icon: Package },
  ];

  const renderInput = (key, label, type = 'text', options = null) => (
    <div key={key}>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      {type === 'select' ? (
        <select
          value={settings[key] || ''}
          onChange={e => updateSetting(key, e.target.value)}
          className="w-full border border-gray-300 rounded-lg px-3 py-2"
        >
          {options.map(opt => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
        </select>
      ) : type === 'boolean' ? (
        <label className="flex items-center gap-2 cursor-pointer">
          <input
            type="checkbox"
            checked={settings[key] === true || settings[key] === 'true'}
            onChange={e => updateSetting(key, e.target.checked)}
            className="w-4 h-4"
          />
          <span className="text-gray-600">Activé</span>
        </label>
      ) : (
        <input
          type={type}
          value={settings[key] || ''}
          onChange={e => updateSetting(key, e.target.value)}
          className="w-full border border-gray-300 rounded-lg px-3 py-2"
        />
      )}
    </div>
  );

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">⚙️ Paramètres Plateforme</h1>
          <p className="text-gray-600 mt-1">Configuration globale de SIGEC</p>
        </div>
        <div className="flex gap-3">
          <button 
            onClick={handleInitDefaults}
            className="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg"
          >
            <RefreshCw size={18} />
            Initialiser Défauts
          </button>
          <button 
            onClick={handleSave}
            disabled={saving}
            className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg disabled:opacity-50"
          >
            <Save size={18} className={saving ? 'animate-spin' : ''} />
            {saving ? 'Sauvegarde...' : 'Sauvegarder'}
          </button>
        </div>
      </div>

      {/* Alerts */}
      {error && <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{error}</div>}
      {success && <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{success}</div>}

      {/* Tabs */}
      <div className="flex gap-2 border-b overflow-x-auto">
        {tabs.map(tab => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={`flex items-center gap-2 px-4 py-3 font-medium whitespace-nowrap transition ${
              activeTab === tab.id 
                ? 'text-blue-600 border-b-2 border-blue-600' 
                : 'text-gray-500 hover:text-gray-700'
            }`}
          >
            <tab.icon size={18} />
            {tab.label}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="animate-pulse space-y-4">
          {[1,2,3,4].map(i => <div key={i} className="h-12 bg-gray-100 rounded"></div>)}
        </div>
      ) : (
        <div className="bg-white rounded-xl shadow-sm p-6">
          {/* General */}
          {activeTab === 'general' && (
            <div className="space-y-4">
              <h3 className="text-lg font-bold text-gray-900 mb-4">Paramètres Généraux</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {renderInput('platform_name', 'Nom de la plateforme')}
                {renderInput('platform_email', 'Email support', 'email')}
                {renderInput('platform_phone', 'Téléphone support')}
                {renderInput('default_currency', 'Devise par défaut', 'select', [
                  { value: 'XOF', label: 'XOF (CFA BCEAO)' },
                  { value: 'XAF', label: 'XAF (CFA BEAC)' },
                  { value: 'EUR', label: 'EUR (Euro)' },
                  { value: 'USD', label: 'USD (Dollar)' },
                ])}
                {renderInput('default_tva_rate', 'Taux TVA par défaut (%)', 'number')}
                {renderInput('default_timezone', 'Fuseau horaire', 'select', [
                  { value: 'Africa/Porto-Novo', label: 'Porto-Novo (UTC+1)' },
                  { value: 'Africa/Dakar', label: 'Dakar (UTC+0)' },
                  { value: 'Africa/Abidjan', label: 'Abidjan (UTC+0)' },
                  { value: 'Africa/Douala', label: 'Douala (UTC+1)' },
                ])}
              </div>
            </div>
          )}

          {/* Email */}
          {activeTab === 'email' && (
            <div className="space-y-4">
              <h3 className="text-lg font-bold text-gray-900 mb-4">Configuration Email (SMTP)</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {renderInput('smtp_host', 'Serveur SMTP')}
                {renderInput('smtp_port', 'Port SMTP', 'number')}
                {renderInput('smtp_username', 'Utilisateur SMTP')}
                {renderInput('smtp_password', 'Mot de passe SMTP', 'password')}
                {renderInput('mail_from_address', 'Adresse expéditeur', 'email')}
                {renderInput('mail_from_name', 'Nom expéditeur')}
              </div>
            </div>
          )}

          {/* SMS */}
          {activeTab === 'sms' && (
            <div className="space-y-4">
              <h3 className="text-lg font-bold text-gray-900 mb-4">Configuration SMS</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {renderInput('sms_provider', 'Fournisseur SMS', 'select', [
                  { value: '', label: 'Aucun' },
                  { value: 'twilio', label: 'Twilio' },
                  { value: 'nexmo', label: 'Nexmo/Vonage' },
                  { value: 'africas_talking', label: "Africa's Talking" },
                ])}
                {renderInput('sms_api_key', 'Clé API SMS')}
                {renderInput('sms_sender_id', 'ID Expéditeur SMS')}
              </div>
            </div>
          )}

          {/* Payment */}
          {activeTab === 'payment' && (
            <div className="space-y-4">
              <h3 className="text-lg font-bold text-gray-900 mb-4">Configuration Paiements</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {renderInput('momo_enabled', 'Mobile Money activé', 'boolean')}
                {renderInput('momo_api_key', 'Clé API MoMo')}
                {renderInput('stripe_enabled', 'Stripe activé', 'boolean')}
                {renderInput('stripe_api_key', 'Clé API Stripe')}
              </div>
            </div>
          )}

          {/* Security */}
          {activeTab === 'security' && (
            <div className="space-y-4">
              <h3 className="text-lg font-bold text-gray-900 mb-4">Sécurité</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {renderInput('password_min_length', 'Longueur min mot de passe', 'number')}
                {renderInput('session_lifetime_minutes', 'Durée session (minutes)', 'number')}
                {renderInput('max_login_attempts', 'Tentatives connexion max', 'number')}
                {renderInput('lockout_duration_minutes', 'Durée blocage (minutes)', 'number')}
              </div>
              <div className="mt-6 pt-6 border-t">
                <h4 className="font-semibold text-gray-800 mb-4">Backup</h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {renderInput('backup_enabled', 'Backup automatique', 'boolean')}
                  {renderInput('backup_frequency', 'Fréquence', 'select', [
                    { value: 'hourly', label: 'Toutes les heures' },
                    { value: 'daily', label: 'Quotidien' },
                    { value: 'weekly', label: 'Hebdomadaire' },
                  ])}
                  {renderInput('backup_retention_days', 'Rétention (jours)', 'number')}
                </div>
              </div>
            </div>
          )}

          {/* Modules */}
          {activeTab === 'modules' && (
            <div className="space-y-4">
              <h3 className="text-lg font-bold text-gray-900 mb-4">Modules Disponibles</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {modules.map(module => (
                  <div key={module.id} className={`p-4 rounded-lg border-2 ${module.is_active ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50'}`}>
                    <div className="flex items-center justify-between mb-2">
                      <span className="font-semibold text-gray-800">{module.name}</span>
                      {module.is_core && <span className="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded">Core</span>}
                    </div>
                    <p className="text-sm text-gray-600 mb-2">{module.description}</p>
                    <div className="flex justify-between items-center text-sm">
                      <span className="text-gray-500">Code: {module.code}</span>
                      {module.extra_price > 0 && (
                        <span className="text-green-600 font-medium">+{module.extra_price.toLocaleString()} XOF</span>
                      )}
                    </div>
                  </div>
                ))}
              </div>
              {modules.length === 0 && (
                <p className="text-center text-gray-500 py-8">Aucun module. Cliquez sur "Initialiser Défauts" pour créer les modules de base.</p>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
