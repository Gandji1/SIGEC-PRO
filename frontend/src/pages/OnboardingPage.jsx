import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import apiClient from '../services/apiClient';

export default function OnboardingPage() {
  const navigate = useNavigate();
  const [step, setStep] = useState(1); // 1: Login/Register, 2: Business Info, 3: Owner Info
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  // Step 1: Auth
  const [authData, setAuthData] = useState({
    email: '',
    password: '',
    password_confirmation: '',
  });

  // Step 2: Business
  const [businessData, setBusinessData] = useState({
    tenant_name: '',
    business_type: 'retail',
    currency: 'XOF',
    country: 'BJ',
  });

  // Step 3: Owner
  const [ownerData, setOwnerData] = useState({
    name: '',
    phone: '',
    mode_pos: 'A',
  });

  const businessTypes = [
    { value: 'retail', label: '🏪 Commerce Détail' },
    { value: 'wholesale', label: '📦 Commerce Gros' },
    { value: 'restaurant', label: '🍽️ Restauration' },
    { value: 'pharmacy', label: '💊 Pharmacie' },
    { value: 'manufacturing', label: '🏭 Fabrication' },
    { value: 'service', label: '🛠️ Services' },
    { value: 'other', label: '❓ Autre' },
  ];

  const currencies = [
    { value: 'XOF', label: 'XOF (CFA Ouest Africain)' },
    { value: 'EUR', label: 'EUR (€)' },
    { value: 'USD', label: 'USD ($)' },
  ];

  const countries = [
    { value: 'BJ', label: 'Bénin' },
    { value: 'SN', label: 'Sénégal' },
    { value: 'CI', label: "Côte d'Ivoire" },
    { value: 'TG', label: 'Togo' },
    { value: 'ML', label: 'Mali' },
    { value: 'BF', label: 'Burkina Faso' },
  ];

  // Step 1: Register
  const handleRegisterStep = async (e) => {
    e.preventDefault();
    setError('');

    if (authData.password !== authData.password_confirmation) {
      setError('Les mots de passe ne correspondent pas');
      return;
    }

    if (authData.password.length < 8) {
      setError('Le mot de passe doit contenir au moins 8 caractères');
      return;
    }

    setLoading(true);
    try {
      // Just validate, move to step 2
      setSuccess('Email validé ✅');
      setStep(2);
      setTimeout(() => setSuccess(''), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur d\'enregistrement');
    } finally {
      setLoading(false);
    }
  };

  // Step 2: Business Info
  const handleBusinessStep = async (e) => {
    e.preventDefault();
    setError('');

    if (!businessData.tenant_name.trim()) {
      setError('Le nom de l\'entreprise est requis');
      return;
    }

    setSuccess('Informations entreprise validées ✅');
    setStep(3);
    setTimeout(() => setSuccess(''), 3000);
  };

  // Step 3: Final Registration
  const handleFinalRegistration = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const response = await apiClient.post('/register', {
        email: authData.email,
        password: authData.password,
        password_confirmation: authData.password_confirmation,
        tenant_name: businessData.tenant_name,
        business_type: businessData.business_type,
        currency: businessData.currency,
        country: businessData.country,
        name: ownerData.name,
        phone: ownerData.phone,
        mode_pos: ownerData.mode_pos,
      });

      if (response.data?.success) {
        setSuccess('✅ Inscription réussie! Connexion en cours...');
        setTimeout(() => {
          navigate('/login', {
            state: { message: 'Votre compte a été créé. Veuillez vous connecter.' }
          });
        }, 2000);
      } else {
        setError(response.data?.message || 'Erreur d\'enregistrement');
      }
    } catch (err) {
      setError(err.response?.data?.message || err.message || 'Erreur d\'enregistrement');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center p-4">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-md">
        {/* Header */}
        <div className="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-6 text-white rounded-t-lg">
          <h1 className="text-3xl font-bold">SIGEC</h1>
          <p className="text-blue-100 text-sm mt-1">
            {step === 1 && '📧 Étape 1: Inscription'}
            {step === 2 && '🏢 Étape 2: Informations Entreprise'}
            {step === 3 && '👤 Étape 3: Informations Propriétaire'}
          </p>
        </div>

        {/* Content */}
        <div className="p-6">
          {error && (
            <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
              {error}
            </div>
          )}
          {success && (
            <div className="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
              {success}
            </div>
          )}

          {/* Step 1: Registration */}
          {step === 1 && (
            <form onSubmit={handleRegisterStep} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input
                  type="email"
                  value={authData.email}
                  onChange={(e) => setAuthData({ ...authData, email: e.target.value })}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                <input
                  type="password"
                  value={authData.password}
                  onChange={(e) => setAuthData({ ...authData, password: e.target.value })}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500"
                  placeholder="Min. 8 caractères"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Confirmer mot de passe</label>
                <input
                  type="password"
                  value={authData.password_confirmation}
                  onChange={(e) => setAuthData({ ...authData, password_confirmation: e.target.value })}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500"
                  required
                />
              </div>

              <button
                type="submit"
                disabled={loading}
                className="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white py-2 rounded-lg font-medium transition"
              >
                {loading ? 'Chargement...' : 'Continuer →'}
              </button>

              <p className="text-center text-sm text-gray-600 mt-4">
                Déjà inscrit? <a href="/login" className="text-blue-600 hover:underline font-medium">Connectez-vous</a>
              </p>
            </form>
          )}

          {/* Step 2: Business Info */}
          {step === 2 && (
            <form onSubmit={handleBusinessStep} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Nom de l'entreprise *</label>
                <input
                  type="text"
                  value={businessData.tenant_name}
                  onChange={(e) => setBusinessData({ ...businessData, tenant_name: e.target.value })}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Type d'activité *</label>
                <select
                  value={businessData.business_type}
                  onChange={(e) => setBusinessData({ ...businessData, business_type: e.target.value })}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500"
                >
                  {businessTypes.map(t => (
                    <option key={t.value} value={t.value}>{t.label}</option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Devise</label>
                  <select
                    value={businessData.currency}
                    onChange={(e) => setBusinessData({ ...businessData, currency: e.target.value })}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500"
                  >
                    {currencies.map(c => (
                      <option key={c.value} value={c.value}>{c.label}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Pays</label>
                  <select
                    value={businessData.country}
                    onChange={(e) => setBusinessData({ ...businessData, country: e.target.value })}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500"
                  >
                    {countries.map(c => (
                      <option key={c.value} value={c.value}>{c.label}</option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="flex gap-3 pt-4">
                <button
                  type="button"
                  onClick={() => setStep(1)}
                  className="flex-1 border border-gray-300 text-gray-700 hover:bg-gray-50 py-2 rounded-lg font-medium transition"
                >
                  ← Retour
                </button>
                <button
                  type="submit"
                  className="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg font-medium transition"
                >
                  Continuer →
                </button>
              </div>
            </form>
          )}

          {/* Step 3: Owner Info */}
          {step === 3 && (
            <form onSubmit={handleFinalRegistration} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Votre nom *</label>
                <input
                  type="text"
                  value={ownerData.name}
                  onChange={(e) => setOwnerData({ ...ownerData, name: e.target.value })}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                <input
                  type="tel"
                  value={ownerData.phone}
                  onChange={(e) => setOwnerData({ ...ownerData, phone: e.target.value })}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500"
                />
              </div>

              {/* Mode POS */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Mode POS *</label>
                <div className="grid grid-cols-2 gap-3">
                  <label 
                    className={`flex flex-col items-center p-4 border-2 rounded-xl cursor-pointer transition-all ${
                      ownerData.mode_pos === 'A' 
                        ? 'border-blue-500 bg-blue-50 text-blue-700' 
                        : 'border-gray-200 hover:border-gray-300 bg-white'
                    }`}
                  >
                    <input
                      type="radio"
                      name="mode_pos"
                      value="A"
                      checked={ownerData.mode_pos === 'A'}
                      onChange={(e) => setOwnerData({ ...ownerData, mode_pos: e.target.value })}
                      className="sr-only"
                    />
                    <span className="text-2xl mb-1">🏪</span>
                    <span className="font-bold text-sm">Mode A</span>
                    <span className="text-xs opacity-75 text-center">Détail + POS</span>
                  </label>
                  <label 
                    className={`flex flex-col items-center p-4 border-2 rounded-xl cursor-pointer transition-all ${
                      ownerData.mode_pos === 'B' 
                        ? 'border-blue-500 bg-blue-50 text-blue-700' 
                        : 'border-gray-200 hover:border-gray-300 bg-white'
                    }`}
                  >
                    <input
                      type="radio"
                      name="mode_pos"
                      value="B"
                      checked={ownerData.mode_pos === 'B'}
                      onChange={(e) => setOwnerData({ ...ownerData, mode_pos: e.target.value })}
                      className="sr-only"
                    />
                    <span className="text-2xl mb-1">📦</span>
                    <span className="font-bold text-sm">Mode B</span>
                    <span className="text-xs opacity-75 text-center">Gros + Détail + POS</span>
                  </label>
                </div>
                <p className="text-xs text-gray-500 mt-2">
                  Mode A: Commerce de détail uniquement. Mode B: Grossiste avec vente au détail.
                </p>
              </div>

              <div className="bg-blue-50 border border-blue-200 p-3 rounded-lg text-sm text-blue-700">
                ℹ️ Votre entreprise sera en mode <strong>Validation en attente</strong>. 
                Un administrateur SIGEC la validera bientôt.
              </div>

              <div className="flex gap-3 pt-4">
                <button
                  type="button"
                  onClick={() => setStep(2)}
                  className="flex-1 border border-gray-300 text-gray-700 hover:bg-gray-50 py-2 rounded-lg font-medium transition"
                >
                  ← Retour
                </button>
                <button
                  type="submit"
                  disabled={loading}
                  className="flex-1 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white py-2 rounded-lg font-medium transition"
                >
                  {loading ? '⏳ Création...' : '✅ Créer Entreprise'}
                </button>
              </div>
            </form>
          )}
        </div>
      </div>
    </div>
  );
}
