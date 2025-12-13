import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTenantStore } from '../stores/tenantStore';
import { useLanguageStore } from '../stores/languageStore';
import apiClient from '../services/apiClient';
import { prefetchEssentialData, prefetchSecondaryData } from '../services/prefetch';
import TwoFactorVerify from '../components/TwoFactorVerify';

export default function LoginPage() {
  const [mode, setMode] = useState('login'); // login or register
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [requires2FA, setRequires2FA] = useState(false);
  const [pending2FAUserId, setPending2FAUserId] = useState(null);
  const navigate = useNavigate();
  const { setTenant, setUser, setToken } = useTenantStore();
  const { t } = useLanguageStore();

  const [formData, setFormData] = useState({
    email: '',
    password: '',
    password_confirmation: '',
    tenant_name: '',
    name: '',
    mode_pos: 'A',
    currency: 'XOF',
    country: 'BJ',
  });

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleLogin = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      console.log('[LoginPage] Attempting login with:', formData.email);
      const response = await apiClient.post('/login', {
        email: formData.email,
        password: formData.password,
      });

      console.log('[LoginPage] Login response:', response.data);
      
      // Vérifier si 2FA est requis
      if (response.data?.requires_2fa) {
        console.log('[LoginPage] 2FA required for user:', response.data.user_id);
        setPending2FAUserId(response.data.user_id);
        setRequires2FA(true);
        return;
      }
      
      if (response.data?.token) {
        setToken(response.data.token);
        setUser(response.data.user);
        setTenant(response.data.tenant);
        
        // Précharger les données essentielles en arrière-plan
        prefetchEssentialData().then(() => {
          setTimeout(prefetchSecondaryData, 500);
        });
        
        // Redirection selon le rôle
        const userRole = response.data.user?.role;
        if (userRole === 'supplier') {
          console.log('[LoginPage] Supplier detected, navigating to supplier portal');
          navigate('/supplier-portal');
        } else {
          console.log('[LoginPage] Navigating to home');
          navigate('/home');
        }
      } else {
        setError('No token received from server');
      }
    } catch (err) {
      console.error('[LoginPage] Login error:', err);
      const errorMsg = err.response?.data?.message || err.message || 'Login failed';
      setError(errorMsg);
    } finally {
      setLoading(false);
    }
  };

  const handleRegister = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      const response = await apiClient.post('/register', {
        tenant_name: formData.tenant_name,
        name: formData.name,
        email: formData.email,
        password: formData.password,
        password_confirmation: formData.password_confirmation,
        mode_pos: formData.mode_pos || 'A',
        currency: formData.currency || 'XOF',
        country: formData.country || 'BJ',
      });

      setToken(response.data.token);
      setUser(response.data.user);
      setTenant(response.data.tenant);

      navigate('/dashboard');
    } catch (err) {
      setError(err.response?.data?.message || 'Registration failed');
    } finally {
      setLoading(false);
    }
  };

  // Handler pour succès 2FA
  const handle2FASuccess = (data) => {
    setToken(data.token);
    setUser(data.user);
    setTenant(data.tenant);
    
    prefetchEssentialData().then(() => {
      setTimeout(prefetchSecondaryData, 500);
    });
    
    const userRole = data.user?.role;
    if (userRole === 'supplier') {
      navigate('/supplier-portal');
    } else {
      navigate('/home');
    }
  };

  // Handler pour annulation 2FA
  const handle2FACancel = () => {
    setRequires2FA(false);
    setPending2FAUserId(null);
    setFormData(prev => ({ ...prev, password: '' }));
  };

  // Afficher l'écran de vérification 2FA si nécessaire
  if (requires2FA && pending2FAUserId) {
    return (
      <TwoFactorVerify
        userId={pending2FAUserId}
        onSuccess={handle2FASuccess}
        onCancel={handle2FACancel}
      />
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-orange-500 to-orange-600 flex items-center justify-center p-4">
      <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-xl p-8 w-full max-w-md">
        <div className="flex justify-center mb-4">
          <div className="w-16 h-16 bg-orange-500 rounded-2xl flex items-center justify-center shadow-lg shadow-orange-500/30">
            <span className="text-3xl font-black text-white">S</span>
          </div>
        </div>
        <h1 className="text-2xl font-bold text-gray-800 dark:text-white mb-2 text-center">SIGEC</h1>
        <p className="text-gray-500 dark:text-gray-400 text-center mb-6 text-sm">Gestion Efficace et Comptabilité</p>

        {error && (
          <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
            {error}
          </div>
        )}

        {mode === 'login' ? (
          <form onSubmit={handleLogin}>
            <div className="mb-4">
              <label className="block text-gray-700 dark:text-gray-300 font-semibold mb-2">
                Email <span className="text-red-500 font-bold">*</span>
              </label>
              <input
                type="email"
                name="email"
                value={formData.email}
                onChange={handleChange}
                placeholder="email@example.com"
                className="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                required
              />
            </div>

            <div className="mb-6">
              <label className="block text-gray-700 dark:text-gray-300 font-semibold mb-2">
                {t('auth.password')} <span className="text-red-500 font-bold">*</span>
              </label>
              <input
                type="password"
                name="password"
                value={formData.password}
                onChange={handleChange}
                placeholder="••••••••"
                className="w-full px-4 py-3 border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                required
              />
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full bg-orange-500 text-white py-3 rounded-xl font-medium hover:bg-orange-600 disabled:bg-gray-400 transition"
            >
              {loading ? t('misc.loading') : t('auth.login')}
            </button>

            <p className="text-center text-gray-600 dark:text-gray-400 mt-4 text-sm">
              {t('auth.noAccount')}{' '}
              <button
                type="button"
                onClick={() => setMode('register')}
                className="text-orange-500 hover:underline font-medium"
              >
                {t('auth.register')}
              </button>
            </p>

            {/* Identifiants de test */}
            <div className="mt-6 p-4 bg-gray-50 dark:bg-slate-700 rounded-xl border border-gray-200 dark:border-slate-600">
              <p className="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2 uppercase tracking-wide">
                🔑 Comptes de test
              </p>
              <div className="space-y-1 text-xs">
                <button
                  type="button"
                  onClick={() => setFormData(prev => ({ ...prev, email: 'owner@demo.local', password: 'password' }))}
                  className="w-full text-left px-2 py-1.5 rounded hover:bg-orange-100 dark:hover:bg-slate-600 transition-colors text-gray-600 dark:text-gray-300"
                >
                  <span className="font-medium text-orange-600 dark:text-orange-400">Propriétaire:</span> owner@demo.local
                </button>
                <button
                  type="button"
                  onClick={() => setFormData(prev => ({ ...prev, email: 'manager@demo.local', password: 'password' }))}
                  className="w-full text-left px-2 py-1.5 rounded hover:bg-orange-100 dark:hover:bg-slate-600 transition-colors text-gray-600 dark:text-gray-300"
                >
                  <span className="font-medium text-blue-600 dark:text-blue-400">Gérant:</span> manager@demo.local
                </button>
                <button
                  type="button"
                  onClick={() => setFormData(prev => ({ ...prev, email: 'accountant@demo.local', password: 'password' }))}
                  className="w-full text-left px-2 py-1.5 rounded hover:bg-orange-100 dark:hover:bg-slate-600 transition-colors text-gray-600 dark:text-gray-300"
                >
                  <span className="font-medium text-green-600 dark:text-green-400">Comptable:</span> accountant@demo.local
                </button>
              </div>
              <p className="text-[10px] text-gray-400 dark:text-gray-500 mt-2">Mot de passe: password</p>
            </div>
          </form>
        ) : (
          <form onSubmit={handleRegister}>
            <div className="mb-4">
              <label className="block text-gray-700 font-semibold mb-2">
                Business Name <span className="text-red-500 font-bold">*</span>
              </label>
              <input
                type="text"
                name="tenant_name"
                value={formData.tenant_name}
                onChange={handleChange}
                placeholder="e.g. My Business"
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
              />
            </div>

            <div className="mb-4">
              <label className="block text-gray-700 font-semibold mb-2">
                Mode POS <span className="text-red-500 font-bold">*</span>
              </label>
              <div className="grid grid-cols-2 gap-3">
                <label 
                  className={`flex items-center p-4 border-2 rounded-xl cursor-pointer transition-all ${
                    formData.mode_pos === 'A' 
                      ? 'border-blue-500 bg-blue-50 text-blue-700' 
                      : 'border-gray-200 hover:border-gray-300 bg-white'
                  }`}
                >
                  <input
                    type="radio"
                    name="mode_pos"
                    value="A"
                    checked={formData.mode_pos === 'A'}
                    onChange={handleChange}
                    className="sr-only"
                  />
                  <div>
                    <div className="font-bold text-sm">Mode A</div>
                    <div className="text-xs opacity-75">Détail + POS</div>
                  </div>
                </label>
                <label 
                  className={`flex items-center p-4 border-2 rounded-xl cursor-pointer transition-all ${
                    formData.mode_pos === 'B' 
                      ? 'border-blue-500 bg-blue-50 text-blue-700' 
                      : 'border-gray-200 hover:border-gray-300 bg-white'
                  }`}
                >
                  <input
                    type="radio"
                    name="mode_pos"
                    value="B"
                    checked={formData.mode_pos === 'B'}
                    onChange={handleChange}
                    className="sr-only"
                  />
                  <div>
                    <div className="font-bold text-sm">Mode B</div>
                    <div className="text-xs opacity-75">Gros + Détail + POS</div>
                  </div>
                </label>
              </div>
              <p className="text-xs text-gray-500 mt-2">
                Mode A: Commerce de détail. Mode B: Grossiste avec vente au détail.
              </p>
            </div>

            <div className="mb-4">
              <label className="block text-gray-700 font-semibold mb-2">
                Your Name <span className="text-red-500 font-bold">*</span>
              </label>
              <input
                type="text"
                name="name"
                value={formData.name}
                onChange={handleChange}
                placeholder="Full name"
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
              />
            </div>

            <div className="mb-4">
              <label className="block text-gray-700 font-semibold mb-2">
                Email <span className="text-red-500 font-bold">*</span>
              </label>
              <input
                type="email"
                name="email"
                value={formData.email}
                onChange={handleChange}
                placeholder="email@example.com"
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
              />
            </div>

            <div className="mb-4">
              <label className="block text-gray-700 font-semibold mb-2">
                Password <span className="text-red-500 font-bold">*</span>
              </label>
              <input
                type="password"
                name="password"
                value={formData.password}
                onChange={handleChange}
                placeholder="Min. 8 characters"
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
                minLength="8"
              />
            </div>

            <div className="mb-6">
              <label className="block text-gray-700 font-semibold mb-2">
                Confirm Password <span className="text-red-500 font-bold">*</span>
              </label>
              <input
                type="password"
                name="password_confirmation"
                value={formData.password_confirmation}
                onChange={handleChange}
                placeholder="Re-enter password"
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
              />
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700 disabled:bg-gray-400 transition"
            >
              {loading ? 'Creating account...' : 'Register'}
            </button>

            <p className="text-center text-gray-600 mt-4 text-sm">
              Already have an account?{' '}
              <button
                type="button"
                onClick={() => setMode('login')}
                className="text-blue-600 hover:underline font-medium"
              >
                Login
              </button>
            </p>
          </form>
        )}
      </div>
    </div>
  );
}
