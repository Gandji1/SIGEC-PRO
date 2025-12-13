# 🔌 API Quick Reference - SIGEC v1.0

## Base URL
- **Development:** `http://localhost:8000/api`
- **Production:** `https://sigec-pi.vercel.app/api`

## Authentication

All endpoints (except register/login) require:
```
Authorization: Bearer {token}
Content-Type: application/json
```

### Register & Login
```bash
# Register (creates tenant + warehouses)
POST /register
{
  "name": "Business Name",
  "email": "owner@business.com",
  "password": "secure_password",
  "mode_pos": "A"  # or "B"
}

# Login
POST /login
{
  "email": "owner@business.com",
  "password": "secure_password"
}

Response: { "token": "xyz...", "user": {...} }
```

---

## 📊 Dashboard Endpoints

### Daily KPIs
```bash
GET /dashboard/stats

Response:
{
  "date": "2025-11-24",
  "sales": { "count": 5, "total_revenue": 1500, "items_sold": 45 },
  "purchases": { "count": 2, "total_cost": 800, "items_received": 120 },
  "cash_flow": { "cash_in": 1500, "cash_out": 500, "net_cash": 1000 },
  "stock_alerts": { "low_stock_count": 2, "items": [...] },
  "critical_stocks": { "critical_count": 0 },
  "pending_operations": { "pending_purchases": 1, "pending_sales": 0 }
}
```

### Monthly Report
```bash
GET /dashboard/monthly-report?month=11&year=2025

Response:
{
  "period": "2025-11",
  "sales": { "count": 45, "total_revenue": 45000, "average_transaction": 1000 },
  "purchases": { "count": 20, "total_cost": 30000 },
  "profitability": { "gross_profit": 15000, "margin_percent": 33.33 }
}
```

---

## 📦 Purchases (PO Management)

### Create PO
```bash
POST /purchases

{
  "supplier_name": "Supplier ABC",
  "supplier_phone": "+224600000000",
  "supplier_email": "supplier@abc.com",
  "payment_method": "transfer",  # cash, transfer, check
  "expected_date": "2025-11-30",
  "items": [
    {
      "product_id": 1,
      "quantity": 100,
      "unit_price": 10.00
    }
  ]
}

Response: { "data": { "id": 1, "status": "pending", ... } }
```

### List POs
```bash
GET /purchases?status=pending&per_page=20

Query params:
- status: pending, confirmed, received, cancelled
- supplier_id: filter by supplier
- per_page: 1-100
```

### Get PO Details
```bash
GET /purchases/{id}

Response: { "data": { "id": 1, "status": "pending", "items": [...], ... } }
```

### Add Item to PO
```bash
POST /purchases/{id}/add-item

{
  "product_id": 2,
  "quantity": 50,
  "unit_price": 15.00
}
```

### Confirm PO
```bash
POST /purchases/{id}/confirm

# Changes status: pending → confirmed
```

### Receive PO (CMP Calculation)
```bash
POST /purchases/{id}/receive

# Changes status: confirmed → received
# Updates Stock.quantity with CMP calculation
# Creates StockMovement audit entry
# Creates GL posting (if auto-posting enabled)
```

### Cancel PO
```bash
POST /purchases/{id}/cancel

# Only if status in [pending, confirmed]
```

### Purchases Report
```bash
GET /purchases/report?start_date=2025-11-01&end_date=2025-11-30

Response:
{
  "period": { "start": "2025-11-01", "end": "2025-11-30" },
  "report": {
    "Supplier ABC": { "count": 2, "total": 3500 },
    ...
  }
}
```

---

## 💰 Sales

### Create Sale
```bash
POST /sales

{
  "customer_name": "John Doe",
  "customer_phone": "+224600000000",
  "mode": "pos",  # or "manual"
  "payment_method": "cash",  # card, mobile_money, transfer
  "items": [
    {
      "product_id": 1,
      "quantity": 10,
      "unit_price": 12.00
    }
  ]
}

Response: { "data": { "id": 1, "status": "draft", ... } }
```

### List Sales
```bash
GET /sales?status=completed&per_page=20

Query params:
- status: draft, completed, cancelled
- per_page: 1-100
```

### Get Sale Details
```bash
GET /sales/{id}

Response: { "data": { "id": 1, "items": [...], "total": 120, ... } }
```

### Add Item to Sale
```bash
POST /sales/{id}/add-item

{
  "product_id": 2,
  "quantity": 5,
  "unit_price": 15.00
}
```

