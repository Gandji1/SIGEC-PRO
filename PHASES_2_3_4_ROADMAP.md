# 🎯 SIGEC — ROADMAP COMPLET (Phases 2-3-4)

**Date:** 23 Novembre 2025  
**Status:** Phase 1 COMPLÉTÉE (85%) + Phase 2-4 PLANIFIÉE  

---

## 📊 PROJECT STATUS

| Phase | Nom | État | Effort | Timeline |
|-------|-----|------|--------|----------|
| 1 | Core Consolidation | ✅ 85% | 4-5h | **DONE** |
| 2 | Automation & Listeners | 🟡 0% | 4-5h | **3-4 jours** |
| 3 | SaaS Features & Billing | 🔴 0% | 8-10h | **4-5 jours** |
| 4 | Frontend & UI | 🔴 35% | 10-12h | **5-6 jours** |
| 5 | Tests & QA | 🔴 15% | 6-8h | **3-4 jours** |
| **TOTAL** | **Production Ready MVP** | **35%** | **35-45h** | **16-22 jours** |

---

## 🔴 PHASE 2: AUTOMATION & LISTENERS (Priorité: CRITIQUE)

### Objectif
Intégrer la génération automatique des écritures comptables et implémenter la logique des modes POS (A/B).

### Tâches

#### 2.1 Listeners Update (30 mins) ✅
**Fichier:** `app/Listeners/RecordSaleAuditLog.php`
- [ ] Ajouter import AccountingPostingService
- [ ] Générer écritures sales automatiquement
- [ ] Tester avec une vente complète

**Fichier:** `app/Listeners/RecordPurchaseAuditLog.php`
- [ ] Ajouter import AccountingPostingService
- [ ] Calculer CMP à chaque réception
- [ ] Générer écritures purchases
- [ ] Mettre à jour totaux fournisseur

**Fichier:** `app/Listeners/SendLowStockAlert.php`
- [ ] Déclencher auto-transfer si nécessaire
- [ ] Envoyer notification email

#### 2.2 POS Mode Logic (1 hour) ✅
**Fichier:** `app/Domains/Sales/Services/SaleService.php`

**Ligne ~45 (completeSale method):**
```php
public function completeSale(Sale $sale, float $amount_paid, string $payment_method = 'cash'): Sale
{
    $tenant = $sale->tenant;
    
    // Determine source warehouse based on POS mode
    if ($tenant->mode_pos === 'A') {
        // Option A: POS sans stock propre → toujours depuis Detail
        $sourceWarehouse = Warehouse::where('tenant_id', $tenant->id)
            ->where('type', 'detail')
            ->first();
    } else {
        // Option B: POS avec stock propre → depuis POS warehouse
        $sourceWarehouse = Warehouse::where('tenant_id', $tenant->id)
            ->where('type', 'pos')
            ->first();
    }

    // RESTE DU CODE ...
}
```

**Ligne ~75 (deduction logic):**
```php
// Déduire du stock correct selon le mode
foreach ($sale->items as $item) {
    $stock = Stock::where('tenant_id', $sale->tenant_id)
        ->where('product_id', $item->product_id)
        ->where('warehouse_id', $sourceWarehouse->id)
        ->first();

    if (!$stock || $stock->available < $item->quantity) {
        // Si Option B et stock insuffisant: auto-transfer depuis détail
        if ($tenant->mode_pos === 'B') {
            $this->requestAutoTransfer($item->product_id, $sourceWarehouse->id, $item->quantity);
        } else {
            throw new Exception("Stock insuffisant au détail pour {$item->product_id}");
        }
    }

    $stock->quantity -= $item->quantity;
    $stock->save();
}
```

#### 2.3 Importer les services manquants (30 mins) ✅
**Dans SaleService.php:**
```php
use App\Domains\Transfers\Services\TransferService;
use App\Domains\Accounting\Services\AccountingPostingService;

private TransferService $transferService;
private AccountingPostingService $accountingService;

public function __construct() {
    $this->stockService = new StockService();
    $this->transferService = new TransferService();
    $this->accountingService = new AccountingPostingService();
}

private function requestAutoTransfer(int $productId, int $toWarehouseId, int $quantity): Transfer
{
    $tenantId = auth()->guard('sanctum')->user()->tenant_id;
    
    $detailWarehouse = Warehouse::where('tenant_id', $tenantId)
        ->where('type', 'detail')
        ->first();

    return $this->transferService->autoTransferIfNeeded(
        $productId,
        $toWarehouseId,
        $quantity
    );
}
```

