import { useState, useEffect } from 'react';
import { Shield, ShieldCheck, ShieldOff, Copy, RefreshCw, Eye, EyeOff, AlertTriangle } from 'lucide-react';
import twoFactorService from '../services/twoFactorService';

/**
 * Composant de configuration 2FA
 * Permet d'activer/désactiver l'authentification à deux facteurs
 */
export default function TwoFactorSetup() {
  const [status, setStatus] = useState({ two_factor_enabled: false });
  const [loading, setLoading] = useState(true);
  const [step, setStep] = useState('status'); // status, setup, confirm, recovery, disable
  const [secret, setSecret] = useState('');
  const [qrUrl, setQrUrl] = useState('');
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [recoveryCodes, setRecoveryCodes] = useState([]);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    loadStatus();
  }, []);

  const loadStatus = async () => {
    try {
      setLoading(true);
      const data = await twoFactorService.getStatus();
      setStatus(data);
      setStep('status');
    } catch (err) {
      setError('Erreur lors du chargement du statut 2FA');
    } finally {
      setLoading(false);
    }
  };

  const handleEnable = async () => {
    try {
      setLoading(true);
      setError('');
      const data = await twoFactorService.enable();
      setSecret(data.secret);
      setQrUrl(twoFactorService.generateQRCodeUrl(data.qr_url));
      setStep('setup');
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors de l\'activation');
    } finally {
      setLoading(false);
    }
  };

  const handleConfirm = async (e) => {
    e.preventDefault();
    if (code.length !== 6) {
      setError('Le code doit contenir 6 chiffres');
      return;
    }
    
    try {
      setLoading(true);
      setError('');
      const data = await twoFactorService.confirm(code);
      setRecoveryCodes(data.recovery_codes);
      setSuccess('2FA activé avec succès !');
      setStep('recovery');
    } catch (err) {
      setError(err.response?.data?.message || 'Code invalide');
    } finally {
      setLoading(false);
    }
  };

  const handleDisable = async (e) => {
    e.preventDefault();
    try {
      setLoading(true);
      setError('');
      await twoFactorService.disable(password);
      setSuccess('2FA désactivé');
      setPassword('');
      await loadStatus();
    } catch (err) {
      setError(err.response?.data?.message || 'Mot de passe incorrect');
    } finally {
      setLoading(false);
    }
  };

  const handleRegenerateCodes = async () => {
    try {
      setLoading(true);
      setError('');
      const data = await twoFactorService.regenerateCodes(password);
      setRecoveryCodes(data.recovery_codes);
      setSuccess('Codes régénérés');
      setStep('recovery');
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur');
    } finally {
      setLoading(false);
    }
  };

  const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const copyAllCodes = () => {
    const text = recoveryCodes.join('\n');
    copyToClipboard(text);
  };

  if (loading && step === 'status') {
    return (
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div className="animate-pulse flex items-center gap-3">
          <div className="w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
          <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-48"></div>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <div className="flex items-center gap-3 mb-6">
        <Shield className="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
        <div>
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
            Authentification à deux facteurs (2FA)
          </h3>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Sécurisez votre compte avec une vérification supplémentaire
          </p>
        </div>
      </div>

      {error && (
        <div className="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex items-center gap-2 text-red-700 dark:text-red-400">
          <AlertTriangle className="w-5 h-5" />
          {error}
        </div>
      )}

      {success && (
        <div className="mb-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-400">
          {success}
        </div>
      )}

      {/* Status View */}
      {step === 'status' && (
        <div>
          <div className={`flex items-center gap-3 p-4 rounded-lg mb-4 ${
            status.two_factor_enabled 
              ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' 
              : 'bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800'
          }`}>
            {status.two_factor_enabled ? (
              <>
                <ShieldCheck className="w-6 h-6 text-green-600 dark:text-green-400" />
                <div>
                  <p className="font-medium text-green-800 dark:text-green-300">2FA Activé</p>
                  <p className="text-sm text-green-600 dark:text-green-400">
                    Votre compte est protégé par l'authentification à deux facteurs
                  </p>
                </div>
              </>
            ) : (
              <>
                <ShieldOff className="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
                <div>
                  <p className="font-medium text-yellow-800 dark:text-yellow-300">2FA Non activé</p>
                  <p className="text-sm text-yellow-600 dark:text-yellow-400">
                    Activez le 2FA pour renforcer la sécurité de votre compte
                  </p>
                </div>
              </>
            )}
          </div>

          {status.two_factor_enabled ? (
            <div className="flex gap-3">
              <button
                onClick={() => setStep('disable')}
                className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition"
              >
                Désactiver 2FA
              </button>
              <button
                onClick={() => setStep('regenerate')}
                className="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition flex items-center gap-2"
              >
                <RefreshCw className="w-4 h-4" />
                Régénérer codes
              </button>
            </div>
          ) : (
            <button
              onClick={handleEnable}
              disabled={loading}
              className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition disabled:opacity-50"
            >
              {loading ? 'Chargement...' : 'Activer 2FA'}
            </button>
          )}
        </div>
      )}

      {/* Setup View - QR Code */}
      {step === 'setup' && (
        <div>
          <div className="mb-6">
            <p className="text-gray-700 dark:text-gray-300 mb-4">
              1. Scannez ce QR code avec Google Authenticator ou une application similaire :
            </p>
            <div className="flex justify-center mb-4">
              <img src={qrUrl} alt="QR Code 2FA" className="rounded-lg border dark:border-gray-600" />
            </div>
            <p className="text-gray-700 dark:text-gray-300 mb-2">
              Ou entrez ce code manuellement :
            </p>
            <div className="flex items-center gap-2 p-3 bg-gray-100 dark:bg-gray-700 rounded-lg font-mono">
              <span className="flex-1 text-center tracking-widest">{secret}</span>
              <button
                onClick={() => copyToClipboard(secret)}
                className="p-2 hover:bg-gray-200 dark:hover:bg-gray-600 rounded"
              >
                <Copy className="w-4 h-4" />
              </button>
            </div>
            {copied && <p className="text-sm text-green-600 mt-1">Copié !</p>}
          </div>

          <form onSubmit={handleConfirm}>
            <p className="text-gray-700 dark:text-gray-300 mb-2">
              2. Entrez le code à 6 chiffres affiché dans l'application :
            </p>
            <input
              type="text"
              value={code}
              onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
              placeholder="000000"
              className="w-full p-3 text-center text-2xl tracking-widest border rounded-lg dark:bg-gray-700 dark:border-gray-600 mb-4"
              maxLength={6}
            />
            <div className="flex gap-3">
              <button
                type="button"
                onClick={() => { setStep('status'); setCode(''); }}
                className="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-lg transition"
              >
                Annuler
              </button>
              <button
                type="submit"
                disabled={loading || code.length !== 6}
                className="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition disabled:opacity-50"
              >
                {loading ? 'Vérification...' : 'Confirmer'}
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Recovery Codes View */}
      {step === 'recovery' && (
        <div>
          <div className="mb-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
            <p className="font-medium text-yellow-800 dark:text-yellow-300 mb-2">
              ⚠️ Sauvegardez ces codes de récupération
            </p>
            <p className="text-sm text-yellow-700 dark:text-yellow-400">
              Ces codes vous permettront de vous connecter si vous perdez accès à votre application d'authentification.
              Chaque code ne peut être utilisé qu'une seule fois.
            </p>
          </div>

          <div className="grid grid-cols-2 gap-2 mb-4 p-4 bg-gray-100 dark:bg-gray-700 rounded-lg font-mono text-sm">
            {recoveryCodes.map((code, i) => (
              <div key={i} className="p-2 bg-white dark:bg-gray-800 rounded text-center">
                {code}
              </div>
            ))}
          </div>

          <div className="flex gap-3">
            <button
              onClick={copyAllCodes}
              className="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition flex items-center gap-2"
            >
              <Copy className="w-4 h-4" />
              {copied ? 'Copié !' : 'Copier tous'}
            </button>
            <button
              onClick={loadStatus}
              className="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition"
            >
              Terminé
            </button>
          </div>
        </div>
      )}

      {/* Disable View */}
      {step === 'disable' && (
        <form onSubmit={handleDisable}>
          <p className="text-gray-700 dark:text-gray-300 mb-4">
            Pour désactiver le 2FA, entrez votre mot de passe :
          </p>
          <div className="relative mb-4">
            <input
              type={showPassword ? 'text' : 'password'}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="Mot de passe"
              className="w-full p-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 pr-10"
            />
            <button
              type="button"
              onClick={() => setShowPassword(!showPassword)}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500"
            >
              {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
            </button>
          </div>
          <div className="flex gap-3">
            <button
              type="button"
              onClick={() => { setStep('status'); setPassword(''); setError(''); }}
              className="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-lg transition"
            >
              Annuler
            </button>
            <button
              type="submit"
              disabled={loading || !password}
              className="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition disabled:opacity-50"
            >
              {loading ? 'Désactivation...' : 'Désactiver 2FA'}
            </button>
          </div>
        </form>
      )}

      {/* Regenerate Codes View */}
      {step === 'regenerate' && (
        <form onSubmit={(e) => { e.preventDefault(); handleRegenerateCodes(); }}>
          <p className="text-gray-700 dark:text-gray-300 mb-4">
            Pour régénérer vos codes de récupération, entrez votre mot de passe :
          </p>
          <div className="relative mb-4">
            <input
              type={showPassword ? 'text' : 'password'}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="Mot de passe"
              className="w-full p-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 pr-10"
            />
            <button
              type="button"
              onClick={() => setShowPassword(!showPassword)}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500"
            >
              {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
            </button>
          </div>
          <div className="flex gap-3">
            <button
              type="button"
              onClick={() => { setStep('status'); setPassword(''); setError(''); }}
              className="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-lg transition"
            >
              Annuler
            </button>
            <button
              type="submit"
              disabled={loading || !password}
              className="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition disabled:opacity-50"
            >
              {loading ? 'Génération...' : 'Régénérer'}
            </button>
          </div>
        </form>
      )}
    </div>
  );
}
