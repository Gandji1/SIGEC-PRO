/**
 * RBAC Utilities
 * Gère les permissions et rôles utilisateur
 */

// ============================================
// CONSTANTS
// ============================================

export const ROLES = Object.freeze({
  SUPER_ADMIN: "super_admin",
  OWNER: "owner",
  ADMIN: "admin",
  MANAGER: "manager",
  ACCOUNTANT: "accountant",
  MAGASINIER_GROS: "magasinier_gros",
  MAGASINIER_DETAIL: "magasinier_detail",
  CAISSIER: "caissier",
  POS_SERVER: "pos_server",
  AUDITOR: "auditor",
  SUPPLIER: "supplier",
});

// ============================================
// PERMISSION GROUPS (composable)
// ============================================

const PERMISSIONS = {
  // Platform (Super Admin)
  platform: [
    "platform.view",
    "tenants.list",
    "tenants.create",
    "tenants.edit",
    "tenants.delete",
    "tenants.suspend",
    "tenants.impersonate",
    "plans.manage",
    "psp.manage",
    "psp.webhook",
    "backups.manage",
    "migrations.run",
    "settings.global",
    "logs.view",
    "users.reset-password",
  ],

  // Tenant base
  tenantBase: ["tenant.view", "tenant.edit"],
  tenantView: ["tenant.view"],

  // Users
  usersManage: [
    "users.list",
    "users.create",
    "users.edit",
    "users.delete",
    "roles.assign",
  ],

  // Warehouses
  warehousesManage: ["warehouses.manage"],

  // Suppliers
  suppliersManage: ["suppliers.manage"],
  suppliersFull: [
    "suppliers.list",
    "suppliers.create",
    "suppliers.edit",
    "suppliers.delete",
  ],
  suppliersView: ["suppliers.list", "suppliers.view"],

  // Customers
  customersManage: ["customers.manage"],
  customersFull: [
    "customers.list",
    "customers.create",
    "customers.edit",
    "customers.delete",
  ],

  // Purchases
  purchasesManage: ["purchases.manage"],
  purchasesFull: ["purchases.list", "purchases.create", "purchases.receive"],
  purchasesApprove: ["purchases.list", "purchases.approve", "purchases.view"],
  purchasesReceive: ["purchases.list", "purchases.receive"],

  // Sales
  salesManage: ["sales.manage"],
  salesFull: ["sales.list", "sales.validate", "sales.view"],
  salesView: ["sales.list", "sales.view"],

  // Transfers
  transfersManage: ["transfers.manage"],
  transfersFull: ["transfers.list", "transfers.create", "transfers.approve"],
  transfersView: ["transfers.list", "transfers.view"],
  transfersReceive: ["transfers.list", "transfers.receive"],

  // Stocks
  stocksFull: ["stocks.view", "stocks.adjust", "stocks.move"],
  stocksView: ["stocks.view"],
  stocksMove: ["stocks.view", "stocks.move"],

  // Inventories
  inventoriesManage: ["inventories.manage"],
  inventoriesFull: [
    "inventories.list",
    "inventories.manage",
    "inventories.validate",
  ],
  inventoriesView: ["inventories.list", "inventories.view"],
  inventoriesParticipate: ["inventories.list", "inventories.participate"],

  // Accounting
  accountingFull: [
    "accounting.view",
    "accounting.post",
    "accounting.close-period",
    "accounting.export",
  ],
  accountingView: ["accounting.view"],

  // Charges
  chargesManage: ["charges.manage", "charges.create", "charges.edit"],
  chargesCreate: ["charges.list", "charges.create"],
  chargesView: ["charges.list", "charges.view"],

  // Reports
  reportsFull: ["reports.view", "reports.export"],
  reportsView: ["reports.view"],

  // Audit
  auditView: ["audit.view"],

  // PSP
  pspDelegate: ["psp.delegate"],

  // Cash
  cashManage: ["cash.manage", "cash.view"],
  cashView: ["cash.view"],

  // Dashboards
  dashboardAll: [
    "dashboard.manager",
    "dashboard.accounting",
    "dashboard.warehouse",
    "dashboard.pos",
    "dashboard.audit",
    "dashboard.cashier",
  ],
  dashboardManager: ["dashboard.manager"],
  dashboardAccounting: ["dashboard.accounting"],
  dashboardWarehouse: ["dashboard.warehouse"],
  dashboardPos: ["dashboard.pos"],
  dashboardAudit: ["dashboard.audit"],
  dashboardCashier: ["dashboard.cashier"],

  // POS
  posFull: [
    "pos.supervise",
    "pos.payments",
    "pos.close-session",
    "pos.create-sale",
    "pos.apply-discount",
    "pos.view-history",
    "pos.prepare",
  ],
  posServer: ["pos.create-sale", "pos.view-history"],
  posCashier: ["pos.payments", "pos.close-session"],
  posPrepare: ["pos.prepare"],

  // POS Orders (Manager)
  posOrdersManage: [
    "pos_orders.manage",
    "pos_orders.approve",
    "pos_orders.serve",
    "pos_orders.validate",
  ],

  // Supplier portal
  supplierPortal: [
    "supplier.dashboard",
    "supplier.orders.view",
    "supplier.orders.confirm",
    "supplier.orders.ship",
    "supplier.orders.deliver",
    "supplier.history",
  ],
};

