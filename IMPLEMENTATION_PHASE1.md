# 🚀 SIGEC - Plan d'Exécution Détaillé (Phase 1 Complétée)

**Date:** 23 Novembre 2025  
**Phase:** 1 - Core Consolidation (EN COURS)  
**Status:** ✅ 60% Complété

---

## 📋 CHECKPOINTS COMPLÉTÉS (Phase 1)

### ✅ Migrations Créées (7 nouvelles)
- [x] `2024_01_01_000019_create_warehouses_table.php` - Gestion entrepôts (gros/détail/pos)
- [x] `2024_01_01_000020_create_stock_movements_table.php` - Audit trail stock
- [x] `2024_01_01_000021_create_inventories_table.php` - Inventaires physiques
- [x] `2024_01_01_000022_create_inventory_items_table.php` - Items inventaire
- [x] `2024_01_01_000023_create_invoices_table.php` - Factures SaaS
- [x] `2024_01_01_000024_create_subscription_plans_table.php` - Plans d'abonnement
- [x] `2024_01_01_000025_create_subscriptions_table.php` - Souscriptions tenants
- [x] `2024_01_01_000026_create_exports_table.php` - Tracking exports

**Total:** 24 migrations (17 existantes + 7 nouvelles = COMPLET)

### ✅ Modèles Créés (7 nouveaux)
- [x] `Warehouse.php` - Relations vers stocks, mouvements, inventaires
- [x] `StockMovement.php` - Audit trail avec factory methods
- [x] `Inventory.php` - Gestion inventaires physiques
- [x] `InventoryItem.php` - Items avec calcul écarts
- [x] `SubscriptionPlan.php` - Définition plans
- [x] `Subscription.php` - Souscription tenant avec cycle de vie
- [x] `Invoice.php` - Facturation SaaS avec statuts
- [x] `Export.php` - Tracking exports avec URLs signées

**Améliorations:**
- [x] Modèle `Stock` enrichi avec `warehouse_id`, `cost_average`, relations `StockMovement`

**Total:** 23 modèles (16 existants + 7 nouveaux = COMPLET)

### ✅ Services Métier Créés/Améliorés (3 nouveaux)
- [x] `TransferService.php` - Transferts inter-warehouse avec auto-transfer et comptabilité
- [x] `InventoryService.php` - Inventaires avec import/export CSV, ajustements auto
- [x] `AccountingPostingService.php` - Génération écritures comptables avec CMP

**Fonctionnalités:**
- [x] Transferts validés avec vérification stock
- [x] Transferts automatiques si seuil bas
- [x] Mouvements de stock tracés
- [x] Inventaires avec détection écarts
- [x] Importation CSV inventaire
- [x] Génération écritures sales/purchases/adjustments
- [x] Calcul CMP (Coût Moyen Pondéré)
- [x] Grand Livre et Balance de Vérification

---

## 📝 TÂCHES RESTANTES (Phase 1)

### 🔴 CRITIQUES (Blockers)

#### 1. Mettre à jour les Listeners existants ⚠️
**Fichier:** `app/Listeners/RecordSaleAuditLog.php`
**Action requise:**
```php
// Ajouter l'appel à AccountingPostingService
use App\Domains\Accounting\Services\AccountingPostingService;

public function handle(SaleCompleted $event): void
{
    $sale = $event->sale;
    $service = new AccountingPostingService();
    
    // Générer les écritures comptables
    $service->generateSaleEntries(
        $sale->tenant_id,
        $sale->id,
        $sale->total,
        $sale->items->sum(fn($item) => $item->unit_cost * $item->quantity),
        "SALE-{$sale->reference}"
    );
}
```

#### 2. Mettre à jour Listeners Purchases & Stock ⚠️
**Fichier:** `app/Listeners/RecordPurchaseAuditLog.php`
**Action requise:**
```php
// Ajouter CMP calculation lors réception achat
$cmp = AccountingPostingService::calculateCMP(
    $oldQuantity,
    $oldCost,
    $newQuantity,
    $newCost
);
```

#### 3. Implémenter les Contrôleurs Manquants ⚠️

**Fichier:** `app/Http/Controllers/Api/InventoryController.php` (À créer)
```php
// POST   /inventories                 - Créer inventaire
// POST   /inventories/{id}/start      - Démarrer
// POST   /inventories/{id}/items      - Ajouter item
// POST   /inventories/{id}/complete   - Finaliser
// POST   /inventories/{id}/validate   - Valider
// GET    /inventories/{id}             - Détail avec items
// GET    /inventories/{id}/summary     - Résumé
// POST   /inventories/{id}/import-csv - Importer CSV
// GET    /inventories/{id}/export-csv - Exporter CSV
```