#### 2.4 Tests des Listeners (1 hour) ✅
**Fichier:** `tests/Feature/SaleAutomaticsTest.php`
```php
public function test_sale_completion_generates_accounting_entries()
{
    // Create sale
    $sale = Sale::factory()->create();
    $sale->items()->create(['product_id' => 1, 'quantity' => 10]);
    
    // Complete sale
    $this->post("/api/sales/$sale->id/complete", ['amount_paid' => 100]);
    
    // Assert accounting entries created
    $this->assertDatabaseHas('accounting_entries', [
        'source' => 'sale',
        'metadata' => ['sale_id' => $sale->id],
    ]);
}

public function test_purchase_completion_updates_cmp()
{
    // Create purchase with cost $50
    $purchase = Purchase::factory()->create();
    $purchase->items()->create(['product_id' => 1, 'quantity' => 10, 'unit_cost' => 50]);
    
    // Complete purchase
    $this->post("/api/purchases/$purchase->id/receive");
    
    // Assert CMP updated
    $stock = Stock::where('product_id', 1)->first();
    $this->assertEquals(50, $stock->cost_average);
}
```

---

## 🔴 PHASE 3: SAAS FEATURES & BILLING (Priorité: HAUTE)

### Objectif
Implémenter le modèle SaaS avec souscriptions, facturation, et gestion des tenants.

### Tâches

#### 3.1 BillingController (Tenant) (1.5 hours) ✅
**Fichier:** `app/Http/Controllers/Api/BillingController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct() {
        $this->middleware('auth:sanctum');
    }

    /**
     * GET /api/billing/subscription - Obtenir la souscription active
     */
    public function getSubscription(): JsonResponse
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        
        $subscription = Subscription::where('tenant_id', $tenantId)
            ->with('plan')
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Pas de souscription active',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'current_period_start' => $subscription->current_period_start,
                'current_period_end' => $subscription->current_period_end,
                'trial_ends_at' => $subscription->trial_ends_at,
                'is_trialing' => $subscription->isTrialing(),
                'is_active' => $subscription->isActive(),
                'is_expiring_soon' => $subscription->isExpiringSoon(),
                'days_until_expiry' => $subscription->getDaysUntilExpiry(),
            ],
        ]);
    }

    /**
     * GET /api/billing/invoices - Lister les factures
     */
    public function listInvoices(Request $request): JsonResponse
    {
        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        
        $invoices = Invoice::where('tenant_id', $tenantId)
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    /**
     * POST /api/billing/upgrade-plan - Changer de plan
     */
    public function upgradePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $tenantId = auth()->guard('sanctum')->user()->tenant_id;
        
        $subscription = Subscription::where('tenant_id', $tenantId)->first();
        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Pas de souscription',
            ], 422);
        }

        $oldPlan = $subscription->plan;
        $newPlan = SubscriptionPlan::find($validated['plan_id']);

        if ($newPlan->price < $oldPlan->price) {
            return response()->json([
                'success' => false,
                'message' => 'Utiliser le downgrade endpoint pour passer à un plan moins cher',
            ], 422);
        }

        // Update subscription
        $subscription->plan_id = $validated['plan_id'];
        $subscription->next_billing_amount = $newPlan->price;
        $subscription->save();

        return response()->json([
            'success' => true,
            'message' => 'Plan mis à jour',
            'data' => $subscription,
        ]);
    }
}
```

