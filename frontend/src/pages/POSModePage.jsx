import { useState, useEffect, memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTenantStore } from '../stores/tenantStore';
import apiClient from '../services/apiClient';
import { Store, Package, ShoppingCart, Check, AlertCircle } from 'lucide-react';

/**
 * Page de configuration du mode POS
 * Option A: POS sans stock (bar/buvette) - Ventes directes
 * Option B: POS avec stock (multisite) - Gestion complète
 */
export default function POSModePage() {
  const navigate = useNavigate();
  const { tenant, setTenant } = useTenantStore();
  const [selectedMode, setSelectedMode] = useState(tenant?.pos_mode || null);
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState('');
  const [error, setError] = useState('');

  const modes = [
    {
      id: 'option_a',
      name: 'Option A - POS Simple',
      subtitle: 'Bar / Buvette / Restauration rapide',
      icon: ShoppingCart,
      color: 'blue',
      features: [
        'Ventes directes sans gestion de stock',
        'Encaissement rapide (Cash, MoMo, Virement)',
        'Historique des ventes',
        'Rapports de caisse',
        'Idéal pour les petits commerces',
      ],
      notIncluded: [
        'Gestion des stocks',
        'Transferts inter-magasins',
        'Demandes de réapprovisionnement',
      ],
    },
    {
      id: 'option_b',
      name: 'Option B - POS Complet',
      subtitle: 'Commerce multisite avec stock',
      icon: Package,
      color: 'green',
      features: [
        'Tout de l\'Option A',
        'Gestion complète des stocks',
        'Magasin Gros + Magasin Détail',
        'Transferts automatiques',
        'Demandes de stock POS',
        'Alertes stock bas',
        'Inventaires périodiques',
      ],
      notIncluded: [],
    },
  ];

  const handleSelectMode = async () => {
    if (!selectedMode) {
      setError('Veuillez sélectionner un mode POS');
      return;
    }

    setLoading(true);
    setError('');

    try {
      const res = await apiClient.put('/tenant-config', { pos_mode: selectedMode });
      setTenant({ ...tenant, pos_mode: selectedMode });
      setSuccess('Mode POS configuré avec succès!');
      
      // Rediriger après 2 secondes
      setTimeout(() => navigate('/dashboard'), 2000);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de la configuration');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <div className="max-w-4xl mx-auto">
        {/* Header */}
        <div className="text-center mb-8">
          <Store size={48} className="mx-auto text-blue-600 mb-4" />
          <h1 className="text-3xl font-bold text-gray-900">Configuration du Mode POS</h1>
          <p className="text-gray-600 mt-2">Choisissez le mode de fonctionnement adapté à votre activité</p>
        </div>

        {/* Alerts */}
        {error && (
          <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
            <AlertCircle size={20} />
            {error}
          </div>
        )}
        {success && (
          <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
            <Check size={20} />
            {success}
          </div>
        )}

        {/* Mode Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
          {modes.map((mode) => (
            <div
              key={mode.id}
              onClick={() => setSelectedMode(mode.id)}
              className={`relative cursor-pointer rounded-2xl border-2 p-6 transition-all ${
                selectedMode === mode.id
                  ? `border-${mode.color}-500 bg-${mode.color}-50 shadow-lg`
                  : 'border-gray-200 bg-white hover:border-gray-300 hover:shadow'
              }`}
            >
              {/* Selected Badge */}
              {selectedMode === mode.id && (
                <div className={`absolute top-4 right-4 bg-${mode.color}-500 text-white p-1 rounded-full`}>
                  <Check size={16} />
                </div>
              )}

              {/* Icon & Title */}
              <div className="flex items-center gap-3 mb-4">
                <div className={`p-3 rounded-xl bg-${mode.color}-100`}>
                  <mode.icon size={28} className={`text-${mode.color}-600`} />
                </div>
                <div>
                  <h3 className="text-xl font-bold text-gray-900">{mode.name}</h3>
                  <p className="text-sm text-gray-500">{mode.subtitle}</p>
                </div>
              </div>

              {/* Features */}
              <div className="space-y-2 mb-4">
                <p className="text-sm font-semibold text-gray-700">✅ Inclus:</p>
                {mode.features.map((feature, idx) => (
                  <div key={idx} className="flex items-center gap-2 text-sm text-gray-600">
                    <Check size={14} className="text-green-500" />
                    {feature}
                  </div>
                ))}
              </div>

              {/* Not Included */}
              {mode.notIncluded.length > 0 && (
                <div className="space-y-2 pt-4 border-t">
                  <p className="text-sm font-semibold text-gray-500">❌ Non inclus:</p>
                  {mode.notIncluded.map((item, idx) => (
                    <div key={idx} className="flex items-center gap-2 text-sm text-gray-400">
                      <span className="w-3.5 h-0.5 bg-gray-300"></span>
                      {item}
                    </div>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>

        {/* Current Mode Info */}
        {tenant?.pos_mode && (
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <p className="text-blue-700">
              <strong>Mode actuel:</strong> {tenant.pos_mode === 'option_a' ? 'Option A - POS Simple' : 'Option B - POS Complet'}
            </p>
          </div>
        )}

        {/* Action Button */}
        <div className="flex justify-center gap-4">
          <button
            onClick={() => navigate('/dashboard')}
            className="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition"
          >
            Annuler
          </button>
          <button
            onClick={handleSelectMode}
            disabled={loading || !selectedMode}
            className={`px-8 py-3 rounded-lg font-bold transition ${
              selectedMode
                ? 'bg-blue-600 hover:bg-blue-700 text-white'
                : 'bg-gray-300 text-gray-500 cursor-not-allowed'
            }`}
          >
            {loading ? 'Configuration...' : 'Confirmer le Mode POS'}
          </button>
        </div>

        {/* Help Text */}
        <p className="text-center text-sm text-gray-500 mt-6">
          💡 Vous pourrez changer de mode ultérieurement dans les paramètres.
        </p>
      </div>
    </div>
  );
}
