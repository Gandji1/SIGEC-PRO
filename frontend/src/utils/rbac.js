/**
 * RBAC Utilities
 * Gère les permissions et rôles utilisateur
 */

// Mappage rôles → permissions
const ROLE_PERMISSIONS = {
  super_admin: [
    // ✅ SUPER ADMIN = ACCÈS TOTAL À TOUT
    // Host management
    'platform.view', 'tenants.list', 'tenants.create', 'tenants.edit', 'tenants.delete',
    'tenants.suspend', 'tenants.impersonate', 'plans.manage', 'psp.manage', 'psp.webhook',
    'backups.manage', 'migrations.run', 'settings.global', 'logs.view', 'users.reset-password',
    // Tenant features
    'tenant.view', 'tenant.edit', 'users.list', 'users.create', 'users.edit', 'users.delete',
    'roles.assign', 'warehouses.manage', 'suppliers.manage', 'customers.manage', 'purchases.manage',
    'sales.manage', 'transfers.manage', 'stocks.view', 'stocks.adjust', 'inventories.manage',
    'accounting.view', 'accounting.post', 'accounting.close-period', 'charges.manage', 'charges.create',
    'charges.edit', 'reports.view', 'reports.export', 'audit.view', 'psp.delegate',
    // Dashboards
    'dashboard.manager', 'dashboard.accounting', 'dashboard.warehouse', 'dashboard.pos',
    'dashboard.audit', 'dashboard.cashier',
    // Additional tenant operations
    'purchases.list', 'purchases.create', 'purchases.receive', 'sales.list', 'sales.validate',
    'sales.view', 'pos.supervise', 'inventories.validate', 'pos.payments', 'pos.close-session',
    'pos.create-sale', 'pos.apply-discount', 'pos.view-history', 'pos.prepare', 'stocks.move'
  ],

  owner: [
    // ✅ TENANT (Propriétaire) - Consultation + Approbation commandes fournisseurs
    // ❌ Ne passe PAS de commande fournisseur, ne valide PAS les ventes
    // ✅ Approuve les commandes du Gérant AVANT envoi au fournisseur
    'tenant.view', 'tenant.edit', 'users.list', 'users.create', 'users.edit', 'users.delete',
    'roles.assign', 'warehouses.manage', 
    'suppliers.list', 'suppliers.create', 'suppliers.edit', 'suppliers.delete', // Gère les fournisseurs
    'customers.list', 'customers.create', 'customers.edit', 'customers.delete',
    'purchases.list', 'purchases.approve', 'purchases.view', // Approuve les commandes du gérant
    'sales.list', 'sales.view', // Consultation ventes uniquement
    'transfers.list', 'transfers.view',
    'stocks.view',
    'inventories.list', 'inventories.view',
    'accounting.view', 'accounting.post', 'accounting.close-period', 'accounting.export',
    'charges.list', 'charges.view',
    'reports.view', 'reports.export', 'audit.view', 'psp.delegate',
    'cash.view' // Consultation caisse
  ],

  admin: [  // Alias for owner (for backward compatibility)
    'tenant.view', 'tenant.edit', 'users.list', 'users.create', 'users.edit', 'users.delete',
    'roles.assign', 'warehouses.manage', 
    'suppliers.list', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
    'customers.list', 'customers.create', 'customers.edit', 'customers.delete',
    'purchases.list', 'purchases.approve', 'purchases.view',
    'sales.list', 'sales.view',
    'transfers.list', 'transfers.view',
    'stocks.view',
    'inventories.list', 'inventories.view',
    'accounting.view', 'accounting.post', 'accounting.close-period', 'accounting.export',
    'charges.list', 'charges.view',
    'reports.view', 'reports.export', 'audit.view', 'psp.delegate',
    'cash.view'
  ],

  manager: [
    // ✅ GÉRANT - Opérationnel complet
    // ✅ Seul à créer commandes fournisseurs (soumises au Tenant pour approbation)
    // ✅ Seul à approuver/servir/valider les ventes des serveurs
    // ✅ Gère caisse, stock, transferts
    // ❌ Pas d'accès aux Fournisseurs dans Collaborateurs
    'dashboard.manager', 
    'purchases.list', 'purchases.create', 'purchases.receive', // Crée et réceptionne
    'sales.list', 'sales.view',
    'pos_orders.manage', 'pos_orders.approve', 'pos_orders.serve', 'pos_orders.validate', // Valide ventes serveurs
    'stocks.view', 'stocks.adjust', 'stocks.move',
    'transfers.list', 'transfers.create', 'transfers.approve',
    'inventories.list', 'inventories.manage',
    'charges.list', 'charges.create',
    'cash.manage', 'cash.view', // Gère la caisse
    'reports.view', 'audit.view'
  ],

  accountant: [
    'tenant.view', 'dashboard.accounting', 'sales.list', 'purchases.list',
    'accounting.view', 'accounting.post', 'accounting.close-period', 'accounting.export',
    'charges.list', 'charges.create', 'charges.edit', 'reports.view', 'reports.export', 'audit.view'
  ],

  magasinier_gros: [
    'tenant.view', 'dashboard.warehouse', 'purchases.list', 'purchases.receive', 'stocks.view',
    'stocks.move', 'transfers.create', 'transfers.approve', 'inventories.list', 'inventories.participate'
  ],

  magasinier_detail: [
    'tenant.view', 'dashboard.warehouse', 'stocks.view', 'stocks.move', 'transfers.list',
    'transfers.receive', 'pos.prepare', 'inventories.list', 'inventories.participate'
  ],

  caissier: [
    'tenant.view', 'dashboard.cashier', 'pos.payments', 'pos.close-session', 'sales.view'
  ],

  pos_server: [
    // ✅ SERVEUR - Crée des ventes uniquement
    // ❌ Ne peut PAS approuver, servir, valider (tout va au Gérant)
    // ❌ Pas d'accès fournisseurs ni magasin
    'tenant.view', 'dashboard.pos', 
    'pos.create-sale', 'pos.view-history', // Crée ventes, voit son historique
    'sales.view' // Voit uniquement SES ventes
  ],

  auditor: [
    'tenant.view', 'dashboard.audit', 'sales.list', 'purchases.list', 'stocks.view',
    'accounting.view', 'reports.view', 'audit.view', 'charges.list'
  ],

  // Fournisseur externe (portail fournisseur)
  supplier: [
    // ✅ FOURNISSEUR - Reçoit commandes approuvées par Tenant
    // Flux: Reçoit → Approuve → Sert → Livrer
    'supplier.dashboard', 
    'supplier.orders.view', 'supplier.orders.confirm', 'supplier.orders.ship', 'supplier.orders.deliver',
    'supplier.history' // Historique des livraisons
  ]
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
  return perms.some(p => hasPermission(userRole, p));
}