// Helper to merge permission groups
const mergePermissions = (...groups) => [...new Set(groups.flat())];

// ============================================
// OWNER PERMISSIONS (base for admin alias)
// ============================================
const OWNER_PERMISSIONS = mergePermissions(
  PERMISSIONS.tenantBase,
  PERMISSIONS.usersManage,
  PERMISSIONS.warehousesManage,
  PERMISSIONS.suppliersFull,
  PERMISSIONS.customersFull,
  PERMISSIONS.purchasesApprove,
  PERMISSIONS.salesView,
  PERMISSIONS.transfersView,
  PERMISSIONS.stocksView,
  PERMISSIONS.inventoriesView,
  PERMISSIONS.accountingFull,
  PERMISSIONS.chargesView,
  PERMISSIONS.reportsFull,
  PERMISSIONS.auditView,
  PERMISSIONS.pspDelegate,
  PERMISSIONS.cashView
);

// ============================================
// ROLE → PERMISSIONS MAPPING
// ============================================
const ROLE_PERMISSIONS = {
  [ROLES.SUPER_ADMIN]: mergePermissions(
    PERMISSIONS.platform,
    PERMISSIONS.tenantBase,
    PERMISSIONS.usersManage,
    PERMISSIONS.warehousesManage,
    PERMISSIONS.suppliersManage,
    PERMISSIONS.customersManage,
    PERMISSIONS.purchasesManage,
    PERMISSIONS.purchasesFull,
    PERMISSIONS.salesManage,
    PERMISSIONS.salesFull,
    PERMISSIONS.transfersManage,
    PERMISSIONS.stocksFull,
    PERMISSIONS.inventoriesManage,
    PERMISSIONS.inventoriesFull,
    PERMISSIONS.accountingFull,
    PERMISSIONS.chargesManage,
    PERMISSIONS.reportsFull,
    PERMISSIONS.auditView,
    PERMISSIONS.pspDelegate,
    PERMISSIONS.dashboardAll,
    PERMISSIONS.posFull
  ),

  [ROLES.OWNER]: OWNER_PERMISSIONS,
  [ROLES.ADMIN]: OWNER_PERMISSIONS, // Alias for backward compatibility

  [ROLES.MANAGER]: mergePermissions(
    PERMISSIONS.dashboardManager,
    PERMISSIONS.suppliersView,
    PERMISSIONS.purchasesFull,
    PERMISSIONS.salesView,
    PERMISSIONS.posOrdersManage,
    PERMISSIONS.stocksFull,
    PERMISSIONS.transfersFull,
    PERMISSIONS.inventoriesFull,
    PERMISSIONS.chargesCreate,
    PERMISSIONS.cashManage,
    PERMISSIONS.reportsView,
    PERMISSIONS.auditView
  ),

  [ROLES.ACCOUNTANT]: mergePermissions(
    PERMISSIONS.tenantView,
    PERMISSIONS.dashboardAccounting,
    ["sales.list", "purchases.list"],
    PERMISSIONS.accountingFull,
    PERMISSIONS.chargesCreate,
    ["charges.edit"],
    PERMISSIONS.reportsFull,
    PERMISSIONS.auditView
  ),

  [ROLES.MAGASINIER_GROS]: mergePermissions(
    PERMISSIONS.tenantView,
    PERMISSIONS.dashboardWarehouse,
    PERMISSIONS.purchasesReceive,
    PERMISSIONS.stocksMove,
    ["transfers.create", "transfers.approve"],
    PERMISSIONS.inventoriesParticipate
  ),

  [ROLES.MAGASINIER_DETAIL]: mergePermissions(
    PERMISSIONS.tenantView,
    PERMISSIONS.dashboardWarehouse,
    PERMISSIONS.stocksMove,
    PERMISSIONS.transfersReceive,
    PERMISSIONS.posPrepare,
    PERMISSIONS.inventoriesParticipate
  ),

  [ROLES.CAISSIER]: mergePermissions(
    PERMISSIONS.tenantView,
    PERMISSIONS.dashboardCashier,
    PERMISSIONS.posCashier,
    ["sales.view"]
  ),

  [ROLES.POS_SERVER]: mergePermissions(
    PERMISSIONS.tenantView,
    PERMISSIONS.dashboardPos,
    PERMISSIONS.posServer,
    ["sales.view"]
  ),

  [ROLES.AUDITOR]: mergePermissions(
    PERMISSIONS.tenantView,
    PERMISSIONS.dashboardAudit,
    ["sales.list", "purchases.list"],
    PERMISSIONS.stocksView,
    PERMISSIONS.accountingView,
    PERMISSIONS.reportsView,
    PERMISSIONS.auditView,
    ["charges.list"]
  ),

  [ROLES.SUPPLIER]: PERMISSIONS.supplierPortal,
};