**Fichier:** `app/Http/Controllers/Api/WarehouseController.php` (À créer)
```php
// GET    /warehouses                  - Liste
// POST   /warehouses                  - Créer
// GET    /warehouses/{id}             - Détail avec stocks
// PUT    /warehouses/{id}             - Mettre à jour
// DELETE /warehouses/{id}             - Supprimer (soft)
// GET    /warehouses/{id}/stock-value - Valeur stock
```

**Fichier:** `app/Http/Controllers/Api/BillingController.php` (À créer - tenant)
```php
// GET    /billing/subscription        - Souscription active
// GET    /billing/invoices            - Historique factures
// GET    /billing/invoices/{id}       - Détail facture
// POST   /billing/upgrade-plan        - Upgrade plan
```

**Fichier:** `app/Http/Controllers/Api/HostBillingController.php` (À créer - admin)
```php
// GET    /host/plans                  - Plans disponibles
// POST   /host/plans                  - Créer plan
// PUT    /host/plans/{id}             - Mettre à jour
// GET    /host/invoices               - Toutes factures
// POST   /host/tenants/{id}/suspend   - Suspendre tenant
```

#### 4. Créer les Routes Manquantes ⚠️
**Fichier:** `routes/api.php`
```php
// Ajouter avant le middleware 'auth:sanctum'
Route::middleware('auth:sanctum')->group(function () {
    // Warehouses
    Route::apiResource('warehouses', WarehouseController::class);
    
    // Inventories
    Route::prefix('inventories')->group(function () {
        Route::post('/', [InventoryController::class, 'store']);
        Route::get('/{inventory}', [InventoryController::class, 'show']);
        Route::post('/{inventory}/start', [InventoryController::class, 'start']);
        Route::post('/{inventory}/items', [InventoryController::class, 'addItem']);
        Route::post('/{inventory}/complete', [InventoryController::class, 'complete']);
        Route::post('/{inventory}/validate', [InventoryController::class, 'validate']);
        Route::get('/{inventory}/summary', [InventoryController::class, 'summary']);
        Route::post('/{inventory}/import-csv', [InventoryController::class, 'importCSV']);
        Route::get('/{inventory}/export-csv', [InventoryController::class, 'exportCSV']);
    });
    
    // Billing (tenant)
    Route::prefix('billing')->group(function () {
        Route::get('/subscription', [BillingController::class, 'getSubscription']);
        Route::get('/invoices', [BillingController::class, 'listInvoices']);
        Route::get('/invoices/{invoice}', [BillingController::class, 'getInvoice']);
        Route::post('/upgrade-plan', [BillingController::class, 'upgradePlan']);
    });
});

// Routes admin (host) - à créer en routes/host.php
Route::middleware(['auth:sanctum', 'admin'])->prefix('host')->group(function () {
    Route::apiResource('plans', HostPlanController::class);
    Route::get('invoices', [HostBillingController::class, 'listInvoices']);
    Route::post('tenants/{tenant}/suspend', [HostTenantController::class, 'suspend']);
    Route::post('tenants/{tenant}/reactivate', [HostTenantController::class, 'reactivate']);
});
```

#### 5. Créer Seeders pour Plans & Configurations ⚠️
**Fichier:** `database/seeders/SubscriptionPlansSeeder.php`
```php
// Créer les 3 plans de base:
// - Startup (14.99/mois, 5 users, 2 warehouses, no backup)
// - Pro (29.99/mois, 20 users, 5 warehouses, avec backup)
// - Enterprise (99.99/mois, unlimited, 20 warehouses, API, backup)
```

---

### 🟡 HAUTES PRIORITÉS (Semaine 1)

#### 6. Endpoints Transfers Complets
- [ ] TransferController complet avec validations
- [ ] Tests des transferts automatiques
- [ ] UI React Transfers page

#### 7. Exports Comptables Base
- [ ] ExportController pour Trial Balance (Excel)
- [ ] ExportController pour Income Statement (PDF)
- [ ] Async job pour gros exports

#### 8. POS Mode A/B Logic
- [ ] Vérifier `mode_pos` dans SaleService
- [ ] Différence warehouse source selon mode
- [ ] Tests scénarios A/B

#### 9. Frontend Pages Manquantes
- [ ] TransfersPage avec CRUD
- [ ] AccountingPage avec Grand Livre
- [ ] SettingsPage avec warehouse config

---

## 🔧 PROCÉDURE D'EXÉCUTION

### Étape 1: Créer les Contrôleurs (InventoryController, WarehouseController, etc.)

