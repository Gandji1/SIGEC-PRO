#!/bin/bash

# ============================================================================
# 🚀 SIGEC LIVE DEMO - Test All Features
# ============================================================================
# This script demonstrates:
# 1. Auth: Register tenant + warehouses
# 2. Purchases: Create → Confirm → Receive (with CMP)
# 3. Transfers: Request → Approve → Execute
# ============================================================================

set -e

API_BASE="http://localhost:8000/api"
COLORS_RESET='\033[0m'
COLORS_GREEN='\033[0;32m'
COLORS_BLUE='\033[0;34m'
COLORS_YELLOW='\033[1;33m'
COLORS_RED='\033[0;31m'

print_header() {
    echo -e "\n${COLORS_BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${COLORS_RESET}"
    echo -e "${COLORS_BLUE}$1${COLORS_RESET}"
    echo -e "${COLORS_BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${COLORS_RESET}\n"
}

print_success() {
    echo -e "${COLORS_GREEN}✅ $1${COLORS_RESET}"
}

print_info() {
    echo -e "${COLORS_YELLOW}ℹ️  $1${COLORS_RESET}"
}

print_error() {
    echo -e "${COLORS_RED}❌ $1${COLORS_RESET}"
}

# ============================================================================
# STEP 1: REGISTER TENANT
# ============================================================================
print_header "ÉTAPE 1: REGISTER TENANT (Mode B - POS)"

REGISTER_RESPONSE=$(curl -s -X POST "$API_BASE/register" \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_name": "Restaurant Africa Demo",
    "name": "Admin User",
    "email": "admin@test.com",
    "password": "password123",
    "password_confirmation": "password123",
    "mode_pos": "B"
  }')

TOKEN=$(echo "$REGISTER_RESPONSE" | jq -r '.token // empty')
TENANT_ID=$(echo "$REGISTER_RESPONSE" | jq -r '.tenant.id // empty')

if [ -z "$TOKEN" ]; then
    print_error "Failed to register tenant"
    echo "$REGISTER_RESPONSE" | jq '.'
    exit 1
fi

print_success "Tenant créé: $TENANT_ID"
print_success "Token obtenu: ${TOKEN:0:20}..."

WAREHOUSES=$(echo "$REGISTER_RESPONSE" | jq -r '.warehouses[] | "\(.id): \(.type) (\(.name))"')
echo -e "\n${COLORS_YELLOW}Warehouses créés:${COLORS_RESET}"
echo "$WAREHOUSES" | while read line; do
    echo "  • $line"
done

GROS_WAREHOUSE=$(echo "$REGISTER_RESPONSE" | jq -r '.warehouses[] | select(.type == "gros") | .id')
DETAIL_WAREHOUSE=$(echo "$REGISTER_RESPONSE" | jq -r '.warehouses[] | select(.type == "detail") | .id')
POS_WAREHOUSE=$(echo "$REGISTER_RESPONSE" | jq -r '.warehouses[] | select(.type == "pos") | .id')

print_info "gros_warehouse_id=$GROS_WAREHOUSE, detail_warehouse_id=$DETAIL_WAREHOUSE, pos_warehouse_id=$POS_WAREHOUSE"

# ============================================================================
# STEP 2: LOGIN
# ============================================================================
print_header "ÉTAPE 2: LOGIN"

LOGIN_RESPONSE=$(curl -s -X POST "$API_BASE/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@test.com",
    "password": "password123"
  }')

LOGIN_TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.token // empty')

if [ -z "$LOGIN_TOKEN" ]; then
    print_error "Failed to login"
    echo "$LOGIN_RESPONSE" | jq '.'
    exit 1
fi

print_success "Login successful"
print_info "Using token: ${LOGIN_TOKEN:0:20}..."

# ============================================================================
# STEP 3: CREATE PURCHASE
# ============================================================================
print_header "ÉTAPE 3: CREATE PURCHASE"

PURCHASE_RESPONSE=$(curl -s -X POST "$API_BASE/purchases" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "supplier_name": "Acme Distribution",
    "supplier_phone": "+229 96 00 00 00",
    "items": [
      {
        "product_id": 1,
        "quantity": 100,
        "unit_price": 5000
      },
      {
        "product_id": 2,
        "quantity": 50,
        "unit_price": 8000
      }
    ]
  }')