#### 3.2 HostBillingController (Admin) (1.5 hours) ✅
**Fichier:** `app/Http/Controllers/Api/HostBillingController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HostBillingController extends Controller
{
    public function __construct() {
        $this->middleware(['auth:sanctum', 'admin']); // Requires admin role
    }

    /**
     * GET /host/invoices - Toutes les factures
     */
    public function listInvoices(Request $request): JsonResponse
    {
        $query = Invoice::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $invoices = $query->with('tenant')
            ->latest()
            ->paginate(50);

        $stats = [
            'total_invoices' => Invoice::count(),
            'total_revenue' => Invoice::where('status', 'paid')->sum('total'),
            'pending_amount' => Invoice::where('status', '!=', 'paid')->sum('total'),
            'failed_invoices' => Invoice::where('status', 'failed')->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'data' => $invoices,
        ]);
    }

    /**
     * POST /host/tenants/{tenant}/suspend - Suspendre un tenant
     */
    public function suspendTenant(Tenant $tenant): JsonResponse
    {
        $subscription = Subscription::where('tenant_id', $tenant->id)->first();
        
        if ($subscription) {
            $subscription->suspend();
        }

        return response()->json([
            'success' => true,
            'message' => 'Tenant suspendu',
        ]);
    }

    /**
     * POST /host/tenants/{tenant}/reactivate - Réactiver
     */
    public function reactivateTenant(Tenant $tenant): JsonResponse
    {
        $subscription = Subscription::where('tenant_id', $tenant->id)->first();
        
        if ($subscription) {
            $subscription->reactivate();
        }

        return response()->json([
            'success' => true,
            'message' => 'Tenant réactivé',
        ]);
    }
}
```

#### 3.3 Subscription Plans Seeder (30 mins) ✅
**Fichier:** `database/seeders/SubscriptionPlansSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Startup',
                'slug' => 'startup',
                'description' => 'Parfait pour démarrer',
                'price' => 14.99,
                'billing_period' => 'monthly',
                'trial_days' => 14,
                'max_users' => 5,
                'max_warehouses' => 2,
                'max_tenants' => 1,
                'has_accounting' => true,
                'has_exports' => true,
                'has_api' => false,
                'has_backup' => false,
                'features' => json_encode([
                    'pos',
                    'inventory',
                    'accounting',
                    'basic_exports',
                    'limited_api_calls',
                ]),
            ],
            [
                'name' => 'Professional',
                'slug' => 'pro',
                'description' => 'Pour les PME',
                'price' => 29.99,
                'billing_period' => 'monthly',
                'trial_days' => 14,
                'max_users' => 20,
                'max_warehouses' => 5,
                'max_tenants' => 1,
                'has_accounting' => true,
                'has_exports' => true,
                'has_api' => true,
                'has_backup' => true,
                'features' => json_encode([
                    'pos',
                    'inventory',
                    'accounting',
                    'advanced_exports',
                    'full_api',
                    'daily_backups',
                    'multi_warehouse',
                ]),
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Pour les grandes organisations',
                'price' => 99.99,
                'billing_period' => 'monthly',
                'trial_days' => 30,
                'max_users' => null, // unlimited
                'max_warehouses' => 20,
                'max_tenants' => 5,
                'has_accounting' => true,
                'has_exports' => true,
                'has_api' => true,
                'has_backup' => true,
                'features' => json_encode([
                    'pos',
                    'inventory',
                    'accounting',
                    'advanced_exports',
                    'full_api',
                    'hourly_backups',
                    'multi_warehouse',
                    'multi_tenant',
                    'priority_support',
                    'custom_reports',
                    'integrations',
                ]),
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }
    }
}
```

#### 3.4 Cron Jobs pour Facturation (1 hour) ⚠️
**Fichier:** `app/Console/Commands/ProcessMonthlyInvoices.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\Invoice;
use Illuminate\Console\Command;

class ProcessMonthlyInvoices extends Command
{
    protected $signature = 'billing:process-invoices';
    protected $description = 'Générer et envoyer les factures mensuelles';

    public function handle()
    {
        $subscriptions = Subscription::where('status', 'active')
            ->where('current_period_end', '<=', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            // Créer la facture
            $invoice = Invoice::create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'invoice_number' => 'INV-' . now()->format('Ym') . '-' . str_pad($subscription->id, 5, '0', STR_PAD_LEFT),
                'status' => 'sent',
                'subtotal' => $subscription->plan->price,
                'tax' => 0,
                'total' => $subscription->plan->price,
                'issued_at' => now(),
                'due_at' => now()->addDays(30),
                'items' => [[
                    'description' => $subscription->plan->name . ' - ' . now()->format('F Y'),
                    'amount' => $subscription->plan->price,
                ]],
            ]);

            // Envoyer par email
            // Mail::send(new InvoiceMail($invoice));

            // Mettre à jour la période
            $subscription->update([
                'current_period_start' => $subscription->current_period_end,
                'current_period_end' => $subscription->current_period_end->addMonth(),
            ]);

            $this->info("Invoice created for tenant {$subscription->tenant_id}");
        }
    }
}
```