/**
 * Vérifie si un utilisateur a une permission spécifique
 * @param {string} userRole - Le rôle de l'utilisateur
 * @param {string} permission - La permission à vérifier
 * @returns {boolean}
 */
export function hasPermission(userRole, permission) {
  if (!userRole || !permission) return false;
  const permissions = ROLE_PERMISSIONS[userRole] || [];
  return permissions.includes(permission);
}

/**
 * Vérifie si un utilisateur a AU MOINS UNE des permissions listées
 * @param {string} userRole
 * @param {string|string[]} permissions
 * @returns {boolean}
 */
export function hasAnyPermission(userRole, permissions) {
  const perms = Array.isArray(permissions) ? permissions : [permissions];
  return perms.some((p) => hasPermission(userRole, p));
}

/**
 * Vérifie si un utilisateur a TOUTES les permissions listées
 * @param {string} userRole
 * @param {string|string[]} permissions
 * @returns {boolean}
 */
export function hasAllPermissions(userRole, permissions) {
  const perms = Array.isArray(permissions) ? permissions : [permissions];
  return perms.every((p) => hasPermission(userRole, p));
}

/**
 * Obtient la liste complète des permissions d'un rôle
 * @param {string} userRole
 * @returns {string[]}
 */
export function getRolePermissions(userRole) {
  return ROLE_PERMISSIONS[userRole] || [];
}

// ============================================
// ROUTE DEFINITIONS
// ============================================

const ROUTES = {
  dashboard: { label: "Dashboard", icon: "📊", path: "/dashboard" },
  platform: { label: "Plateforme", icon: "🌐", path: "/platform" },
  tenants: { label: "Tenants", icon: "🏢", path: "/tenant-management" },
  subscriptions: { label: "Abonnements", icon: "💳", path: "/subscriptions" },
  superadminAccounting: {
    label: "Comptabilité Globale",
    icon: "📈",
    path: "/superadmin-accounting",
  },
  paymentGateways: {
    label: "Passerelles Paiement",
    icon: "💰",
    path: "/payment-gateways",
  },
  monitoring: { label: "Monitoring", icon: "📊", path: "/monitoring" },
  systemLogs: { label: "Logs Système", icon: "📋", path: "/system-logs" },
  platformSettings: {
    label: "Plateforme",
    icon: "🌐",
    path: "/platform-settings",
  },
  paymentConfig: {
    label: "Paiements",
    icon: "💳",
    path: "/payment-configuration",
  },
  users: { label: "Utilisateurs", icon: "👤", path: "/users-management" },
  suppliers: { label: "Fournisseurs", icon: "🏭", path: "/suppliers" },
  customers: { label: "Clients", icon: "🧑‍💼", path: "/customers" },
  products: { label: "Produits", icon: "🏷️", path: "/products" },
  approvisionnement: {
    label: "Magasin",
    icon: "🛒",
    path: "/approvisionnement",
  },
  inventory: { label: "Inventaire", icon: "📋", path: "/inventory-enriched" },
  transfers: { label: "Transferts", icon: "🔄", path: "/transfers" },
  salesManager: { label: "Ventes", icon: "🛍️", path: "/pos/manager-orders" },
  salesManagerAlt: { label: "Ventes", icon: "🍽️", path: "/pos/manager-orders" },
  serverStock: { label: "Stock Serveurs", icon: "📤", path: "/server-stock" },
  accounting: { label: "Tableau de bord", icon: "📊", path: "/accounting" },
  immobilisations: {
    label: "Immobilisations",
    icon: "🏢",
    path: "/immobilisations",
  },
  rapprochement: {
    label: "Rapprochement Bancaire",
    icon: "🏦",
    path: "/rapprochement-bancaire",
  },
  grandLivre: { label: "Grand Livre", icon: "📖", path: "/grand-livre" },
  balance: { label: "Balance", icon: "⚖️", path: "/balance" },
  cashRegister: { label: "Caisse", icon: "🏧", path: "/cash-register" },
  expenses: { label: "Charges", icon: "💸", path: "/expense-tracking" },
  reports: { label: "Rapports", icon: "📄", path: "/reports" },
  tenantConfig: {
    label: "Configuration",
    icon: "🔧",
    path: "/tenant-configuration",
  },
  settings: { label: "Général", icon: "⚙️", path: "/settings" },
  journaux: { label: "Journaux", icon: "📚", path: "/journaux" },
  purchases: { label: "Achats", icon: "📦", path: "/purchases" },
  sales: { label: "Ventes", icon: "🛒", path: "/sales" },
  pos: { label: "Point de Vente", icon: "🛍️", path: "/pos" },
  posEncaissement: { label: "Encaissement", icon: "💳", path: "/pos" },
  myOrders: { label: "Mes Commandes", icon: "📋", path: "/pos/my-orders" },
  myStock: { label: "Mon Stock", icon: "📦", path: "/server-stock" },
  myCash: { label: "Ma Caisse", icon: "🏧", path: "/cash-register" },
  supplierPortal: {
    label: "Portail Fournisseur",
    icon: "🏭",
    path: "/supplier-portal",
  },
  accountingDashboard: {
    label: "Comptabilité",
    icon: "💰",
    path: "/accounting",
  },
  approvisionnementAlt: {
    label: "Approvisionnement",
    icon: "🏪",
    path: "/approvisionnement",
  },
};

