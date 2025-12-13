import { Navigate } from 'react-router-dom';
import { useTenantStore } from '../stores/tenantStore';
import { hasPermission, hasAnyPermission } from '../utils/rbac';

/**
 * Composant de route protégée avec vérification RBAC
 * @param {object} props
 * @param {React.ReactNode} props.children - Contenu à afficher si autorisé
 * @param {string|string[]} props.permission - Permission(s) requise(s)
 * @param {boolean} props.requireAll - Si true, toutes les permissions sont requises (default: false = au moins une)
 * @param {string} props.redirectTo - Redirection si non autorisé (default: /dashboard)
 * @param {React.ReactNode} props.fallback - Composant à afficher si non autorisé (au lieu de rediriger)
 */
export default function ProtectedRoute({ 
  children, 
  permission, 
  requireAll = false,
  redirectTo = '/dashboard',
  fallback = null
}) {
  const { user, token } = useTenantStore();

  // Non connecté → login
  if (!token) {
    return <Navigate to="/login" replace />;
  }

  // Pas de permission requise → accès libre
  if (!permission) {
    return children;
  }

  const userRole = user?.role;
  const permissions = Array.isArray(permission) ? permission : [permission];

  // Vérifier les permissions
  const hasAccess = requireAll
    ? permissions.every(p => hasPermission(userRole, p))
    : hasAnyPermission(userRole, permissions);

  if (!hasAccess) {
    // Afficher le fallback ou rediriger
    if (fallback) {
      return fallback;
    }
    return <Navigate to={redirectTo} replace />;
  }

  return children;
}

/**
 * Composant pour afficher du contenu conditionnel selon les permissions
 */
export function PermissionGate({ 
  children, 
  permission, 
  requireAll = false,
  fallback = null 
}) {
  const { user } = useTenantStore();
  const userRole = user?.role;

  if (!permission) return children;

  const permissions = Array.isArray(permission) ? permission : [permission];
  const hasAccess = requireAll
    ? permissions.every(p => hasPermission(userRole, p))
    : hasAnyPermission(userRole, permissions);

  return hasAccess ? children : fallback;
}

/**
 * HOC pour protéger un composant avec des permissions
 */
export function withPermission(WrappedComponent, permission, options = {}) {
  return function PermissionWrapper(props) {
    return (
      <ProtectedRoute permission={permission} {...options}>
        <WrappedComponent {...props} />
      </ProtectedRoute>
    );
  };
}
