import React, { useState, useEffect } from 'react';
import apiClient from '../services/apiClient';

export default function ChartOfAccountsSetupPage() {
  const [businessTypes, setBusinessTypes] = useState({});
  const [selectedType, setSelectedType] = useState('retail');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);
  const [chartInitialized, setChartInitialized] = useState(false);
  const [summary, setSummary] = useState(null);

  useEffect(() => {
    fetchBusinessTypes();
    checkChartStatus();
  }, []);

  const fetchBusinessTypes = async () => {
    try {
      const response = await apiClient.get('/chart-of-accounts/business-types');
      setBusinessTypes(response.data);
    } catch (err) {
      console.error('Erreur:', err);
    }
  };

  const checkChartStatus = async () => {
    try {
      const response = await apiClient.get('/chart-of-accounts/summary');
      setSummary(response.data);
      setChartInitialized(response.data.total_accounts > 0);
    } catch (err) {
      setChartInitialized(false);
    }
  };

  const handleInitialize = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await apiClient.post('/chart-of-accounts/initialize', {
        business_type: selectedType,
      });

      setSuccess(true);
      setSummary(response.data);
      setChartInitialized(true);

      setTimeout(() => {
        setSuccess(false);
      }, 3000);
    } catch (err) {
      setError(err.response?.data?.error || 'Erreur lors de l\'initialisation');
    } finally {
      setLoading(false);
    }
  };

  if (chartInitialized && summary) {
    return (
      <div className="space-y-6">
        <div className="bg-green-50 border border-green-200 rounded-lg p-6">
          <h2 className="text-2xl font-bold text-green-800 mb-4">
            ✅ Plan Comptable Initialisé
          </h2>
          <p className="text-green-700 mb-4">
            Votre plan comptable a été créé automatiquement selon votre type de business.
          </p>

          <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div className="bg-white p-4 rounded border">
              <p className="text-sm text-gray-600">Total Comptes</p>
              <p className="text-2xl font-bold">{summary.total_accounts}</p>
            </div>
            <div className="bg-white p-4 rounded border">
              <p className="text-sm text-gray-600">Actifs</p>
              <p className="text-2xl font-bold">{summary.by_type.assets}</p>
            </div>
            <div className="bg-white p-4 rounded border">
              <p className="text-sm text-gray-600">Passifs</p>
              <p className="text-2xl font-bold">{summary.by_type.liabilities}</p>
            </div>
            <div className="bg-white p-4 rounded border">
              <p className="text-sm text-gray-600">Capitaux</p>
              <p className="text-2xl font-bold">{summary.by_type.equity}</p>
            </div>
            <div className="bg-white p-4 rounded border">
              <p className="text-sm text-gray-600">Revenus</p>
              <p className="text-2xl font-bold">{summary.by_type.revenue}</p>
            </div>
            <div className="bg-white p-4 rounded border">
              <p className="text-sm text-gray-600">Dépenses</p>
              <p className="text-2xl font-bold">{summary.by_type.expense}</p>
            </div>
          </div>

          <div className="mt-6 p-4 bg-blue-50 rounded border border-blue-200">
            <h3 className="font-semibold text-blue-900 mb-2">📋 Prochaines Étapes</h3>
            <ul className="text-sm text-blue-800 space-y-1">
              <li>✓ Consultez le plan comptable complet</li>
              <li>✓ Personnalisez les comptes si nécessaire</li>
              <li>✓ Commencez à enregistrer vos transactions</li>
              <li>✓ Le système mappera automatiquement vos ventes/achats</li>
            </ul>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg p-8 text-white">
        <h1 className="text-3xl font-bold mb-2">Configuration du Plan Comptable</h1>
        <p className="text-blue-100">
          Sélectionnez votre type de business pour créer automatiquement votre plan comptable.
          Aucune ressource comptable requise!
        </p>
      </div>

      <div className="bg-white rounded-lg border p-6">
        <h2 className="text-xl font-bold mb-4">Type de Business</h2>
        <p className="text-gray-600 mb-4">
          Choisissez le type qui correspond le mieux à votre activité:
        </p>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          {Object.entries(businessTypes).map(([key, value]) => (
            <label
              key={key}
              className={`p-4 border rounded-lg cursor-pointer transition ${
                selectedType === key
                  ? 'border-blue-500 bg-blue-50'
                  : 'border-gray-300 hover:border-gray-400'
              }`}
            >
              <input
                type="radio"
                name="business_type"
                value={key}
                checked={selectedType === key}
                onChange={(e) => setSelectedType(e.target.value)}
                className="mr-2"
              />
              <span className="font-medium">{value}</span>
            </label>
          ))}
        </div>

        {error && (
          <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded text-red-700">
            {error}
          </div>
        )}

        {success && (
          <div className="mb-4 p-4 bg-green-50 border border-green-200 rounded text-green-700">
            Plan comptable créé avec succès!
          </div>
        )}

        <button
          onClick={handleInitialize}
          disabled={loading}
          className="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:bg-gray-400"
        >
          {loading ? 'Création en cours...' : 'Créer le Plan Comptable'}
        </button>
      </div>

      <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h3 className="font-bold text-blue-900 mb-3">🔧 Qu'est-ce qui sera créé?</h3>
        <ul className="text-sm text-blue-800 space-y-2">
          <li>✓ <strong>25+ comptes pré-configurés</strong> selon vos normes comptables</li>
          <li>✓ <strong>Comptabilité automatique:</strong> vos ventes/achats mappées automatiquement</li>
          <li>✓ <strong>Rapports clés:</strong> bilan, résultat, balance, grand livre</li>
          <li>✓ <strong>Conformité:</strong> codes comptables standardisés pour chaque type de business</li>
          <li>✓ <strong>Audit trail:</strong> chaque transaction enregistrée et traçable</li>
        </ul>
      </div>

      <div className="bg-amber-50 border border-amber-200 rounded-lg p-6">
        <h3 className="font-bold text-amber-900 mb-3">💡 Comment ça marche?</h3>
        <ol className="text-sm text-amber-800 space-y-2 list-decimal list-inside">
          <li>Vous sélectionnez votre type de business</li>
          <li>Le système crée automatiquement 25+ comptes comptables</li>
          <li>Chaque vente/achat est automatiquement comptabilisé</li>
          <li>Vos rapports comptables se génèrent automatiquement</li>
          <li>Vous pouvez personnaliser/ajouter des comptes si nécessaire</li>
        </ol>
      </div>
    </div>
  );
}
