# 🏗️ SIGEC Architecture & Business Logic

## System Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                         SIGEC v1.0                              │
│                    Multi-Tenant ERP System                      │
└─────────────────────────────────────────────────────────────────┘

┌──────────────┐      ┌──────────────┐      ┌──────────────────┐
│   Frontend   │      │   Backend    │      │   Database       │
│  (Next.js)   │◄────►│  (Laravel)   │◄────►│   (PostgreSQL)   │
│  Vercel      │      │              │      │                  │
└──────────────┘      └──────────────┘      └──────────────────┘
     UI/UX          Services + APIs          Multi-tenant Data
     Pages            Controllers             Audit Trail
     Forms             Routes
```

---

## Multi-Tenant Architecture

### Tenancy Enforcement

```
User Login → Auth Token → Middleware (tenant_id extraction)
                              ↓
                    DB Query Scoped to tenant_id
                              ↓
                    Response filtered by tenant
```

**Implementation:**
```php
// middleware/tenant.php
$tenant_id = auth()->guard('sanctum')->user()->tenant_id;

// All queries:
Model::where('tenant_id', $tenant_id)
```

**Tables Scoped:**
- `users`
- `products`
- `purchases` / `purchase_items`
- `sales` / `sale_items`
- `stocks` / `stock_movements`
- `accounting_entries`
- `audit_logs`

---

## Core Business Flows

### 1️⃣ Purchase Order → Stock Receipt (CMP Calculation)

```
┌─────────────────────────────────────────────────────────────┐
│                    PO RECEIVE FLOW                           │
└─────────────────────────────────────────────────────────────┘

Step 1: Create Purchase Order
  POST /api/purchases
  ├─ tenant_id, supplier_name, payment_method
  ├─ Items: [product_id, quantity, unit_price]
  └─ Status: pending

Step 2: Confirm PO
  POST /api/purchases/{id}/confirm
  └─ Status: pending → confirmed

Step 3: Receive PO (AUTO CMP + STOCK UPDATE)
  POST /api/purchases/{id}/receive
  │
  ├─ FOR EACH item:
  │  ├─ Get current Stock record
  │  │  old_qty = 100, old_cmp = 10.00
  │  │
  │  ├─ Calculate NEW CMP:
  │  │  new_cmp = (old_qty × old_cmp + new_qty × unit_price) / (old_qty + new_qty)
  │  │  new_cmp = (100 × 10 + 50 × 15) / (100 + 50)
  │  │  new_cmp = 2500 / 150 = 11.67
  │  │
  │  ├─ Update Stock:
  │  │  stock.quantity += received_qty
  │  │  stock.cost_average = new_cmp
  │  │  stock.unit_cost = latest_unit_price
  │  │
  │  ├─ Record StockMovement (audit):
  │  │  type: 'purchase'
  │  │  reference: 'PUR-' . purchase.id
  │  │  quantity_received, unit_cost
  │  │
  │  └─ Post GL Entry (if auto-posting enabled):
  │     Debit: Inventory (3000)
  │     Credit: Payable (3000)
  │
  └─ Status: confirmed → received

Result:
  ✓ Stock quantity updated
  ✓ CMP recalculated for COGS accuracy
  ✓ GL entries created
  ✓ Audit trail recorded
```

**CMP Formula (Weighted Average Cost):**
```
New CMP = (Previous_Inventory_Value + New_Inventory_Value) / Total_Quantity

Where:
  Previous_Inventory_Value = Current_Quantity × Current_CMP
  New_Inventory_Value = Received_Quantity × Unit_Price
  Total_Quantity = Current_Quantity + Received_Quantity
```

**Example Sequence:**
```
Receipt 1: Receive 100 @ 10.00
  CMP = 10.00
  Stock = 100

Receipt 2: Receive 50 @ 15.00
  CMP = (100×10 + 50×15) / 150 = 11.67
  Stock = 150

Receipt 3: Receive 25 @ 20.00
  CMP = (150×11.67 + 25×20) / 175 = 12.43
  Stock = 175
