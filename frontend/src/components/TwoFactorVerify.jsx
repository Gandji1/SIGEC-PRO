import { useState } from 'react';
import { Shield, AlertTriangle, ArrowLeft } from 'lucide-react';
import twoFactorService from '../services/twoFactorService';

/**
 * Composant de vérification 2FA lors de la connexion
 */
export default function TwoFactorVerify({ userId, onSuccess, onCancel }) {
  const [code, setCode] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [useRecovery, setUseRecovery] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!useRecovery && code.length !== 6) {
      setError('Le code doit contenir 6 chiffres');
      return;
    }

    if (useRecovery && code.length < 8) {
      setError('Code de récupération invalide');
      return;
    }

    try {
      setLoading(true);
      setError('');
      const data = await twoFactorService.verify(userId, code);
      
      if (data.success) {
        // Stocker le token et les infos utilisateur
        localStorage.setItem('token', data.token);
        localStorage.setItem('user', JSON.stringify(data.user));
        localStorage.setItem('tenant', JSON.stringify(data.tenant));
        localStorage.setItem('tenant_id', data.tenant.id);
        
        onSuccess(data);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Code invalide');
    } finally {
      setLoading(false);
    }
  };

  const handleCodeChange = (e) => {
    const value = e.target.value;
    if (useRecovery) {
      // Code de récupération: lettres et chiffres avec tiret
      setCode(value.toUpperCase().slice(0, 9));
    } else {
      // Code TOTP: chiffres uniquement
      setCode(value.replace(/\D/g, '').slice(0, 6));
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-500 to-purple-600 p-4">
      <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 w-full max-w-md">
        <div className="text-center mb-6">
          <div className="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 dark:bg-indigo-900/30 rounded-full mb-4">
            <Shield className="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
          </div>
          <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
            Vérification 2FA
          </h2>
          <p className="text-gray-600 dark:text-gray-400 mt-2">
            {useRecovery 
              ? 'Entrez un code de récupération'
              : 'Entrez le code de votre application d\'authentification'
            }
          </p>
        </div>

        {error && (
          <div className="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex items-center gap-2 text-red-700 dark:text-red-400">
            <AlertTriangle className="w-5 h-5 flex-shrink-0" />
            <span>{error}</span>
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="mb-6">
            <input
              type="text"
              value={code}
              onChange={handleCodeChange}
              placeholder={useRecovery ? 'XXXX-XXXX' : '000000'}
              className={`w-full p-4 text-center text-2xl tracking-widest border-2 rounded-xl 
                dark:bg-gray-700 dark:border-gray-600 dark:text-white
                focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-800 
                transition ${error ? 'border-red-300' : 'border-gray-200'}`}
              maxLength={useRecovery ? 9 : 6}
              autoFocus
              autoComplete="one-time-code"
            />
          </div>

          <button
            type="submit"
            disabled={loading || (!useRecovery && code.length !== 6) || (useRecovery && code.length < 8)}
            className="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-xl 
              transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {loading ? (
              <span className="flex items-center justify-center gap-2">
                <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                Vérification...
              </span>
            ) : 'Vérifier'}
          </button>
        </form>

        <div className="mt-6 flex flex-col gap-3">
          <button
            type="button"
            onClick={() => { setUseRecovery(!useRecovery); setCode(''); setError(''); }}
            className="text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
          >
            {useRecovery 
              ? 'Utiliser le code de l\'application'
              : 'Utiliser un code de récupération'
            }
          </button>

          <button
            type="button"
            onClick={onCancel}
            className="flex items-center justify-center gap-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
          >
            <ArrowLeft className="w-4 h-4" />
            Retour à la connexion
          </button>
        </div>
      </div>
    </div>
  );
}
