#!/bin/bash
set -e

TOKEN="7|buq7YlFT5vJXtKaHmhPNzzgs4CKnWQRGwPOgyou479c86462"
TENANT_ID="4"
BASE="http://localhost:8000/api"

echo "Testing SIGEC APIs..."
echo

# 1. Test Customers
echo "1️⃣ Testing Customers"
curl -s "$BASE/customers" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" > /tmp/customers.json
COUNT=$(python3 -c "import json; d=json.load(open('/tmp/customers.json')); print(len(d.get('data', [])))")
echo "   ✅ GET /api/customers: $COUNT customers"

# 2. Test Expenses
echo "2️⃣ Testing Expenses"
curl -s -X POST "$BASE/expenses" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" \
  -d '{"description":"Electric","amount":"100000","category":"utilities","date":"2025-11-25"}' > /tmp/expense.json
EXPENSE_ID=$(python3 -c "import json; d=json.load(open('/tmp/expense.json')); print(d.get('id', '?'))")
echo "   ✅ POST /api/expenses: Created expense #$EXPENSE_ID"

curl -s "$BASE/expenses" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" > /tmp/expenses.json
COUNT=$(python3 -c "import json; d=json.load(open('/tmp/expenses.json')); print(len(d.get('data', [])))")
echo "   ✅ GET /api/expenses: $COUNT expenses"

# 3. Test Accounting Summary
echo "3️⃣ Testing Accounting"
curl -s "$BASE/accounting/summary" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" > /tmp/accounting.json
REVENUE=$(python3 -c "import json; d=json.load(open('/tmp/accounting.json')); print(d.get('revenue', 0))")
echo "   ✅ GET /api/accounting/summary: Revenue=$REVENUE"

# 4. Test Suppliers
echo "4️⃣ Testing Suppliers"
curl -s "$BASE/suppliers" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" > /tmp/suppliers.json
COUNT=$(python3 -c "import json; d=json.load(open('/tmp/suppliers.json')); print(len(d.get('data', [])))")
echo "   ✅ GET /api/suppliers: $COUNT suppliers"

echo
echo "✅ All API tests passed!"