```bash
# Générer les contrôleurs
php artisan make:controller Api/InventoryController --model=Inventory --api
php artisan make:controller Api/WarehouseController --model=Warehouse --api
php artisan make:controller Api/BillingController --api
php artisan make:controller Api/HostBillingController --api
```

### Étape 2: Implémen les Contrôleurs

Voir template ci-dessous pour `InventoryController`.

### Étape 3: Ajouter les Routes

Dans `routes/api.php`, ajouter les groupes manquants.

### Étape 4: Exécuter les Migrations

```bash
php artisan migrate
php artisan db:seed --class=SubscriptionPlansSeeder
```

### Étape 5: Tester les Endpoints

```bash
# Tester inventaire
POST /api/inventories
POST /api/inventories/1/items
POST /api/inventories/1/complete

# Tester transfers
POST /api/transfers
POST /api/transfers/1/approve

# Tester billing
GET /api/billing/subscription
```

---

## 🧪 TEMPLATE - InventoryController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Domains\Stocks\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    private InventoryService $service;

    public function __construct()
    {
        $this->service = new InventoryService();
        $this->middleware('auth:sanctum');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
        ]);

        $inventory = $this->service->createInventory($validated['warehouse_id']);

        return response()->json([
            'success' => true,
            'data' => $inventory,
        ], 201);
    }

    public function show(Inventory $inventory): JsonResponse
    {
        $this->authorize('view', $inventory);

        return response()->json([
            'success' => true,
            'data' => $inventory->load('items'),
        ]);
    }

    public function start(Inventory $inventory): JsonResponse
    {
        $this->authorize('update', $inventory);
        $inventory = $this->service->startInventory($inventory);

        return response()->json([
            'success' => true,
            'data' => $inventory,
        ]);
    }

    public function addItem(Request $request, Inventory $inventory): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'counted_qty' => 'required|integer|min:0',
        ]);

        $item = $this->service->addItem(
            $inventory,
            $validated['product_id'],
            $validated['counted_qty']
        );

        return response()->json([
            'success' => true,
            'data' => $item,
        ], 201);
    }

    public function complete(Inventory $inventory): JsonResponse
    {
        $this->authorize('update', $inventory);
        $inventory = $this->service->completeInventory($inventory);

        return response()->json([
            'success' => true,
            'data' => $inventory,
        ]);
    }

    public function validate(Inventory $inventory): JsonResponse
    {
        $this->authorize('update', $inventory);
        $inventory = $this->service->validateInventory($inventory);

        return response()->json([
            'success' => true,
            'data' => $inventory,
        ]);
    }

    public function summary(Inventory $inventory): JsonResponse
    {
        $this->authorize('view', $inventory);
        $summary = $this->service->getInventorySummary($inventory);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    public function importCSV(Request $request, Inventory $inventory): JsonResponse
    {
        $validated = $request->validate([
            'csv' => 'required|string',
        ]);

        $results = $this->service->importFromCSV(
            $inventory,
            $validated['csv']
        );

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    public function exportCSV(Inventory $inventory): \Symfony\Component\HttpFoundation\Response
    {
        $csv = $this->service->exportAsCSV($inventory);

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=inventory_{$inventory->reference}.csv");
    }
}
```

---

## 📊 STATUT RÉCAPITULATIF

| Composant | État | % |
|-----------|------|---|
| Migrations | ✅ Complet | 100% |
| Modèles | ✅ Complet | 100% |
| Services | 🟡 Partiellement | 70% |
| Contrôleurs | 🟡 Partiellement | 40% |
| Routes | 🟡 Partiellement | 50% |
| Tests | 🟡 Partiellement | 20% |
| **PHASE 1** | 🟡 **EN COURS** | **60%** |

---

## ⏰ TIMELINE ESTIMÉE

- **Jour 1:** Créer contrôleurs + routes (6 heures)
- **Jour 2:** Mettre à jour listeners + tests unitaires (6 heures)
- **Jour 3:** Frontend pages manquantes (8 heures)
- **Jour 4:** QA + bug fixes (4 heures)

**Total Phase 1:** ~24 heures (3-4 jours avec Copilot)

---

## 🎯 NEXT STEPS

1. ✅ Créer `InventoryController` (template fourni)
2. ✅ Créer `WarehouseController`
3. ✅ Créer `BillingController`
4. ✅ Ajouter routes dans api.php
5. ✅ Mettre à jour listeners pour AccountingPostingService
6. ✅ Créer `SubscriptionPlansSeeder`
7. ✅ Frontend: TransfersPage, SettingsPage
8. ✅ Tests pour tous les nouveaux endpoints

**Status Finale Phase 1:** Cible 100% par 25 Novembre 2025