// Helper to create menu group
const menuGroup = (label, icon, children) => ({
  label,
  icon,
  path: null,
  children,
});

// ============================================
// ROLE-SPECIFIC ROUTE CONFIGURATIONS
// ============================================

const ROLE_ROUTES = {
  [ROLES.SUPER_ADMIN]: [
    ROUTES.dashboard,
    ROUTES.platform,
    ROUTES.tenants,
    ROUTES.subscriptions,
    ROUTES.superadminAccounting,
    ROUTES.paymentGateways,
    ROUTES.monitoring,
    ROUTES.systemLogs,
    menuGroup("Paramètres", "⚙️", [
      ROUTES.platformSettings,
      ROUTES.paymentConfig,
    ]),
  ],

  [ROLES.OWNER]: [
    ROUTES.dashboard,
    menuGroup("Collaborateurs", "👥", [
      ROUTES.users,
      ROUTES.suppliers,
      ROUTES.customers,
    ]),
    menuGroup("Approvisionnement", "📦", [
      ROUTES.products,
      ROUTES.approvisionnement,
      ROUTES.inventory,
    ]),
    ROUTES.salesManager,
    menuGroup("Comptabilité", "💰", [
      ROUTES.accounting,
      ROUTES.immobilisations,
      ROUTES.rapprochement,
      ROUTES.grandLivre,
      ROUTES.balance,
    ]),
    menuGroup("Gestion Financière", "🏦", [
      ROUTES.cashRegister,
      ROUTES.expenses,
      ROUTES.reports,
    ]),
    menuGroup("Paramètres", "⚙️", [
      ROUTES.tenantConfig,
      ROUTES.paymentConfig,
      ROUTES.settings,
    ]),
  ],

  [ROLES.MANAGER]: [
    ROUTES.dashboard,
    menuGroup("Approvisionnement", "📦", [
      ROUTES.products,
      ROUTES.approvisionnement,
      ROUTES.inventory,
      ROUTES.transfers,
    ]),
    ROUTES.salesManagerAlt,
    ROUTES.serverStock,
    menuGroup("Gestion Financière", "🏦", [
      ROUTES.cashRegister,
      ROUTES.expenses,
      ROUTES.reports,
    ]),
  ],

  [ROLES.ACCOUNTANT]: [
    ROUTES.dashboard,
    ROUTES.journaux,
    ROUTES.grandLivre,
    ROUTES.balance,
    ROUTES.purchases,
    ROUTES.sales,
    ROUTES.expenses,
    ROUTES.immobilisations,
    { ...ROUTES.rapprochement, label: "Rapprochement" },
    ROUTES.reports,
  ],

  [ROLES.MAGASINIER_GROS]: [
    ROUTES.dashboard,
    ROUTES.approvisionnementAlt,
    ROUTES.inventory,
    ROUTES.suppliers,
  ],

  [ROLES.MAGASINIER_DETAIL]: [
    ROUTES.dashboard,
    ROUTES.approvisionnementAlt,
    ROUTES.inventory,
  ],

  [ROLES.CAISSIER]: [ROUTES.dashboard, ROUTES.posEncaissement, ROUTES.myCash],

  [ROLES.POS_SERVER]: [
    ROUTES.dashboard,
    ROUTES.pos,
    ROUTES.myOrders,
    ROUTES.myStock,
  ],

  [ROLES.AUDITOR]: [
    ROUTES.dashboard,
    ROUTES.reports,
    ROUTES.sales,
    ROUTES.purchases,
    ROUTES.accountingDashboard,
  ],

  [ROLES.SUPPLIER]: [ROUTES.supplierPortal],
};