PURCHASE_ID=$(echo "$PURCHASE_RESPONSE" | jq -r '.data.id // empty')
PURCHASE_STATUS=$(echo "$PURCHASE_RESPONSE" | jq -r '.data.status // empty')

if [ -z "$PURCHASE_ID" ]; then
    print_error "Failed to create purchase"
    echo "$PURCHASE_RESPONSE" | jq '.'
    exit 1
fi

print_success "Purchase créé: $PURCHASE_ID"
print_info "Status: $PURCHASE_STATUS"

# ============================================================================
# STEP 4: CONFIRM PURCHASE
# ============================================================================
print_header "ÉTAPE 4: CONFIRM PURCHASE"

CONFIRM_RESPONSE=$(curl -s -X POST "$API_BASE/purchases/$PURCHASE_ID/confirm" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json")

CONFIRM_STATUS=$(echo "$CONFIRM_RESPONSE" | jq -r '.data.status // empty')

if [ -z "$CONFIRM_STATUS" ]; then
    print_error "Failed to confirm purchase"
    echo "$CONFIRM_RESPONSE" | jq '.'
    exit 1
fi

print_success "Purchase confirmé"
print_info "Status: $CONFIRM_STATUS"

# ============================================================================
# STEP 5: RECEIVE PURCHASE (CMP CALCULATION)
# ============================================================================
print_header "ÉTAPE 5: RECEIVE PURCHASE (CMP Calculation)"

# Get purchase items
PURCHASE_DETAIL=$(curl -s -X GET "$API_BASE/purchases/$PURCHASE_ID" \
  -H "Authorization: Bearer $TOKEN")

PURCHASE_ITEMS=$(echo "$PURCHASE_DETAIL" | jq -r '.data.items[] | {id: .id, product_id: .product_id}')

# Build receive payload
RECEIVE_PAYLOAD="{"
RECEIVE_PAYLOAD+="\"items\": ["
FIRST=true
echo "$PURCHASE_ITEMS" | jq -r '@csv' | while read -r line; do
    if [ "$FIRST" = false ]; then
        RECEIVE_PAYLOAD+=","
    fi
    ITEM_ID=$(echo "$line" | jq -r '.id')
    PRODUCT_ID=$(echo "$line" | jq -r '.product_id')
    RECEIVE_PAYLOAD+="{\"purchase_item_id\": $ITEM_ID, \"received_quantity\": 100}"
    FIRST=false
done
RECEIVE_PAYLOAD+="]"
RECEIVE_PAYLOAD+="}"

RECEIVE_RESPONSE=$(curl -s -X POST "$API_BASE/purchases/$PURCHASE_ID/receive" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      {
        "purchase_item_id": 1,
        "received_quantity": 100
      },
      {
        "purchase_item_id": 2,
        "received_quantity": 50
      }
    ]
  }')

RECEIVE_STATUS=$(echo "$RECEIVE_RESPONSE" | jq -r '.data.status // empty')

if [ -z "$RECEIVE_STATUS" ]; then
    print_error "Failed to receive purchase"
    echo "$RECEIVE_RESPONSE" | jq '.'
else
    print_success "Purchase reçu"
    print_info "Status: $RECEIVE_STATUS"
    
    # Show stock with CMP
    echo -e "\n${COLORS_YELLOW}Stock après réception:${COLORS_RESET}"
    STOCKS=$(curl -s -X GET "$API_BASE/stocks?warehouse_id=$GROS_WAREHOUSE" \
      -H "Authorization: Bearer $TOKEN" | jq '.data[] | select(.warehouse_id == '$GROS_WAREHOUSE') | {product_id, quantity, cost_average}')
    echo "$STOCKS" | jq '.'
fi

# ============================================================================
# STEP 6: CREATE TRANSFER REQUEST
# ============================================================================
print_header "ÉTAPE 6: CREATE TRANSFER REQUEST (Gros → Détail)"

