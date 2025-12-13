import { useState, useEffect, useRef, useCallback } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useTenantStore } from '../stores/tenantStore';
import apiClient from '../services/apiClient';

/**
 * Composant qui vérifie si le tenant a un abonnement actif
 * Redirige vers /subscription-required si pas d'abonnement
 * Les super_admin passent toujours
 * 
 * IMPORTANT: Si l'abonnement est actif, ne JAMAIS demander de mise à jour
 */
export default function SubscriptionGuard({ children }) {
  const { user, token } = useTenantStore();
  const location = useLocation();
  const [status, setStatus] = useState('checking'); // 'checking' | 'allowed' | 'denied'
  const lastCheckRef = useRef(null);
  const cacheTimeoutRef = useRef(null);

  // Cache de 5 minutes pour éviter les vérifications répétées
  const CACHE_DURATION = 5 * 60 * 1000;

  const checkSubscription = useCallback(async (forceCheck = false) => {
    // Si on a vérifié récemment et que c'était OK, ne pas revérifier
    if (!forceCheck && lastCheckRef.current) {
      const { timestamp, isActive } = lastCheckRef.current;
      if (Date.now() - timestamp < CACHE_DURATION && isActive) {
        setStatus('allowed');
        return;
      }
    }

    try {
      const res = await apiClient.get('/subscription/status');
      const data = res.data?.data;

      // Vérifier si abonnement actif ou en essai
      // Un abonnement est considéré actif si:
      // 1. has_subscription est true ET
      // 2. status est 'active' ou 'trial' ET
      // 3. (optionnel) end_date n'est pas dépassé
      const isActive = data?.has_subscription === true && 
        ['active', 'trial', 'grace_period'].includes(data?.status);
      
      // Mettre en cache le résultat
      lastCheckRef.current = {
        timestamp: Date.now(),
        isActive,
        data
      };
      
      setStatus(isActive ? 'allowed' : 'denied');
    } catch (err) {
      console.error('Subscription check error:', err);
      // En cas d'erreur réseau (500, timeout, etc.), permettre l'accès
      // pour ne pas bloquer l'utilisateur à cause d'un problème serveur
      // Le backend vérifiera de toute façon les permissions
      if (err.response?.status === 401) {
        // Non authentifié - rediriger
        setStatus('denied');
      } else {
        // Erreur serveur ou réseau - permettre l'accès (fail-open)
        console.warn('Subscription check failed, allowing access (fail-open)');
        setStatus('allowed');
      }
    }
  }, []);

  useEffect(() => {
    if (!token || !user) {
      setStatus('denied');
      return;
    }

    // Super Admin bypass - toujours accès
    if (user?.role === 'super_admin') {
      setStatus('allowed');
      return;
    }

    // Vérifier l'abonnement
    checkSubscription();

    // Nettoyer le timeout précédent
    if (cacheTimeoutRef.current) {
      clearTimeout(cacheTimeoutRef.current);
    }

    // Programmer une revérification après expiration du cache
    cacheTimeoutRef.current = setTimeout(() => {
      checkSubscription(true);
    }, CACHE_DURATION);

    return () => {
      if (cacheTimeoutRef.current) {
        clearTimeout(cacheTimeoutRef.current);
      }
    };
  }, [token, user?.id, checkSubscription]);

  // Ne pas revérifier à chaque changement de route si le cache est valide
  useEffect(() => {
    if (status === 'allowed' && lastCheckRef.current?.isActive) {
      // Déjà vérifié et actif, pas besoin de revérifier
      return;
    }
  }, [location.pathname, status]);

  // Afficher un loader pendant la vérification
  if (status === 'checking') {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900">
        <div className="text-center">
          <div className="w-12 h-12 bg-brand-600 rounded-2xl flex items-center justify-center mx-auto mb-4 animate-pulse">
            <span className="text-xl font-black text-white">S</span>
          </div>
          <p className="text-gray-500 dark:text-gray-400 text-sm">Vérification...</p>
        </div>
      </div>
    );
  }

  // Rediriger si pas d'abonnement
  if (status === 'denied') {
    return <Navigate to="/subscription-required" replace />;
  }

  // Tout est OK, afficher le contenu
  return children;
}
