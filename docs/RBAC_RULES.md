# RBAC Rules Documentation

## Overview

SIGEC implements a comprehensive Role-Based Access Control (RBAC) system with 8 defined user roles, each with specific permissions tied to business processes.

---

## 🔵 Role: SUPER_ADMIN (Platform Host)

**Description:** Full platform access. Manages all tenants, configurations, and host operations.

**Accessible Pages:**
- Tenant Management (CRUD tenants)
- User Management (all users across all tenants)
- PSP Configuration (Fedapay/Kakiapay)
- System Monitoring & Logs
- Plan Management
- Impersonation

**Permissions:**
```
✓ tenant.create, tenant.view, tenant.edit, tenant.delete, tenant.suspend
✓ plan.create, plan.view, plan.edit
✓ psp.configure, psp.webhooks
✓ system.monitor, system.logs, system.impersonate
✓ user.create, user.view, user.edit, user.delete, user.assign_role
✓ tenant_settings.edit, tenant_settings.view
✓ purchase.view, sale.view, stock.view
✓ report.view, report.export
✓ accounting.view
```

**Hidden Menus:**
- Point de Vente (POS)
- Products (view-only for monitoring)

---

## 🟣 Role: OWNER (Tenant Proprietor)

**Description:** Complete access to tenant. Can manage users, configure settings, approve all operations.

**Accessible Pages:**
- Dashboard (full analytics)
- Purchases (create, receive, manage)
- Sales (create, complete, discount)
- Inventory (create, manage)
- Transfers (create, approve)
- Products (full CRUD)
- Chart of Accounts (view/configure)
- Reports (all types)
- Accounting (GL view/edit)
- Expenses (create, manage)
- Users (CRUD + role assignment)
- Settings (configure tenant + PSP)

**Permissions:**
```
✓ user.create, user.view, user.edit, user.delete, user.assign_role
✓ tenant_settings.edit, tenant_settings.view
✓ purchase.create, purchase.view, purchase.edit, purchase.receive, purchase.cancel
✓ supplier.manage, supplier.view
✓ sale.create, sale.view, sale.complete, sale.cancel, sale.discount
✓ stock.view, stock.adjust, stock.transfer, stock.approve_transfer
✓ inventory.create, inventory.complete, inventory.view
✓ warehouse.manage, warehouse.view
✓ product.manage, product.view, product.create, product.edit, product.delete
✓ accounting.view, accounting.edit, accounting.post
✓ report.view, report.export
✓ expense.create, expense.view, expense.edit, expense.delete
✓ payment.process, payment.verify, payment.view
```

**Hidden Menus:**
- Tenant Management (not shown, super_admin only)
- System settings (not shown)

---

## 🔶 Role: MANAGER (Operational Lead)

**Description:** Day-to-day operations management. Can execute all operational tasks but cannot modify users or system settings.

**Accessible Pages:**
- Dashboard (operational KPIs)
- Purchases (receive goods)
- Sales (process/validate)
- Inventory (view/manage)
- Transfers (create/approve)
- Stock (view/adjust)
- Products (view)
- Reports (summary reports)

**Disabled Actions:**
- Cannot modify user roles
- Cannot edit tenant settings
- Cannot modify PSP configuration
- Cannot post GL entries
- Cannot create users

**Permissions:**
```
✓ purchase.create, purchase.view, purchase.edit, purchase.receive, purchase.cancel
✓ supplier.view
✓ sale.view, sale.complete
✓ stock.view, stock.adjust, stock.transfer, stock.approve_transfer
✓ inventory.view
✓ warehouse.view
✓ product.view
✓ report.view
✓ expense.view
```

**Hidden Menus:**
- Collaborateurs (Users) - not shown
- Paramètres (Settings) - not shown
- Tenant management
- Chart of Accounts
- Accounting module

---

## 🟡 Role: ACCOUNTANT (Finance)

**Description:** Accounting and financial reporting. Read-only on operational data, full access to GL and reports.

**Accessible Pages:**
- Dashboard (financial indicators only)
- Reports (all: journals, P&L, trial balance)
- Accounting (GL view + post adjustments)
- Expenses (create/manage)
- Sales/Purchases (view only)
- Payments (view only)

**Disabled Actions:**
- Cannot modify stock
- Cannot create sales/purchases
- Cannot modify users
- Cannot change tenant settings

**Permissions:**
```
✓ sale.view, purchase.view
✓ accounting.view, accounting.edit
✓ report.view, report.export
✓ expense.create, expense.view, expense.edit
✓ payment.view
```

**Hidden Menus:**
- Point de Vente (POS)
- Produits (Products)
- Inventaire (Inventory)
- Transferts (Transfers)
- Approvisionnement (Purchase order creation)
- Collaborateurs
- Paramètres

---

## 🟢 Role: WAREHOUSE / MAGASINIER

**Description:** Warehouse operations. Manage physical stock, receptions, transfers, inventory counts.

**Sub-Types:**
- Magasinier Gros (Bulk warehouse)
- Magasinier Détail (Retail warehouse)

**Accessible Pages:**
- Dashboard (stock alerts only)
- Purchases (receive only - no create)
- Stock (view + adjust)
- Inventory (create counts, complete)
- Transfers (execute transfers)
- Products (view)

**Disabled Actions:**
- Cannot create purchases (only receive existing ones)
- Cannot create sales
- Cannot access accounting
- Cannot modify users
- Cannot see pricing/margins

**Permissions:**
```
✓ purchase.receive
✓ stock.view, stock.adjust, stock.transfer
✓ inventory.create, inventory.complete, inventory.view
✓ warehouse.view
✓ product.view
```

