import { memo } from 'react';

/**
 * Badge de statut réutilisable
 * @param {string} status - Statut à afficher
 * @param {object} statusConfig - Configuration des statuts (optionnel)
 * @param {string} size - Taille ('sm' | 'md' | 'lg')
 */
const StatusBadge = memo(({ status, statusConfig, size = 'md' }) => {
  // Configuration par défaut des statuts
  const defaultConfig = {
    // Statuts génériques
    active: { label: 'Actif', color: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' },
    inactive: { label: 'Inactif', color: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-400' },
    pending: { label: 'En attente', color: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' },
    completed: { label: 'Terminé', color: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' },
    cancelled: { label: 'Annulé', color: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
    
    // Statuts commandes POS
    approved: { label: 'Approuvée', color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
    preparing: { label: 'En préparation', color: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' },
    ready: { label: 'Prête', color: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400' },
    served: { label: 'Servie', color: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' },
    paid: { label: 'Payée', color: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' },
    confirmed: { label: 'Confirmée', color: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' },
    
    // Statuts achats
    draft: { label: 'Brouillon', color: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-400' },
    ordered: { label: 'Commandé', color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
    received: { label: 'Reçu', color: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' },
    partial: { label: 'Partiel', color: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' },
    
    // Statuts transferts
    in_transit: { label: 'En transit', color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
    delivered: { label: 'Livré', color: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' },
    
    // Statuts abonnement
    trial: { label: 'Essai', color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
    expired: { label: 'Expiré', color: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
    suspended: { label: 'Suspendu', color: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
  };

  const config = statusConfig || defaultConfig;
  const statusInfo = config[status?.toLowerCase()] || { 
    label: status || 'Inconnu', 
    color: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-400' 
  };

  const sizes = {
    sm: 'px-2 py-0.5 text-[10px]',
    md: 'px-2.5 py-1 text-xs',
    lg: 'px-3 py-1.5 text-sm',
  };

  return (
    <span className={`inline-flex items-center font-medium rounded-full ${statusInfo.color} ${sizes[size]}`}>
      {statusInfo.label}
    </span>
  );
});

export default StatusBadge;