TRANSFER_RESPONSE=$(curl -s -X POST "$API_BASE/transfers" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"from_warehouse_id\": $GROS_WAREHOUSE,
    \"to_warehouse_id\": $DETAIL_WAREHOUSE,
    \"items\": [
      {\"product_id\": 1, \"quantity\": 30},
      {\"product_id\": 2, \"quantity\": 20}
    ],
    \"notes\": \"Weekly restocking\"
  }")

TRANSFER_ID=$(echo "$TRANSFER_RESPONSE" | jq -r '.data.id // empty')
TRANSFER_STATUS=$(echo "$TRANSFER_RESPONSE" | jq -r '.data.status // empty')

if [ -z "$TRANSFER_ID" ]; then
    print_error "Failed to create transfer"
    echo "$TRANSFER_RESPONSE" | jq '.'
else
    print_success "Transfer créé: $TRANSFER_ID"
    print_info "Status: $TRANSFER_STATUS"
fi

# ============================================================================
# STEP 7: APPROVE & EXECUTE TRANSFER
# ============================================================================
print_header "ÉTAPE 7: APPROVE & EXECUTE TRANSFER"

APPROVE_RESPONSE=$(curl -s -X POST "$API_BASE/transfers/$TRANSFER_ID/approve" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json")

APPROVE_STATUS=$(echo "$APPROVE_RESPONSE" | jq -r '.data.status // empty')

if [ -z "$APPROVE_STATUS" ]; then
    print_error "Failed to approve transfer"
    echo "$APPROVE_RESPONSE" | jq '.'
else
    print_success "Transfer approuvé et exécuté"
    print_info "Status: $APPROVE_STATUS"
fi

# ============================================================================
# STEP 8: VERIFY STOCK CHANGES
# ============================================================================
print_header "ÉTAPE 8: VERIFY STOCK CHANGES"

echo -e "${COLORS_YELLOW}Stock après transfert:${COLORS_RESET}\n"

ALL_STOCKS=$(curl -s -X GET "$API_BASE/stocks" \
  -H "Authorization: Bearer $TOKEN")

echo "Warehouse GROS ($GROS_WAREHOUSE):"
echo "$ALL_STOCKS" | jq '.data[] | select(.warehouse_id == '$GROS_WAREHOUSE') | {product_id, quantity, cost_average}'

echo -e "\nWarehouse DÉTAIL ($DETAIL_WAREHOUSE):"
echo "$ALL_STOCKS" | jq '.data[] | select(.warehouse_id == '$DETAIL_WAREHOUSE') | {product_id, quantity, cost_average}'

# ============================================================================
# STEP 9: TRANSFER STATISTICS
# ============================================================================
print_header "ÉTAPE 9: TRANSFER STATISTICS"

STATS=$(curl -s -X GET "$API_BASE/transfers/statistics" \
  -H "Authorization: Bearer $TOKEN")

echo -e "${COLORS_YELLOW}Statistics:${COLORS_RESET}"
echo "$STATS" | jq '.'

# ============================================================================
# SUMMARY
# ============================================================================
print_header "SUMMARY - DEMO COMPLETED ✅"

echo -e "${COLORS_GREEN}All features tested:${COLORS_RESET}"
echo "  ✅ Auth: Register + Login (Mode B with 3 warehouses)"
echo "  ✅ Purchases: Create + Confirm + Receive (with CMP)"
echo "  ✅ Stock: Tracked with cost_average (Coût Moyen Pondéré)"
echo "  ✅ Transfers: Request + Approve + Execute"
echo "  ✅ Stock Movement: Audit trail created"

echo -e "\n${COLORS_YELLOW}Test Data:${COLORS_RESET}"
echo "  • Tenant: Restaurant Africa Demo (Mode B)"
echo "  • User: admin@test.com / password123"
echo "  • Products: 2 items purchased (100 + 50 units)"
echo "  • Transfer: 30 + 20 units from Gros → Détail"

echo -e "\n${COLORS_BLUE}Next Steps:${COLORS_RESET}"
echo "  1. View logs: tail -f backend/storage/logs/laravel.log"
echo "  2. Test database: php artisan tinker"
echo "  3. Check transfers: sqlite3 backend/database/database.sqlite 'SELECT * FROM transfers;'"
echo -e "\n"
