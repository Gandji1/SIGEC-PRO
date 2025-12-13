import React, { useState, useEffect } from 'react';
import { useTenantStore } from '../stores/tenantStore';
import apiClient from '../services/apiClient';
import TwoFactorSetup from '../components/TwoFactorSetup';

export default function SettingsPage() {
  const { user, tenant } = useTenantStore();
  const [settings, setSettings] = useState({
    company_name: tenant?.name || '',
    email: tenant?.email || '',
    phone: tenant?.phone || '',
    address: '',
    currency: tenant?.currency || 'XOF',
    timezone: 'Africa/Porto-Novo',
    language: 'fr',
    auto_invoice: true,
    stock_movement_log: true,
  });
  const [pspSettings, setPspSettings] = useState({
    fedapay_enabled: false,
    fedapay_api_key: '',
    fedapay_environment: 'sandbox',
    kakiapay_enabled: false,
    kakiapay_api_key: '',
    kakiapay_environment: 'sandbox',
  });
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState('');
  const [error, setError] = useState('');

  const handleSettingChange = (key, value) => {
    setSettings({ ...settings, [key]: value });
  };

  const handlePSPChange = (key, value) => {
    setPspSettings({ ...pspSettings, [key]: value });
  };

  const handleSaveSettings = async () => {
    setSaving(true);
    setError('');

    try {
      // Call backend
      await apiClient.put('/tenant/settings', settings);
      setSuccess('Paramètres sauvegardés!');
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError('Erreur lors de la sauvegarde');
    } finally {
      setSaving(false);
    }
  };

  const handleSavePSP = async () => {
    setSaving(true);
    setError('');

    try {
      await apiClient.put('/tenant/psp-settings', pspSettings);
      setSuccess('Paramètres PSP sauvegardés!');
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError('Erreur lors de la sauvegarde PSP');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="min-h-screen p-6">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-800">⚙️ Paramètres</h1>
        <p className="text-gray-600">Configurez les paramètres du tenant</p>
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

      <div className="space-y-6">
        {/* General Settings */}
        <div className="bg-white rounded-lg shadow p-6">
          <h2 className="text-xl font-bold text-gray-800 mb-4">🏢 Paramètres Généraux</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Nom de l'Entreprise
              </label>
              <input
                type="text"
                value={settings.company_name}
                onChange={(e) => handleSettingChange('company_name', e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Email
              </label>
              <input
                type="email"
                value={settings.email}
                onChange={(e) => handleSettingChange('email', e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Téléphone
              </label>
              <input
                type="tel"
                value={settings.phone}
                onChange={(e) => handleSettingChange('phone', e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Adresse
              </label>
              <input
                type="text"
                value={settings.address}
                onChange={(e) => handleSettingChange('address', e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Devise
              </label>
              <select
                value={settings.currency}
                onChange={(e) => handleSettingChange('currency', e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2"
              >
                <option value="XOF">XOF (CFA Franc)</option>
                <option value="EUR">EUR (€)</option>
                <option value="USD">USD ($)</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Fuseau horaire
              </label>
              <select
                value={settings.timezone}
                onChange={(e) => handleSettingChange('timezone', e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2"
              >
                <option value="Africa/Porto-Novo">Porto-Novo (UTC+1)</option>
                <option value="Africa/Dakar">Dakar (UTC+0)</option>
                <option value="Africa/Abidjan">Abidjan (UTC+0)</option>
              </select>
            </div>
          </div>

          <div className="mt-6 pt-6 border-t space-y-3">
            <label className="flex items-center gap-3 cursor-pointer">
              <input
                type="checkbox"
                checked={settings.auto_invoice}
                onChange={(e) => handleSettingChange('auto_invoice', e.target.checked)}
                className="w-4 h-4"
              />
              <span className="text-gray-700">Génération automatique des factures</span>
            </label>

            <label className="flex items-center gap-3 cursor-pointer">
              <input
                type="checkbox"
                checked={settings.stock_movement_log}
                onChange={(e) => handleSettingChange('stock_movement_log', e.target.checked)}
                className="w-4 h-4"
              />
              <span className="text-gray-700">Enregistrer tous les mouvements de stock</span>
            </label>
          </div>

          <button
            onClick={handleSaveSettings}
            disabled={saving}
            className="mt-6 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition disabled:opacity-50"
          >
            {saving ? 'Sauvegarde...' : 'Enregistrer les modifications'}
          </button>
        </div>

        {/* PSP Settings (only for Owner/Admin) */}
        {(user?.role === 'owner' || user?.role === 'super_admin') && (
          <div className="bg-white rounded-lg shadow p-6">
            <h2 className="text-xl font-bold text-gray-800 mb-4">💳 Paiements (PSP)</h2>
            <p className="text-gray-600 mb-6 text-sm">
              Configurez les clés API de vos fournisseurs de paiement
            </p>

            {/* Fedapay */}
            <div className="mb-8 pb-8 border-b">
              <div className="flex items-center gap-3 mb-4">
                <input
                  type="checkbox"
                  checked={pspSettings.fedapay_enabled}
                  onChange={(e) => handlePSPChange('fedapay_enabled', e.target.checked)}
                  className="w-4 h-4"
                />
                <span className="font-semibold text-gray-800">Fedapay</span>
              </div>

              {pspSettings.fedapay_enabled && (
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Clé API Fedapay
                    </label>
                    <input
                      type="password"
                      value={pspSettings.fedapay_api_key}
                      onChange={(e) => handlePSPChange('fedapay_api_key', e.target.value)}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2"
                      placeholder="sk_live_xxxx..."
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Environnement
                    </label>
                    <select
                      value={pspSettings.fedapay_environment}
                      onChange={(e) => handlePSPChange('fedapay_environment', e.target.value)}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2"
                    >
                      <option value="sandbox">Sandbox (Test)</option>
                      <option value="live">Production</option>
                    </select>
                  </div>
                </div>
              )}
            </div>

            {/* Kakiapay */}
            <div className="mb-8 pb-8">
              <div className="flex items-center gap-3 mb-4">
                <input
                  type="checkbox"
                  checked={pspSettings.kakiapay_enabled}
                  onChange={(e) => handlePSPChange('kakiapay_enabled', e.target.checked)}
                  className="w-4 h-4"
                />
                <span className="font-semibold text-gray-800">Kakiapay</span>
              </div>

              {pspSettings.kakiapay_enabled && (
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Clé API Kakiapay
                    </label>
                    <input
                      type="password"
                      value={pspSettings.kakiapay_api_key}
                      onChange={(e) => handlePSPChange('kakiapay_api_key', e.target.value)}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2"
                      placeholder="pk_live_xxxx..."
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Environnement
                    </label>
                    <select
                      value={pspSettings.kakiapay_environment}
                      onChange={(e) => handlePSPChange('kakiapay_environment', e.target.value)}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2"
                    >
                      <option value="sandbox">Sandbox (Test)</option>
                      <option value="production">Production</option>
                    </select>
                  </div>
                </div>
              )}
            </div>

            <button
              onClick={handleSavePSP}
              disabled={saving}
              className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition disabled:opacity-50"
            >
              {saving ? 'Sauvegarde...' : 'Enregistrer les paramètres PSP'}
            </button>
          </div>
        )}

        {/* Security - 2FA */}
        <TwoFactorSetup />

        {/* Danger Zone */}
        <div className="bg-red-50 border border-red-200 rounded-lg p-6">
          <h2 className="text-xl font-bold text-red-600 mb-4">⚠️ Zone Dangereuse</h2>
          <p className="text-gray-700 mb-4">Ces actions sont irréversibles.</p>
          <button className="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg font-medium transition">
            Supprimer tous les données
          </button>
        </div>
      </div>
    </div>
  );
}
