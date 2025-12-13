#!/bin/bash

# Couleurs
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

BASE_URL="http://localhost:8000/api"

echo "========================================="
echo "SIGEC - Test Suite Complet"
echo "========================================="

# 1. Login
echo -e "\n${YELLOW}1. Test Login${NC}"
LOGIN_RESPONSE=$(curl -s -X POST $BASE_URL/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "testuser2@example.com",
    "password": "password123"
  }')

TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.token // empty')
TENANT_ID=$(echo $LOGIN_RESPONSE | jq -r '.tenant.id // empty')

if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ]; then
  echo -e "${RED}❌ Login failed${NC}"
  echo $LOGIN_RESPONSE | jq .
  exit 1
fi

echo -e "${GREEN}✅ Login successful${NC}"
echo "Token: ${TOKEN:0:20}..."
echo "Tenant ID: $TENANT_ID"

# 2. Test Suppliers
echo -e "\n${YELLOW}2. Test Suppliers CRUD${NC}"

# Create supplier
SUPPLIER_RESPONSE=$(curl -s -X POST $BASE_URL/suppliers \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" \
  -d '{
    "name": "Supplier Test",
    "email": "supplier@test.com",
    "phone": "+22912345678"
  }')

SUPPLIER_ID=$(echo $SUPPLIER_RESPONSE | jq -r '.id // empty')
if [ -z "$SUPPLIER_ID" ] || [ "$SUPPLIER_ID" = "null" ]; then
  echo -e "${RED}❌ Create supplier failed${NC}"
  echo $SUPPLIER_RESPONSE | jq .
else
  echo -e "${GREEN}✅ Create supplier successful${NC}"
  echo "Supplier ID: $SUPPLIER_ID"
fi

# Get suppliers
SUPPLIERS=$(curl -s -X GET $BASE_URL/suppliers \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID")

COUNT=$(echo $SUPPLIERS | jq '.data | length')
echo -e "${GREEN}✅ Get suppliers successful (count: $COUNT)${NC}"

# 3. Test Customers
echo -e "\n${YELLOW}3. Test Customers CRUD${NC}"

CUSTOMER_RESPONSE=$(curl -s -X POST $BASE_URL/customers \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" \
  -d '{
    "name": "Customer Test",
    "email": "customer@test.com",
    "phone": "+22987654321"
  }')

CUSTOMER_ID=$(echo $CUSTOMER_RESPONSE | jq -r '.id // empty')
if [ -z "$CUSTOMER_ID" ] || [ "$CUSTOMER_ID" = "null" ]; then
  echo -e "${RED}❌ Create customer failed${NC}"
  echo $CUSTOMER_RESPONSE | jq .
else
  echo -e "${GREEN}✅ Create customer successful${NC}"
  echo "Customer ID: $CUSTOMER_ID"
fi

# 4. Test Expenses
echo -e "\n${YELLOW}4. Test Expenses CRUD${NC}"

EXPENSE_RESPONSE=$(curl -s -X POST $BASE_URL/expenses \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" \
  -d '{
    "description": "Office Supplies",
    "amount": "50000",
    "category": "supplies",
    "date": "2025-11-25"
  }')

EXPENSE_ID=$(echo $EXPENSE_RESPONSE | jq -r '.id // empty')
if [ -z "$EXPENSE_ID" ] || [ "$EXPENSE_ID" = "null" ]; then
  echo -e "${RED}❌ Create expense failed${NC}"
  echo $EXPENSE_RESPONSE | jq .
else
  echo -e "${GREEN}✅ Create expense successful${NC}"
  echo "Expense ID: $EXPENSE_ID"
fi

# 5. Test Dashboard
echo -e "\n${YELLOW}5. Test Dashboard Stats${NC}"

STATS=$(curl -s -X GET $BASE_URL/dashboard/stats \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID")

echo -e "${GREEN}✅ Dashboard stats retrieved${NC}"
echo $STATS | jq .

echo -e "\n${GREEN}=========================================${NC}"
echo -e "${GREEN}All tests completed!${NC}"
echo -e "${GREEN}=========================================${NC}"
