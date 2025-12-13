import { useCallback } from 'react';
import { useTenantStore } from '../stores/tenantStore';
import { hasPermission, hasAnyPermission, hasAllPermissions, getRolePermissions } from '../utils/rbac';

/**
 * Hook pour vérifier les permissions de l'utilisateur courant
 * @returns {object} Méthodes de vérification des permissions
 */
export function usePermission() {
  const { user } = useTenantStore();
  const userRole = user?.role;

  const checkPermission = useCallback((permission) => {
    if (!userRole) return false;
    return hasPermission(userRole, permission);
  }, [userRole]);

  const checkAny = useCallback((permissions) => {
    if (!userRole) return false;
    return hasAnyPermission(userRole, permissions);
  }, [userRole]);

  const checkAll = useCallback((permissions) => {
    if (!userRole) return false;
    return hasAllPermissions(userRole, permissions);
  }, [userRole]);

  const getAllPermissions = useCallback(() => {
    if (!userRole) return [];
    return getRolePermissions(userRole);
  }, [userRole]);

  // Aliases pour plus de clarté
  const can = checkPermission;
  const canAny = checkAny;
  const canAll = checkAll;

  return {
    // Méthodes longues
    hasPermission: checkPermission,
    hasAnyPermission: checkAny,
    hasAllPermissions: checkAll,
    getPermissions: getAllPermissions,

    // Aliases courts
    can,
    canAny,
    canAll,
    userRole,
    user
  };
}

export default usePermission;
