import { lazy, Suspense, useEffect } from 'react';
import { useTenantStore } from '../stores/tenantStore';
import { prefetchEssentialData, prefetchSecondaryData } from '../services/prefetch';

const SuperAdminDashboard = lazy(() => import('./SuperAdminDashboard'));
const DashboardCompletePage = lazy(() => import('./DashboardCompletePage'));
const ManagerDashboard = lazy(() => import('./ManagerDashboard'));
const AccountantDashboard = lazy(() => import('./AccountantDashboard'));
const MagasinierDashboard = lazy(() => import('./MagasinierDashboard'));
const CaissierDashboard = lazy(() => import('./CaissierDashboard'));
const ServeurDashboard = lazy(() => import('./ServeurDashboard'));

const Loader = () => (
  <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50">
    <div className="w-12 h-12 border-4 border-orange-500 border-t-transparent rounded-full animate-spin mb-3"></div>
    <p className="text-gray-500 text-sm">Chargement du tableau de bord...</p>
  </div>
);

export default function AdaptiveDashboard() {
  const { user } = useTenantStore();
  const role = user?.role;

  let Component = DashboardCompletePage;
  if (role === 'super_admin') Component = SuperAdminDashboard;
  else if (role === 'manager') Component = ManagerDashboard;
  else if (role === 'accountant') Component = AccountantDashboard;
  else if (role === 'magasinier_gros' || role === 'magasinier_detail') Component = MagasinierDashboard;
  else if (role === 'caissier') Component = CaissierDashboard;
  else if (role === 'pos_server') Component = ServeurDashboard;

  return <Suspense fallback={<Loader />}><Component /></Suspense>;
}