### Complete Sale (Stock Deduction)
```bash
POST /sales/{id}/complete

{
  "amount_paid": 120.00,
  "payment_method": "cash"  # or card, mobile_money, transfer
}

# Changes status: draft → completed
# Deducts Stock.quantity for each item
# Records payment
# Creates GL posting
```

### Cancel Sale
```bash
POST /sales/{id}/cancel

# Only if status in [draft]
# Releases reserved stock
```

### Sales Report
```bash
GET /sales/report?start_date=2025-11-01&end_date=2025-11-30&group_by=daily

Query params:
- group_by: daily, weekly, monthly, total
- start_date, end_date: YYYY-MM-DD format
```

---

## 💸 Expenses (Charges)

### Record Expense
```bash
POST /expenses

{
  "category": "transport",  # personnel, transport, utilities, maintenance, other
  "description": "Fuel for delivery",
  "amount": 15000,
  "date": "2025-11-24",
  "payment_method": "cash"  # optional
}

Response: { "message": "Expense recorded", "entry": {...} }
```

### List Expenses
```bash
GET /expenses?start_date=2025-11-01&end_date=2025-11-30&category=transport

Query params:
- category: filter by expense category
- start_date, end_date: YYYY-MM-DD format
- per_page: 1-100
```

---

## 📋 Reports

### Sales Journal
```bash
GET /reports/sales-journal?start_date=2025-11-01&end_date=2025-11-30

Response:
{
  "period": { "start": "2025-11-01", "end": "2025-11-30" },
  "entries": [
    {
      "date": "2025-11-24",
      "reference": "SALE-20251124-0001",
      "customer": "John Doe",
      "total_ht": 100,
      "tax": 0,
      "total_ttc": 100
    }
  ],
  "summary": { "total_sales": 1500, "total_tax": 0, "transaction_count": 15 }
}
```

### Purchases Journal
```bash
GET /reports/purchases-journal?start_date=2025-11-01&end_date=2025-11-30

# Same structure as sales journal
```

### Profit & Loss Statement
```bash
GET /reports/profit-loss?start_date=2025-11-01&end_date=2025-11-30

Response:
{
  "revenue": 1500,
  "cost_of_goods_sold": 800,
  "gross_profit": 700,
  "expenses": 200,
  "net_income": 500,
  "margin_percent": 33.33
}
```

### Trial Balance
```bash
GET /reports/trial-balance?date=2025-11-24

Response:
{
  "date": "2025-11-24",
  "accounts": {
    "10000": { "debit": 5000, "credit": 0 },  # Assets
    "40000": { "debit": 0, "credit": 2000 },  # Revenue
    ...
  },
  "totals": { "total_debit": 50000, "total_credit": 50000 }
}
```

### Export Sales Journal (XLSX)
```bash
GET /reports/sales-journal/export?start_date=2025-11-01&end_date=2025-11-30

# Returns .xlsx file download
# Headers: Date, Reference, Customer, Total HT, Tax, Total TTC, Items
```

---

## 📊 Error Responses

All endpoints return errors in consistent format:

### 400 Bad Request
```json
{
  "error": "Validation failed",
  "message": "The amount must be a number",
  "errors": {
    "amount": ["The amount must be a number"]
  }
}
```

### 401 Unauthorized
```json
{
  "message": "Unauthenticated"
}
```

### 403 Forbidden
```json
{
  "error": "Unauthorized action"
}
```

### 404 Not Found
```json
{
  "error": "Resource not found"
}
```

### 500 Server Error
```json
{
  "error": "Internal server error",
  "message": "Exception message (debug only in dev)"
}
```

---

## 🧪 Quick Test Script

```bash
#!/bin/bash

# Variables
API="http://localhost:8000/api"
EMAIL="demo@sigec.com"
PASSWORD="password123"

# 1. Login
TOKEN=$(curl -s -X POST "$API/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}" \
  | jq -r '.token')

echo "Token: $TOKEN"

# 2. Get Dashboard Stats
curl -s -X GET "$API/dashboard/stats" \
  -H "Authorization: Bearer $TOKEN" | jq '.'

# 3. List Purchases
curl -s -X GET "$API/purchases?status=pending" \
  -H "Authorization: Bearer $TOKEN" | jq '.data[0]'

# 4. Get Profit & Loss
curl -s -X GET "$API/reports/profit-loss" \
  -H "Authorization: Bearer $TOKEN" | jq '.'
```

---

## 📝 Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success (GET, PUT) |
| 201 | Created (POST) |
| 204 | No Content (DELETE) |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Unprocessable Entity (validation) |
| 500 | Server Error |

---

**Last Updated:** 2025-11-24  
**Version:** 1.0.0  
**API Status:** ✅ Production Ready