**Hidden Menus:**
- Point de Vente (POS)
- Rapports (Reports)
- Plan Comptable (CoA)
- Collaborateurs
- Paramètres

---

## 🟤 Role: CASHIER (CAISSIER)

**Description:** POS payment processing. Encash payments, manage cash register, validate transactions.

**Accessible Pages:**
- Dashboard (cash register status)
- Point de Vente (POS encashment only)
- Payments (process + verify)
- Sales (view only)

**Disabled Actions:**
- Cannot modify stock
- Cannot modify prices
- Cannot create transfers
- Cannot modify users
- Cannot access accounting

**Permissions:**
```
✓ sale.view
✓ pos.access, pos.close_session
✓ stock.view
✓ payment.process, payment.verify, payment.view
```

**Hidden Menus:**
- All except POS and Payments

---

## ⚪ Role: POS_SERVER (SERVEUR POS)

**Description:** Point of Sale transactions. Create sales, apply discounts, process simple payments.

**Accessible Pages:**
- Dashboard (ultra-simplified)
- Point de Vente (create sales only)
- Payments (simple payment processing)
- Products (view to ring up)

**Disabled Actions:**
- Cannot access transfers
- Cannot access inventory
- Cannot access accounting
- Cannot modify users
- Cannot close POS session (cashier does)
- Cannot apply advanced discounts (manager only)

**Permissions:**
```
✓ sale.create, sale.view, sale.discount
✓ pos.access
✓ stock.view
✓ product.view
✓ payment.process
```

**Hidden Menus:**
- Rapports (Reports)
- Plan Comptable
- Inventaire
- Approvisionnement
- Collaborateurs
- Paramètres

---

## 🔍 Role: AUDITOR / CONSULTANT (READ-ONLY)

**Description:** Analysis and audit only. Full read access, NO write/delete permissions.

**Accessible Pages:**
- Dashboard (read-only)
- Reports (all reports)
- Accounting (GL view only)
- Sales history (view only)
- Purchases history (view only)
- Expenses (view only)
- Payments (view only)

**Disabled Actions:**
- Cannot create anything
- Cannot modify anything
- Cannot delete anything
- Cannot process payments

**Permissions:**
```
✓ sale.view, purchase.view
✓ stock.view
✓ accounting.view
✓ report.view, report.export
✓ expense.view
✓ payment.view
```

**Hidden Menus:**
- Point de Vente
- All management pages (Users, Settings)
- All creation pages (products, etc)

---

## 🧩 Permission Matrix

| Feature | Super Admin | Owner | Manager | Accountant | Warehouse | Cashier | POS Server | Auditor |
|---------|:-----------:|:-----:|:-------:|:----------:|:---------:|:-------:|:----------:|:-------:|
| Dashboard | ✓ Full | ✓ Full | ✓ Ops | ✓ Finance | ✓ Stock | ✓ Cash | ✓ Simple | ✓ RO |
| Purchases | ✓ RW | ✓ RW | ✓ RW | ✓ RO | ✓ Receive | ✗ | ✗ | ✓ RO |
| Sales | ✓ RO | ✓ RW | ✓ RO | ✓ RO | ✗ | ✗ | ✓ Create | ✓ RO |
| Stock | ✓ RO | ✓ RW | ✓ RW | ✗ | ✓ RW | ✓ RO | ✓ RO | ✓ RO |
| Inventory | ✓ RO | ✓ RW | ✓ RO | ✗ | ✓ RW | ✗ | ✗ | ✓ RO |
| Accounting | ✓ RO | ✓ RW | ✗ | ✓ RW | ✗ | ✗ | ✗ | ✓ RO |
| Reports | ✓ RW | ✓ RW | ✓ RO | ✓ RW | ✗ | ✗ | ✗ | ✓ RO |
| Payments | ✓ RO | ✓ RW | ✗ | ✓ RO | ✗ | ✓ RW | ✓ RW | ✓ RO |
| POS | ✗ | ✓ RW | ✗ | ✗ | ✗ | ✓ Cash | ✓ Create | ✗ |
| Users | ✓ RW | ✓ RW | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Settings | ✓ RW | ✓ RW | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| PSP Config | ✓ RW | ✓ RW | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |

**Legend:** ✓ = Allowed | ✗ = Denied | RW = Read/Write | RO = Read Only

---

## 🔐 Middleware Usage

All API routes use permission middleware:

```php
Route::middleware(['auth:sanctum', 'permission:sale.create'])
     ->post('/sales', [SaleController::class, 'store']);
```

---

## 🛠️ Implementation

**Check Permission in Controller:**
```php
if (!AuthorizationService::can(auth()->user(), 'sale.create')) {
    return response()->json(['error' => 'Forbidden'], 403);
}
```

**Check Ownership:**
```php
if (!AuthorizationService::owns(auth()->user(), $sale)) {
    return response()->json(['error' => 'Not authorized'], 403);
}
```

---

## 📋 Frontend Implementation

### Dynamic Menu Rendering
Menus render based on `user.role`:
```jsx
const getMenuItems = () => {
  if (user.role === 'super_admin') return [...allItems];
  if (user.role === 'owner') return [...ownerItems];
  if (user.role === 'warehouse') return [...warehouseItems];
  // etc
}
```

### Feature Flags
```jsx
{user.can('user.create') && <Link to="/users">Collaborateurs</Link>}
```

---

## 📝 Notes

- Roles are **system roles** (cannot be modified by users)
- Custom roles can be implemented in future versions
- Permissions are checked at both **middleware** (API) and **UI** (frontend) levels
- Multi-tenant isolation: Users can only see their own tenant's data
- Super Admin can impersonate any user for troubleshooting

---

**Last Updated:** 2024-11-24
**Status:** RBAC fully implemented and deployed