/**
 * Vérifie si un utilisateur a TOUTES les permissions listées
 * @param {string} userRole
 * @param {string|string[]} permissions
 * @returns {boolean}
 */
export function hasAllPermissions(userRole, permissions) {
  const perms = Array.isArray(permissions) ? permissions : [permissions];
  return perms.every(p => hasPermission(userRole, p));
}

/**
 * Obtient la liste complète des permissions d'un rôle
 * @param {string} userRole
 * @returns {string[]}
 */
export function getRolePermissions(userRole) {
  return ROLE_PERMISSIONS[userRole] || [];
}

/**
 * Obtient les routes accessibles selon le rôle
 * Structure hiérarchique avec parents/enfants pour une navigation fluide
 * @param {string} userRole
 * @returns {array} Routes avec label, icon et children optionnel
 */
export function getAccessibleRoutes(userRole) {
  
  // ========================================
  // SUPER ADMIN - Gestion plateforme
  // ========================================
  const superAdminRoutes = [
    { label: 'Dashboard', icon: '📊', path: '/dashboard' },
    { label: 'Plateforme', icon: '🌐', path: '/platform' },
    { label: 'Tenants', icon: '🏢', path: '/tenant-management' },
    { label: 'Abonnements', icon: '💳', path: '/subscriptions' },
    { label: 'Comptabilité Globale', icon: '📈', path: '/superadmin-accounting' },
    { label: 'Passerelles Paiement', icon: '💰', path: '/payment-gateways' },
    { label: 'Monitoring', icon: '📊', path: '/monitoring' },
    { label: 'Logs Système', icon: '📋', path: '/system-logs' },
    { 
      label: 'Paramètres', icon: '⚙️', path: null,
      children: [
        { label: 'Plateforme', icon: '🌐', path: '/platform-settings' },
        { label: 'Paiements', icon: '💳', path: '/payment-configuration' },
      ]
    },
  ];

  // ========================================
  // OWNER / ADMIN (Tenant/Propriétaire)
  // ✅ Consultation stocks, ventes, tableaux de bord, caisse
  // ✅ Approuve commandes du Gérant avant envoi fournisseur
  // ❌ Ne passe PAS de commande, ne valide PAS les ventes
  // ========================================
  const ownerRoutes = [
    { label: 'Dashboard', icon: '📊', path: '/dashboard' },
    
    // Parent: Collaborateurs (utilisateurs, fournisseurs, clients)
    // ✅ Fournisseurs visible uniquement pour le Tenant (pas le Gérant)
    { 
      label: 'Collaborateurs', icon: '👥', path: null,
      children: [
        { label: 'Utilisateurs', icon: '👤', path: '/users-management' },
        { label: 'Fournisseurs', icon: '🏭', path: '/suppliers' },
        { label: 'Clients', icon: '🧑‍💼', path: '/customers' },
      ]
    },
    
    // Parent: Approvisionnement
    { 
      label: 'Approvisionnement', icon: '📦', path: null,
      children: [
        { label: 'Produits', icon: '🏷️', path: '/products' },
        { label: 'Magasin', icon: '🛒', path: '/approvisionnement' },
        { label: 'Inventaire', icon: '📋', path: '/inventory-enriched' },
      ]
    },
    
    // Ventes (consultation uniquement)
    { label: 'Ventes', icon: '🛍️', path: '/pos/manager-orders' },
    
    // Parent: Comptabilité
    { 
      label: 'Comptabilité', icon: '💰', path: null,
      children: [
        { label: 'Tableau de bord', icon: '📊', path: '/accounting' },
        { label: 'Immobilisations', icon: '🏢', path: '/immobilisations' },
        { label: 'Rapprochement Bancaire', icon: '🏦', path: '/rapprochement-bancaire' },
        { label: 'Grand Livre', icon: '📖', path: '/grand-livre' },
        { label: 'Balance', icon: '⚖️', path: '/balance' },
      ]
    },
    
    // Parent: Gestion Financière
    { 
      label: 'Gestion Financière', icon: '🏦', path: null,
      children: [
        { label: 'Caisse', icon: '🏧', path: '/cash-register' },
        { label: 'Charges', icon: '💸', path: '/expense-tracking' },
        { label: 'Rapports', icon: '📄', path: '/reports' },
      ]
    },
    
    // Parent: Paramètres
    { 
      label: 'Paramètres', icon: '⚙️', path: null,
      children: [
        { label: 'Configuration', icon: '🔧', path: '/tenant-configuration' },
        { label: 'Paiements', icon: '💳', path: '/payment-configuration' },
        { label: 'Général', icon: '⚙️', path: '/settings' },
      ]
    },
  ];

  // ========================================
  // MANAGER (Gérant) - Gestion opérationnelle
  // ✅ Seul émetteur des commandes vers fournisseurs
  // ✅ Reçoit toutes les ventes des Serveurs
  // ✅ Seul à approuver/servir/valider les ventes
  // ✅ Gère caisse, stock, transferts, approvisionnements
  // ✅ Option B: Délègue stock aux serveurs
  // ❌ Pas d'accès aux Fournisseurs dans Collaborateurs
  // ❌ Pas d'accès aux Paramètres (Configuration, Paiements, Général)
  // ========================================
  const managerRoutes = [
    { label: 'Dashboard', icon: '📊', path: '/dashboard' },
    
    // Parent: Approvisionnement
    { 
      label: 'Approvisionnement', icon: '📦', path: null,
      children: [
        { label: 'Produits', icon: '🏷️', path: '/products' },
        { label: 'Magasin', icon: '🛒', path: '/approvisionnement' },
        { label: 'Inventaire', icon: '📋', path: '/inventory-enriched' },
        { label: 'Transferts', icon: '🔄', path: '/transfers' },
      ]
    },
    
    // Ventes - Approuver/Servir/Valider les commandes des serveurs
    { label: 'Ventes', icon: '🍽️', path: '/pos/manager-orders' },
    
    // Option B: Stock Délégué aux Serveurs
    { label: 'Stock Serveurs', icon: '📤', path: '/server-stock' },
    
    // Parent: Gestion Financière
    { 
      label: 'Gestion Financière', icon: '🏦', path: null,
      children: [
        { label: 'Caisse', icon: '🏧', path: '/cash-register' },
        { label: 'Charges', icon: '💸', path: '/expense-tracking' },
        { label: 'Rapports', icon: '📄', path: '/reports' },
      ]
    },
  ];

  // ========================================
  // ACCOUNTANT (Comptable)
  // ========================================
  const accountantRoutes = [
    { label: 'Dashboard', icon: '📊', path: '/dashboard' },
    { label: 'Journaux', icon: '📚', path: '/journaux' },
    { label: 'Grand Livre', icon: '📖', path: '/grand-livre' },
    { label: 'Balance', icon: '⚖️', path: '/balance' },
    { label: 'Achats', icon: '📦', path: '/purchases' },
    { label: 'Ventes', icon: '🛒', path: '/sales' },
    { label: 'Charges', icon: '💸', path: '/expense-tracking' },
    { label: 'Immobilisations', icon: '🏢', path: '/immobilisations' },
    { label: 'Rapprochement', icon: '🏦', path: '/rapprochement-bancaire' },
    { label: 'Rapports', icon: '📄', path: '/reports' },
  ];

  // ========================================
  // MAGASINIER GROS
  // ========================================
  const magasinierGrosRoutes = [
    { label: 'Dashboard', icon: '📊', path: '/dashboard' },
    { label: 'Approvisionnement', icon: '🏪', path: '/approvisionnement' },
    { label: 'Inventaire', icon: '📋', path: '/inventory-enriched' },
    { label: 'Fournisseurs', icon: '🏭', path: '/suppliers' },
  ];

  // ========================================
  // MAGASINIER DETAIL
  // ========================================
  const magasinierDetailRoutes = [
    { label: 'Dashboard', icon: '📊', path: '/dashboard' },
    { label: 'Approvisionnement', icon: '🏪', path: '/approvisionnement' },
    { label: 'Inventaire', icon: '📋', path: '/inventory-enriched' },
  ];

  // ========================================
  // CAISSIER
  // ========================================
  const caissierRoutes = [
    { label: 'Dashboard', icon: '📊', path: '/dashboard' },
    { label: 'Encaissement', icon: '💳', path: '/pos' },
    { label: 'Ma Caisse', icon: '🏧', path: '/cash-register' },
  ];

  // ========================================
  // SERVEUR POS - Crée des ventes uniquement
  // ✅ Peut créer des ventes clients
  // ✅ Voit uniquement SES ventes initiées
  // ✅ Option B: Gère son stock délégué et fait le point
  // ❌ Ne peut PAS approuver, servir, valider (tout va au Gérant)
  // ❌ Pas d'accès fournisseurs ni magasin
  // ========================================
  const posServerRoutes = [
    { label: 'Dashboard', icon: '📊', path: '/dashboard' },
    { label: 'Point de Vente', icon: '🛍️', path: '/pos' },
    { label: 'Mes Commandes', icon: '📋', path: '/pos/my-orders' },
    { label: 'Mon Stock', icon: '📦', path: '/server-stock' },
  ];

  // ========================================
  // AUDITOR
  // ========================================
  const auditorRoutes = [
    { label: 'Dashboard', icon: '📊', path: '/dashboard' },
    { label: 'Rapports', icon: '📄', path: '/reports' },
    { label: 'Ventes', icon: '🛒', path: '/sales' },
    { label: 'Achats', icon: '📦', path: '/purchases' },
    { label: 'Comptabilité', icon: '💰', path: '/accounting' },
  ];

  // ========================================
  // SUPPLIER (Fournisseur externe)
  // ========================================
  const supplierRoutes = [
    { label: 'Portail Fournisseur', icon: '🏭', path: '/supplier-portal' },
  ];

  // Mapping rôle -> routes
  const roleRoutes = {
    super_admin: superAdminRoutes,
    owner: ownerRoutes,
    admin: ownerRoutes,  // Alias
    manager: managerRoutes,
    accountant: accountantRoutes,
    magasinier_gros: magasinierGrosRoutes,
    magasinier_detail: magasinierDetailRoutes,
    caissier: caissierRoutes,
    pos_server: posServerRoutes,
    auditor: auditorRoutes,
    supplier: supplierRoutes,
  };

  return roleRoutes[userRole] || [{ label: 'Dashboard', icon: '📊', path: '/dashboard' }];
}

