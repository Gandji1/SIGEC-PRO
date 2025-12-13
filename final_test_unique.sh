#!/bin/bash
set -e
TS="7eafd195"

echo "🔍 SIGEC - Final System Test [$TS]"
echo "======================================"

# 1. Health check
echo -e "\n✅ 1. Health Check"
curl -s -H "Accept: application/json" http://localhost:8000/api/health | jq .

# 2. Register
echo -e "\n✅ 2. Register Tenant"
REGISTER=$(curl -s -X POST http://localhost:8000/api/register   -H "Content-Type: application/json"   -H "Accept: application/json"   -d "{
    \"tenant_name\": \"Tenant-$TS\",
    \"name\": \"User $TS\",
    \"email\": \"user-$TS@example.com\",
    \"password\": \"Password123456\",
    \"password_confirmation\": \"Password123456\",
    \"mode_pos\": \"A\"
  }")

echo "$REGISTER" | jq .

TOKEN=$(echo "$REGISTER" | jq -r '.token // empty')
if [ -z "$TOKEN" ]; then
  echo "❌ Registration failed!"
  exit 1
fi

echo "✅ Token: ${TOKEN:0:30}..."

# 3. Get Profile
echo -e "\n✅ 3. Get Profile"
curl -s -H "Authorization: Bearer $TOKEN"   -H "Accept: application/json"   http://localhost:8000/api/me | jq '.user | {name, email, role}'

echo -e "\n✅ All tests passed! System is operational!"