// Admin uses same routes as Owner
ROLE_ROUTES[ROLES.ADMIN] = ROLE_ROUTES[ROLES.OWNER];

const DEFAULT_ROUTES = [ROUTES.dashboard];

/**
 * Obtient les routes accessibles selon le rôle
 * @param {string} userRole
 * @param {string} pos_option - "A" (standard) or "B" (delegated stock to servers)
 *   Option A: Standard flow - servers create sales, manager validates
 *   Option B: Delegated stock - servers manage their own stock allocation
 * @returns {array} Routes avec label, icon et children optionnel
 */
export function getAccessibleRoutes(userRole, pos_option = "A") {
  const baseRoutes = ROLE_ROUTES[userRole] || DEFAULT_ROUTES;

  // Option B: Add delegated stock routes for Manager and POS Server
  if (pos_option === "B") {
    if (userRole === ROLES.MANAGER) {
      // Manager already has serverStock in base routes
      return baseRoutes;
    }
    if (userRole === ROLES.POS_SERVER) {
      // POS Server already has myStock in base routes
      return baseRoutes;
    }
  }

  // Option A: Remove stock delegation routes
  if (pos_option === "A") {
    if (userRole === ROLES.MANAGER) {
      return baseRoutes.filter(
        (route) => route.path !== ROUTES.serverStock.path
      );
    }
    if (userRole === ROLES.POS_SERVER) {
      return baseRoutes.filter((route) => route.path !== ROUTES.myStock.path);
    }
  }

  return baseRoutes;
}

// ============================================
// ROLE METADATA
// ============================================

const ROLE_LABELS = {
  [ROLES.SUPER_ADMIN]: "Super Admin",
  [ROLES.OWNER]: "Propriétaire",
  [ROLES.ADMIN]: "Administrateur",
  [ROLES.MANAGER]: "Gérant",
  [ROLES.SUPPLIER]: "Fournisseur",
  [ROLES.ACCOUNTANT]: "Comptable",
  [ROLES.MAGASINIER_GROS]: "Magasinier Gros",
  [ROLES.MAGASINIER_DETAIL]: "Magasinier Détail",
  [ROLES.CAISSIER]: "Caissier",
  [ROLES.POS_SERVER]: "Serveur POS",
  [ROLES.AUDITOR]: "Auditeur",
};

const ROLE_COLORS = {
  [ROLES.SUPER_ADMIN]: "bg-red-600",
  [ROLES.OWNER]: "bg-purple-600",
  [ROLES.ADMIN]: "bg-purple-600",
  [ROLES.MANAGER]: "bg-orange-600",
  [ROLES.ACCOUNTANT]: "bg-yellow-600",
  [ROLES.MAGASINIER_GROS]: "bg-green-600",
  [ROLES.MAGASINIER_DETAIL]: "bg-green-500",
  [ROLES.CAISSIER]: "bg-blue-600",
  [ROLES.POS_SERVER]: "bg-blue-500",
  [ROLES.AUDITOR]: "bg-gray-600",
  [ROLES.SUPPLIER]: "bg-teal-600",
};

/**
 * Texte affichable du rôle
 * @param {string} userRole
 * @returns {string}
 */
export function getRoleLabel(userRole) {
  return ROLE_LABELS[userRole] || userRole;
}

/**
 * Couleur badge du rôle
 * @param {string} userRole
 * @returns {string}
 */
export function getRoleColor(userRole) {
  return ROLE_COLORS[userRole] || "bg-gray-400";
}

export default {
  ROLES,
  hasPermission,
  hasAnyPermission,
  hasAllPermissions,
  getRolePermissions,
  getAccessibleRoutes,
  getRoleLabel,
  getRoleColor,
};