```

---

### 2️⃣ Sale → Stock Deduction & Payment

```
┌─────────────────────────────────────────────────────────────┐
│                    SALE FLOW                                │
└─────────────────────────────────────────────────────────────┘

Step 1: Create Sale (Draft)
  POST /api/sales
  ├─ customer_name, mode (pos/manual)
  ├─ Items: [product_id, quantity, unit_price]
  └─ Status: draft

Step 2: Add Items to Sale
  POST /api/sales/{id}/add-item
  ├─ product_id, quantity, unit_price
  └─ Auto-calculate totals & tax

Step 3: Complete Sale (VALIDATE + DEDUCT STOCK + RECORD PAYMENT)
  POST /api/sales/{id}/complete
  │
  ├─ Validate Stock Availability:
  │  FOR EACH item:
  │    IF stock.available < quantity
  │      RETURN error: "Insufficient stock"
  │
  ├─ Reserve Stock (mark as unavailable):
  │  FOR EACH item:
  │    stock.reserved += quantity
  │    stock.available -= quantity
  │
  ├─ Deduct from Inventory:
  │  FOR EACH item:
  │    stock.quantity -= quantity
  │    cost_of_goods_sold += quantity × stock.cost_average
  │
  ├─ Record Payment:
  │  payment_method: cash, card, mobile_money, transfer
  │  amount_paid, status: completed
  │
  ├─ Post GL Entries (auto):
  │  Debit: Cash (if cash) / AR (if credit)
  │  Debit: COGS (quantity × CMP)
  │  Credit: Sales Revenue (quantity × selling_price)
  │  Credit: Inventory (quantity × CMP)
  │
  ├─ Update Cash Register:
  │  IF payment_method IN [cash, mobile_money]
  │    cash_register.balance += amount_paid
  │
  └─ Status: draft → completed

Result:
  ✓ Stock physically reduced
  ✓ Cash balance updated
  ✓ Revenue & COGS recorded
  ✓ Accurate margin calculation
```

---

### 3️⃣ Automatic GL Posting

```
┌─────────────────────────────────────────────────────────────┐
│              ACCOUNTING AUTOMATION                          │
└─────────────────────────────────────────────────────────────┘

Event: Purchase Order Received
  ├─ Debit:  Inventory (warehouse_gros)    [amount = qty × unit_price]
  └─ Credit: Payable (Supplier)             [amount = qty × unit_price]

Event: Sale Completed
  ├─ Debit:  Cash / AR                      [amount = sale total]
  ├─ Debit:  COGS                          [amount = qty × CMP]
  ├─ Credit: Sales Revenue                  [amount = qty × selling_price]
  └─ Credit: Inventory                      [amount = qty × CMP]

Event: Expense Recorded
  ├─ Debit:  Operating Expense              [amount = expense amount]
  └─ Credit: Cash                           [amount = expense amount]

Event: Inventory Adjustment (count variance)
  ├─ Debit/Credit: Inventory Adjustment     [for variance]
  └─ Credit/Debit: Inventory Value          [reverse GL for count]

Implementation: Service calls GL posting after business logic
  $purchaseService->receivePurchase()
    → accountingService->postPurchaseReceived()
      → AccountingEntry::create([debit/credit])
