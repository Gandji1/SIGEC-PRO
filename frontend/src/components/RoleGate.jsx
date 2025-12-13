import React from 'react';
import { usePermission } from '../hooks/usePermission';

/**
 * Composant de gating par permission
 * Affiche les enfants SEULEMENT si l'utilisateur a la permission
 *
 * Usage:
 * <RoleGate permission="users.create">
 *   <button>Créer utilisateur</button>
 * </RoleGate>
 *
 * ou avec multiple permissions (ET):
 * <RoleGate permissions={['users.create', 'users.edit']}>
 *   ...
 * </RoleGate>
 *
 * ou avec multiple permissions (OU):
 * <RoleGate permissions={['sales.create', 'manager.supervise']} require="any">
 *   ...
 * </RoleGate>
 */
export default function RoleGate({
  permission,
  permissions,
  require = 'all',
  fallback = null,
  children,
  debug = false
}) {
  const { hasPermission, hasAnyPermission, hasAllPermissions } = usePermission();

  let hasAccess = false;

  // Si permission singulière
  if (permission) {
    hasAccess = hasPermission(permission);
    if (debug) console.log(`[RoleGate] Permission "${permission}": ${hasAccess}`);
  }
  // Si permissions multiples
  else if (permissions && Array.isArray(permissions)) {
    if (require === 'any') {
      hasAccess = hasAnyPermission(permissions);
      if (debug) console.log(`[RoleGate] Any of ${permissions}: ${hasAccess}`);
    } else {
      hasAccess = hasAllPermissions(permissions);
      if (debug) console.log(`[RoleGate] All of ${permissions}: ${hasAccess}`);
    }
  }

  if (!hasAccess) {
    return fallback;
  }

  return children;
}

/**
 * Variante: RoleGate pour afficher un fallback si AUCUNE permission
 * Usage: <RoleGateOr condition={hasAccess} fallback={<NoAccess />}>
 *   Content
 * </RoleGateOr>
 */
export function RoleGateOr({ condition, fallback, children }) {
  return condition ? children : fallback;
}

/**
 * Variante: Show/Hide avec classe CSS
 * Masque via display:none si pas d'accès
 */
export function RoleGateClass({ permission, permissions, require = 'all', className = '', children }) {
  const { hasPermission, hasAnyPermission, hasAllPermissions } = usePermission();

  let hasAccess = false;

  if (permission) {
    hasAccess = hasPermission(permission);
  } else if (permissions && Array.isArray(permissions)) {
    hasAccess = require === 'any'
      ? hasAnyPermission(permissions)
      : hasAllPermissions(permissions);
  }

  return (
    <div className={!hasAccess ? 'hidden' : ''} style={!hasAccess ? { display: 'none' } : {}}>
      {children}
    </div>
  );
}
