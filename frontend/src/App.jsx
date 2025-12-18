import React, { Suspense, lazy, memo } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { useTenantStore } from './stores/tenantStore';
import Layout from './components/Layout';
import SubscriptionGuard from './components/SubscriptionGuard';
import ErrorBoundary from './components/ErrorBoundary';

// Loader optimisé avec message
const Loader = memo(({ message = 'Chargement...' }) => (
  <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50">
    <div className="w-10 h-10 border-4 border-orange-500 border-t-transparent rounded-full animate-spin mb-3"></div>
    <p className="text-gray-500 text-sm">{message}</p>
  </div>
));

// Lazy loading
const LandingPage = lazy(() => import('./pages/LandingPage'));
const LoginPage = lazy(() => import('./pages/LoginPage'));
const OnboardingPage = lazy(() => import('./pages/OnboardingPage'));
const AdaptiveDashboard = lazy(() => import('./pages/AdaptiveDashboard'));
const PurchasesPage = lazy(() => import('./pages/PurchasesPage'));
const TransfersPage = lazy(() => import('./pages/TransfersPage'));
const SalesPage = lazy(() => import('./pages/SalesPage'));
const POSPage = lazy(() => import('./pages/POSPage'));
const ProductsPage = lazy(() => import('./pages/ProductsPage'));
const ReportsPage = lazy(() => import('./pages/ReportsPage'));
const ChartOfAccountsPage = lazy(() => import('./pages/ChartOfAccountsPage'));
const TenantManagementPage = lazy(() => import('./pages/TenantManagementPage'));
const TenantConfigurationPage = lazy(() => import('./pages/TenantConfigurationPage'));
const PaymentConfigurationPage = lazy(() => import('./pages/PaymentConfigurationPage'));
const ExpenseTrackingPage = lazy(() => import('./pages/ExpenseTrackingPage'));
const UsersManagementPage = lazy(() => import('./pages/UsersManagementPage'));
const SettingsPage = lazy(() => import('./pages/SettingsPage'));
const SuppliersPage = lazy(() => import('./pages/SuppliersPage'));
const CustomersPage = lazy(() => import('./pages/CustomersPage'));
const ExpensesPage = lazy(() => import('./pages/ExpensesPage'));
const AccountingPage = lazy(() => import('./pages/AccountingPage'));
const DebugPage = lazy(() => import('./pages/DebugPage'));
const ApprovisionnementPage = lazy(() => import('./pages/ApprovisionnementPage'));
const SuperAdminDashboard = lazy(() => import('./pages/SuperAdminDashboard'));
const POSModePage = lazy(() => import('./pages/POSModePage'));
const CaissePage = lazy(() => import('./pages/CaissePage'));
const JournauxPage = lazy(() => import('./pages/JournauxPage'));
const GrandLivrePage = lazy(() => import('./pages/GrandLivrePage'));
const BalancePage = lazy(() => import('./pages/BalancePage'));
const SystemLogsPage = lazy(() => import('./pages/SystemLogsPage'));
const SubscriptionsPage = lazy(() => import('./pages/SubscriptionsPage'));
const PlatformSettingsPage = lazy(() => import('./pages/PlatformSettingsPage'));
const MonitoringPage = lazy(() => import('./pages/MonitoringPage'));
const SubscriptionRequiredPage = lazy(() => import('./pages/SubscriptionRequiredPage'));
const CashRegisterPage = lazy(() => import('./pages/CashRegisterPage'));
const PaymentGatewaysPage = lazy(() => import('./pages/PaymentGatewaysPage'));
const SuperAdminAccountingPage = lazy(() => import('./pages/SuperAdminAccountingPage'));
const POSTablesPage = lazy(() => import('./pages/POSTablesPage'));
const POSKitchenPage = lazy(() => import('./pages/POSKitchenPage'));
const POSOrderDetailPage = lazy(() => import('./pages/POSOrderDetailPage'));
const ManagerOrdersPage = lazy(() => import('./pages/ManagerOrdersPage'));
const ServerOrdersPage = lazy(() => import('./pages/ServerOrdersPage'));
const HomePage = lazy(() => import('./pages/HomePage'));
const FECExportPage = lazy(() => import('./pages/FECExportPage'));
const SupplierPortalPage = lazy(() => import('./pages/SupplierPortalPage'));
const EnrichedInventoryPage = lazy(() => import('./pages/EnrichedInventoryPage'));
const TenantDashboardPage = lazy(() => import('./pages/TenantDashboardPage'));
const ImmobilisationsPage = lazy(() => import('./pages/ImmobilisationsPage'));
const RapprochementBancairePage = lazy(() => import('./pages/RapprochementBancairePage'));
const ServerStockPage = lazy(() => import('./pages/ServerStockPage'));