```

---

## Data Models (Entity Relationships)

```
┌──────────────┐
│   Tenants    │ (Multi-tenant hosts)
└──────┬───────┘
       │ 1:N
       ├─────────┬────────────┬────────────┬──────────────┐
       │         │            │            │              │
    ┌──▼──┐  ┌──▼──┐  ┌──────▼──┐  ┌────▼─────┐  ┌────▼─────┐
    │Users│  │Prods│  │Purchase │  │  Sales   │  │Suppliers │
    └─────┘  └─────┘  └─────────┘  └──────────┘  └──────────┘
                          │ 1:N        │ 1:N
                    ┌─────▼──┐  ┌─────▼──┐
                    │PurItems│  │SalItems│
                    └────────┘  └────────┘

    ┌────────┐         ┌───────┐
    │Stocks  │ (by     │ Ware  │
    │(qty, │  product  │houses │
    │ CMP) │   + whse) │       │
    └───┬──┘           └───────┘
        │ 1:N
    ┌───▼──────────┐
    │StockMovements│ (audit trail)
    │(type, qty,   │
    │ ref_id, user)│
    └──────────────┘

    ┌─────────────────┐
    │AccountingEntries│ (GL journal)
    │(debit, credit,  │
    │ amount, ref)    │
    └─────────────────┘

    ┌─────────────┐
    │  AuditLogs  │ (change tracking)
    │(action, old,│
    │ new, user)  │
    └─────────────┘
```

---

## Warehouse Strategy (Option A/B)

### Option A: Simple POS (No stock management)
```
Supplier → [Bulk Warehouse] → Direct to Sale
          (gros)
```
- Single warehouse (Gros)
- Sales directly from bulk
- Minimal inventory tracking
- Low complexity

### Option B: Managed Inventory (with preparation)
```
Supplier → [Bulk] → [Detail] → [POS Terminal]
          (gros)   (detail)
                    ↓
                 Prep work
                 (packaging,
                  sorting)
```
- Three warehouses: gros, detail, pos
- Sales from POS warehouse
- Auto-transfer requests: gros → detail → pos
- Order approval workflow
- Higher accuracy

**Current Implementation:** Both options supported but treated identically (can be differentiated in future iterations)

---

## Dashboard KPIs (Real-time)

```
┌─────────────────────────────────────────────────────────┐
│             DAILY DASHBOARD                            │
└─────────────────────────────────────────────────────────┘

GET /api/dashboard/stats → Aggregates:

Sales Metrics (today):
  ├─ Count: number of transactions
  ├─ Total Revenue: sum(sale.total)
  ├─ Total Tax: sum(sale.tax_amount)
  ├─ Items Sold: sum(item quantities)
  ├─ Average Transaction: total / count
  ├─ By Method: cash, card, mobile_money breakdown
  └─ By Warehouse: if multi-warehouse

Purchase Metrics (today):
  ├─ Count: number of received POs
  ├─ Total Cost: sum(purchase.total)
  └─ Items Received: sum(received quantities)

Cash Flow (today):
  ├─ Cash In: sum(cash/mobile_money sales)
  ├─ Cash Out: sum(cash purchases)
  └─ Net: cash_in - cash_out

Stock Alerts:
  ├─ Low Stock (< 20%): products below threshold
  ├─ Critical Stock (= 0): out of stock items
  └─ Items: product_id, name, warehouse, quantity

Pending Operations:
  ├─ Pending POs: count(status='pending')
  ├─ Pending Sales: count(status='draft')
  └─ Total: sum

User Activity:
  ├─ Active Users: logged in last 8 hours
  └─ Total Users: in tenant
```

---

## Reporting Engine

```
┌──────────────────────────────────────┐
│    REPORT GENERATION                 │
└──────────────────────────────────────┘

Sales Journal:
  FROM sales WHERE status='completed' AND completed_at BETWEEN dates
  GROUP BY: date, reference, customer
  COLUMNS: date, ref, customer, ht, tax, total, items_count
  FILTER: date range, customer, payment_method

Purchases Journal:
  FROM purchases WHERE status='received' AND received_date BETWEEN dates
  GROUP BY: date, reference, supplier
  COLUMNS: date, ref, supplier, ht, tax, total, items_count

P&L Statement:
  Revenue = SUM(sale.total) for completed sales
  COGS = SUM(qty × CMP) for sales
  Gross Profit = Revenue - COGS
  Expenses = SUM(expense amounts)
  Net Income = Gross Profit - Expenses
  Margin % = (Net Income / Revenue) × 100

