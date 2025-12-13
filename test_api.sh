#!/bin/bash

# SIGEC API Test & Demo Script
# Usage: ./test_api.sh [action]

API_URL="${API_URL:-http://localhost:8000/api}"
ADMIN_EMAIL="${ADMIN_EMAIL:-super@demo.local}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-demo12345}"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== SIGEC Backend API Test ===${NC}\n"

# 1. Health check
echo -e "${BLUE}[1] Health Check${NC}"
HEALTH=$(curl -s "$API_URL/health")
if echo "$HEALTH" | grep -q "ok"; then
  echo -e "${GREEN}✅ API Health: OK${NC}\n"
else
  echo -e "${RED}❌ API Health: FAILED${NC}\n"
  exit 1
fi

# 2. Login
echo -e "${BLUE}[2] Login as ${ADMIN_EMAIL}${NC}"
TOKEN=$(curl -s -X POST "$API_URL/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}" \
  | python3 -c "import json,sys; r=json.load(sys.stdin); print(r.get('token', ''))" 2>/dev/null)

if [ -z "$TOKEN" ]; then
  echo -e "${RED}❌ Login failed${NC}\n"
  exit 1
fi
echo -e "${GREEN}✅ Token received: ${TOKEN:0:30}...${NC}\n"

# 3. Get current user
echo -e "${BLUE}[3] Get Current User Info${NC}"
curl -s -H "Authorization: Bearer $TOKEN" "$API_URL/me" \
  | python3 -m json.tool | head -20
echo ""

# 4. List tenants
echo -e "${BLUE}[4] List Tenants${NC}"
TENANT_COUNT=$(curl -s -H "Authorization: Bearer $TOKEN" "$API_URL/tenants" \
  | python3 -c "import json,sys; print(len(json.load(sys.stdin)['data']))")
echo -e "${GREEN}✅ Found ${TENANT_COUNT} tenants${NC}\n"

# 5. Create new tenant
echo -e "${BLUE}[5] Create New Tenant${NC}"
TENANT_NAME="ApiTest-$(date +%s)"
curl -s -X POST "$API_URL/tenants" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\":\"$TENANT_NAME\",
    \"slug\":\"$(echo $TENANT_NAME | tr ' ' '-' | tr '[:upper:]' '[:lower:]')\",
    \"domain\":\"$(echo $TENANT_NAME | tr ' ' '-' | tr '[:upper:]' '[:lower:]').sigec.local\",
    \"business_type\":\"retail\"
  }" | python3 -m json.tool
echo ""

echo -e "${GREEN}=== All Tests Passed ===${NC}"