function PrivateRoute({ children, skipSubscriptionCheck = false }) {
  const { token } = useTenantStore();
  
  if (!token) {
    return <Navigate to="/login" replace />;
  }

  // Si on doit vérifier l'abonnement
  if (!skipSubscriptionCheck) {
    return (
      <SubscriptionGuard>
        <Layout>{children}</Layout>
      </SubscriptionGuard>
    );
  }

  return <Layout>{children}</Layout>;
}

// Route privée sans vérification d'abonnement (pour la page d'abonnement)
function PrivateRouteNoSubscription({ children }) {
  const { token } = useTenantStore();
  
  if (!token) {
    return <Navigate to="/login" />;
  }

  return children;
}

export default function App() {
  return (
    <ErrorBoundary fallbackMessage="L'application n'a pas pu se charger. Veuillez réessayer.">
      <Router>
        <Suspense fallback={<Loader message="Chargement de la page..." />}>
          <Routes>
          {/* Public Routes */}
          <Route path="/" element={<LandingPage />} />
          <Route path="/login" element={<LoginPage />} />
          <Route path="/onboarding" element={<OnboardingPage />} />
          <Route path="/debug" element={<DebugPage />} />
          
          {/* Page d'abonnement - nécessite authentification mais pas d'abonnement */}
          <Route path="/subscription-required" element={
            <PrivateRouteNoSubscription>
              <SubscriptionRequiredPage />
            </PrivateRouteNoSubscription>
          } />

        {/* Private Routes */}
        
        {/* Page d'accueil - Point d'entrée pour tous les utilisateurs connectés */}
        <Route
          path="/home"
          element={
            <PrivateRouteNoSubscription>
              <HomePage />
            </PrivateRouteNoSubscription>
          }
        />
        
        <Route
          path="/dashboard"
          element={
            <PrivateRoute>
              <AdaptiveDashboard />
            </PrivateRoute>
          }
        />
        <Route
          path="/purchases"
          element={
            <PrivateRoute>
              <PurchasesPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/transfers"
          element={
            <PrivateRoute>
              <TransfersPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/sales"
          element={
            <PrivateRoute>
              <SalesPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/pos"
          element={
            <PrivateRoute>
              <POSPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/products"
          element={
            <PrivateRoute>
              <ProductsPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/inventory"
          element={<Navigate to="/inventory-enriched" replace />}
        />
        <Route
          path="/reports"
          element={
            <PrivateRoute>
              <ReportsPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/chart-of-accounts"
          element={
            <PrivateRoute>
              <ChartOfAccountsPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/tenant-management"
          element={
            <PrivateRoute>
              <TenantManagementPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/tenant-configuration"
          element={
            <PrivateRoute>
              <TenantConfigurationPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/payment-configuration"
          element={
            <PrivateRoute>
              <PaymentConfigurationPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/expense-tracking"
          element={
            <PrivateRoute>
              <ExpenseTrackingPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/users-management"
          element={
            <PrivateRoute>
              <UsersManagementPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/users"
          element={
            <PrivateRoute>
              <UsersManagementPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/settings"
          element={
            <PrivateRoute>
              <SettingsPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/suppliers"
          element={
            <PrivateRoute>
              <SuppliersPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/customers"
          element={
            <PrivateRoute>
              <CustomersPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/expenses"
          element={
            <PrivateRoute>
              <ExpensesPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/accounting"
          element={
            <PrivateRoute>
              <AccountingPage />
            </PrivateRoute>
          }
        />
        <Route
          path="/approvisionnement"
          element={
            <PrivateRoute>
              <ApprovisionnementPage />
            </PrivateRoute>
          }
        />

        {/* Super Admin Routes - skipSubscriptionCheck car le SuperAdmin n'a pas d'abonnement */}
        <Route path="/platform" element={<PrivateRoute skipSubscriptionCheck><SuperAdminDashboard /></PrivateRoute>} />
        <Route path="/system-logs" element={<PrivateRoute skipSubscriptionCheck><SystemLogsPage /></PrivateRoute>} />
        <Route path="/subscriptions" element={<PrivateRoute skipSubscriptionCheck><SubscriptionsPage /></PrivateRoute>} />
        <Route path="/platform-settings" element={<PrivateRoute skipSubscriptionCheck><PlatformSettingsPage /></PrivateRoute>} />
        <Route path="/monitoring" element={<PrivateRoute skipSubscriptionCheck><MonitoringPage /></PrivateRoute>} />
        <Route path="/payment-gateways" element={<PrivateRoute skipSubscriptionCheck><PaymentGatewaysPage /></PrivateRoute>} />
        <Route path="/superadmin-accounting" element={<PrivateRoute skipSubscriptionCheck><SuperAdminAccountingPage /></PrivateRoute>} />

        {/* Configuration Routes */}
        <Route path="/pos-mode" element={<PrivateRoute><POSModePage /></PrivateRoute>} />

        {/* Comptabilité Routes */}
        <Route path="/caisse" element={<PrivateRoute><CaissePage /></PrivateRoute>} />
        <Route path="/cash-register" element={<PrivateRoute><CashRegisterPage /></PrivateRoute>} />
        <Route path="/journaux" element={<PrivateRoute><JournauxPage /></PrivateRoute>} />
        <Route path="/grand-livre" element={<PrivateRoute><GrandLivrePage /></PrivateRoute>} />
        <Route path="/balance" element={<PrivateRoute><BalancePage /></PrivateRoute>} />
        <Route path="/fec-export" element={<PrivateRoute><FECExportPage /></PrivateRoute>} />
        <Route path="/immobilisations" element={<PrivateRoute><ImmobilisationsPage /></PrivateRoute>} />
        <Route path="/rapprochement-bancaire" element={<PrivateRoute><RapprochementBancairePage /></PrivateRoute>} />

        {/* Dashboard Tenant optimisé */}
        <Route path="/tenant-dashboard" element={<PrivateRoute><TenantDashboardPage /></PrivateRoute>} />

        {/* POS Routes */}
        <Route path="/pos/tables" element={<PrivateRoute><POSTablesPage /></PrivateRoute>} />
        <Route path="/pos/kitchen" element={<PrivateRoute><POSKitchenPage /></PrivateRoute>} />
        <Route path="/pos/order/:orderId" element={<PrivateRoute><POSOrderDetailPage /></PrivateRoute>} />
        <Route path="/pos/manager-orders" element={<PrivateRoute><ManagerOrdersPage /></PrivateRoute>} />
        <Route path="/pos/my-orders" element={<PrivateRoute><ServerOrdersPage /></PrivateRoute>} />

        {/* Portail Fournisseur */}
        <Route path="/supplier-portal" element={<PrivateRoute><SupplierPortalPage /></PrivateRoute>} />
        <Route path="/supplier-portal/orders/:id" element={<PrivateRoute><SupplierPortalPage /></PrivateRoute>} />
        
        {/* Détail commande d'achat (lien depuis email) */}
        <Route path="/purchases/:id" element={<PrivateRoute><PurchasesPage /></PrivateRoute>} />

        {/* Inventaire Enrichi */}
        <Route path="/inventory-enriched" element={<PrivateRoute><EnrichedInventoryPage /></PrivateRoute>} />

        {/* Option B: Stock Délégué aux Serveurs */}
        <Route path="/server-stock" element={<PrivateRoute><ServerStockPage /></PrivateRoute>} />

        {/* Redirect */}
        <Route path="*" element={<Navigate to="/dashboard" />} />
          </Routes>
        </Suspense>
      </Router>
    </ErrorBoundary>
  );
}