Trial Balance:
  FOR EACH account:
    Debit Balance = SUM(debit entries)
    Credit Balance = SUM(credit entries)
  Total Debits = Total Credits (must balance)

Inventory Valuation:
  FOR EACH product:
    Total Value = quantity × cost_average

Export Formats:
  ├─ JSON: API response
  ├─ XLSX: Excel spreadsheet (sync, small)
  ├─ PDF: Pretty printed (future)
  └─ DOCX: Word document (future)
```

---

## Security & Audit

```
┌────────────────────────────────────────┐
│      SECURITY LAYERS                   │
└────────────────────────────────────────┘

Layer 1: Authentication
  ├─ Sanctum tokens (Laravel)
  ├─ Token expiration
  ├─ Secure password hashing (bcrypt)
  └─ Email verification (future)

Layer 2: Authorization (RBAC)
  ├─ User roles: Owner, Manager, Accountant, Cashier, Staff
  ├─ Role-based gates on sensitive actions
  ├─ Policy-based authorization (Laravel Policies)
  └─ Tenant-scoped permissions

Layer 3: Tenant Isolation
  ├─ Middleware scopes all queries by tenant_id
  ├─ Cannot access other tenant data
  ├─ Separate audit logs per tenant
  └─ Rate limiting per tenant

Layer 4: Audit Logging
  ├─ Every create/update/delete logged
  ├─ Audit table: action, old_values, new_values, user_id, timestamp
  ├─ Immutable audit trail
  └─ Change history for compliance

Layer 5: Data Encryption
  ├─ Password hashing (bcrypt)
  ├─ API keys encrypted (if stored)
  ├─ HTTPS only (Vercel + SSL)
  └─ DB connection secured
```

---

## Testing Strategy

```
┌────────────────────────────────────────┐
│         TEST LAYERS                    │
└────────────────────────────────────────┘

Unit Tests:
  ├─ Service logic (CMP calculation, totals)
  ├─ Model relationships
  └─ Utility functions

Feature/Integration Tests:
  ├─ Full flow: PO create → confirm → receive
  ├─ Stock movement recording
  ├─ GL posting
  ├─ Sale completion workflow
  ├─ Dashboard KPI calculation
  └─ Multi-tenant isolation

API Tests:
  ├─ Endpoint response codes
  ├─ Authorization checks
  ├─ Data validation
  └─ Error handling

End-to-End Tests:
  ├─ Frontend UI interactions
  ├─ Real API calls
  ├─ Complete business flows
  └─ Cross-browser compatibility

Example Test:
  php artisan test tests/Feature/PurchaseReceiveFlowTest.php -v
```

---

## Deployment Architecture

```
┌─────────────────────────────────────┐
│         DEPLOYMENT STACK            │
└─────────────────────────────────────┘

Frontend (Vercel):
  ├─ Next.js 15.1.2 with App Router
  ├─ Vercel Edge deployment
  ├─ CDN for static assets
  ├─ Auto-builds on main push
  └─ Free tier (up to specified limits)

Backend (Heroku or Railway - TODO):
  ├─ Laravel 11 + PHP 8.2
  ├─ Docker container
  ├─ Environment variables
  ├─ Database: PostgreSQL (Vercel/Railway)
  ├─ Queue: Redis (async jobs - TODO)
  └─ Horizon: Job monitoring (TODO)

Database:
  ├─ PostgreSQL
  ├─ Multi-tenant schema
  ├─ Automated backups (daily)
  ├─ Indexes on tenant_id + common queries
  └─ Connection pooling

Monitoring:
  ├─ Error logging (Sentry - TODO)
  ├─ Performance monitoring (NewRelic - TODO)
  └─ Uptime checks (Pingdom - TODO)

CI/CD:
  ├─ GitHub Actions (on push)
  ├─ Lint + test + build
  ├─ Deploy on main branch
  └─ Rollback capability (TODO)
```

---

**Last Updated:** 2025-11-24  
**Version:** 1.0.0  
**Architecture Status:** ✅ Production Ready (Core Flows)