/**
 * Texte affichable du rôle
 * @param {string} userRole
 * @returns {string}
 */
export function getRoleLabel(userRole) {
  const labels = {
    super_admin: 'Super Admin',
    owner: 'Propriétaire',
    manager: 'Gérant',
    supplier: 'Fournisseur',
    accountant: 'Comptable',
    magasinier_gros: 'Magasinier Gros',
    magasinier_detail: 'Magasinier Détail',
    caissier: 'Caissier',
    pos_server: 'Serveur POS',
    auditor: 'Auditeur'
  };
  return labels[userRole] || userRole;
}

/**
 * Couleur badge du rôle
 * @param {string} userRole
 * @returns {string}
 */
export function getRoleColor(userRole) {
  const colors = {
    super_admin: 'bg-red-600',
    owner: 'bg-purple-600',
    manager: 'bg-orange-600',
    accountant: 'bg-yellow-600',
    magasinier_gros: 'bg-green-600',
    magasinier_detail: 'bg-green-500',
    caissier: 'bg-blue-600',
    pos_server: 'bg-blue-500',
    auditor: 'bg-gray-600'
  };
  return colors[userRole] || 'bg-gray-400';
}

export default {
  hasPermission,
  hasAnyPermission,
  hasAllPermissions,
  getRolePermissions,
  getAccessibleRoutes,
  getRoleLabel,
  getRoleColor
};