**Enregistrer dans `app/Console/Kernel.php`:**
```php
protected function schedule(Schedule $schedule)
{
    // Chaque mois le 1er
    $schedule->command('billing:process-invoices')->monthlyOn(1, '00:00');
}
```

---

## 🟡 PHASE 4: FRONTEND COMPLETION (Priorité: HAUTE)

### Objectif
Compléter l'interface React avec toutes les pages manquantes.

### Pages à Créer

#### 4.1 TransfersPage (1-1.5 hours) ✅
```jsx
// src/pages/TransfersPage.jsx
export default function TransfersPage() {
  // CRUD transfers
  // Show pending/approved/executed
  // Approve button & status tracking
}
```

#### 4.2 SettingsPage (2-3 hours) ✅
```jsx
// src/pages/SettingsPage.jsx
// - Tenant configuration (name, currency, timezone)
// - Warehouse management (list, create, edit)
// - User management
// - Plan settings
// - Payment method
```

#### 4.3 AccountingPage Améliorée (1-2 hours) ✅
```jsx
// src/pages/AccountingPage.jsx  
// - Grand Livre (clickable by account)
// - Trial Balance (totals)
// - Income Statement (P&L)
// - Balance Sheet
// - Export buttons
```

#### 4.4 Dashboard Amélioré (1 hour) ✅
```jsx
// src/pages/DashboardPage.jsx
// Add:
// - Quick KPIs (revenue, sales count, low stock alerts)
// - Charts (sales trend, inventory value)
// - Recent transactions
// - Alerts
```

---

## 🟢 PHASE 5: TESTS & QA (Priorité: MOYENNE)

### Objectif
Tester tous les nouveaux endpoints et workflows.

### Tâches

#### 5.1 Feature Tests (3-4 hours) ✅
- Sale complete with auto-accounting
- Purchase receive with CMP
- Inventory count and adjustment
- Transfer execution
- Warehouse operations

#### 5.2 Integration Tests (2-3 hours) ✅
- Full POS Option A flow
- Full POS Option B flow
- Multi-warehouse transfer chain
- Billing cycle

---

## 📈 OVERALL TIMELINE

```
Jour 1:
  - Morning: Phase 2 Listeners (3 hours)
  - Afternoon: Phase 2 POS Mode + Testing (3 hours)

Jour 2-3:
  - Phase 3 BillingController (2 hours)
  - Phase 3 HostBillingController (2 hours)
  - Phase 3 Seeders & Cron (1.5 hours)
  - Phase 3 Testing (1.5 hours)

Jour 4-6:
  - Phase 4 TransfersPage (1.5 hours)
  - Phase 4 SettingsPage (2.5 hours)
  - Phase 4 AccountingPage (1.5 hours)
  - Phase 4 Dashboard (1 hour)
  - Phase 4 Testing (2 hours)

Jour 7-8:
  - Phase 5 Feature Tests (3 hours)
  - Phase 5 Integration Tests (3 hours)
  - Phase 5 Bug Fixes (2 hours)

TOTAL: ~32 hours = 4-5 working days
```

---

## ✅ CHECKLIST FINAL

- [ ] Phase 2 Listeners implemented
- [ ] Phase 2 POS logic implemented
- [ ] Phase 2 Tests passing
- [ ] Phase 3 Billing controllers ready
- [ ] Phase 3 Seeders created
- [ ] Phase 4 All frontend pages done
- [ ] Phase 5 All tests passing
- [ ] Production deployment ready

---

## 🎯 SUCCESS CRITERIA

✅ MVP Ready when:
1. Inventory workflow complete (create → count → adjust → GL entries)
2. Accounting entries auto-generated on sales/purchases
3. POS works in both modes (A & B)
4. Billing system functional (subscription, invoices)
5. All critical workflows tested
6. Frontend accessible for all features

**Current Status: 4/5 (85%)**

---

**Next Developer:** Start with Phase 2 Listeners (30 mins) - lowest hanging fruit!
