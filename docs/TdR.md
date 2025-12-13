# TdR - SIGEC Spécifications Complètes

## 1. Résumé Exécutif

**SIGEC** (Système Intégré de Gestion Efficace et de la Comptabilité) est une plateforme SaaS multitenant destín aux entreprises de restauration et commerce. Elle offre :

- **Mode POS configurable** : Option A (centralisé) ou Option B (avec stock)
- **Gestion multi-entrepôt** : Gros → Détail → POS
- **Comptabilité automatisée** : Écritures générées automatiquement
- **POS offline** : Synchronisation automatique en ligne
- **RBAC complet** : 8 rôles avec permissions granulaires

## 2. Rôles & Permissions (RBAC)

### SuperAdmin (Host)
- Tout : créer/suspendre/restaurer tenants

### Owner (Tenant)
- Config entreprise + rapports financiers

### Gérant
- Stocks, transferts, réceptions, inventaire

### Magasinier
- Réceptions, transfers, inventaire

### Caissier
- Sessions caisse, paiements, réconciliation

### Comptable
- Journal comptable, exports, clôtures

### Serveur/Vendeur (POS)
- Prise commande, encaissement, ticket

### Support
- Visualisation + intervention

## 3. Mode POS : Option A vs Option B

### Option A - POS Centralisé (Défaut)
- POS = point de prise de commande seulement
- **Pas de stock POS**
- Commandes → Magasin Détail → Préparation → Remise

### Option B - POS avec Stock
- Chaque POS a **mini-stock propre**
- Transferts gros→détail→POS programmables

## 4. Opérations Automatiques

- ✅ CMP : Recalcul à chaque réception
- ✅ Déduction stock : À validation vente
- ✅ Comptabilité : Écritures auto  ventes/achats/transferts
- ✅ Marges : Calculées par ligne
- ✅ Alertes : Email/SMS seuils
- ✅ Facturation : Mensuelle + retry
- ✅ Backups : Quotidiennes
- ✅ Sync POS : Reconciliation offline → online

## 5. Flux Métier

### Vente Option A
```
Client → Serveur saisit POS → Détail prépare → Remise → Paiement → Écritures
```

### Vente Option B  
```
Détail transfer → POS stock → Vente immédiate → Sync
```

### Approvisionnement
```
Magasin gros crée PO → Réception (qty + cost) → CMP update → Auto-transfer gros→détail
```

## 6. Schéma BD (Tables Clés)

| Table | Colonnes |
|-------|----------|
| tenants | id, name, mode_pos (A\|B) |
| warehouses | id, tenant_id, type (gros\|detail\|pos) |
| products | id, tenant_id, sku, price_sale |
| stocks | id, tenant_id, product_id, warehouse_id, qty, cost_average |
| stock_movements | id, type (purchase\|transfer\|sale), qty, unit_cost |
| sales | id, pos_id, total_amount, status |
| purchases | id, supplier_id, status |
| transfers | id, from_warehouse_id, to_warehouse_id, status |
| accounting_entries | id, debit_account, credit_account, amount |

## 7. Services Métier

- **StockService** : addEntry, transfer, withdraw, recalcCMP
- **SaleService** : createSale, validateStock, autoTransferIfNeeded
- **PurchaseService** : createPO, receivePO
- **TransferService** : request, approve, execute
- **AccountingService** : generateEntriesForSale, For Purchase, ForTransfer
- **InventoryService** : recordCount, adjust
- **BillingService** : createInvoice, retryPayment
- **BackupService** : snapshotTenant, restoreTenant

## 8. API Endpoints (Résumé)

```
AUTH
POST /api/auth/register
POST /api/auth/login
GET /api/auth/me

PRODUCTS
GET /api/products
POST /api/products

STOCKS
GET /api/warehouses
POST /api/stocks/transfer
GET /api/stock/{product_id}

PURCHASES
POST /api/purchases
POST /api/purchases/{id}/receive

SALES
POST /api/sales
POST /api/sales/{id}/payment

ACCOUNTING
GET /api/accounting/journal
POST /api/accounting/export
```

**Header requis** : `X-Tenant-ID: {id}`

## 9. Critères Acceptation

- ✅ Onboarding : Mode POS A/B selection
- ✅ PO → Réception : CMP correct, stock ↑
- ✅ Transfert gros→détail : mouvements + écritures
- ✅ Vente A : remise détail, déduction
- ✅ Vente B : déduction POS immédiate
- ✅ Inventaire : ajustement + écritures
- ✅ Backup/restore : données identiques
- ✅ Exports : Excel/Word/PDF OK

---

Version 1.0.0 | Novembre 2024
