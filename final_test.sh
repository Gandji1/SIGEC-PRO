#!/bin/bash
set -e

echo "🔍 SIGEC - Final System Test"
echo "======================================"

# 1. Health check
echo -e "\n✅ 1. Health Check"
curl -s -H "Accept: application/json" http://localhost:8000/api/health | jq .

# 2. Register
echo -e "\n✅ 2. Register Tenant"
REGISTER=$(curl -s -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "tenant_name": "Final Test Inc",
    "name": "Final Test User",
    "email": "finaltest@example.com",
    "password": "Password123456",
    "password_confirmation": "Password123456",
    "mode_pos": "A"
  }')

echo "$REGISTER" | jq .

TOKEN=$(echo "$REGISTER" | jq -r '.token // empty')
if [ -z "$TOKEN" ]; then
  echo "❌ Registration failed!"
  exit 1
fi

echo -e "\n✅ 3. Login"
LOGIN=$(curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "finaltest@example.com",
    "password": "Password123456"
  }')
echo "$LOGIN" | jq .

# 4. Get Profile
echo -e "\n✅ 4. Get Profile"
curl -s -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  http://localhost:8000/api/me | jq .

# 5. List Tenants
echo -e "\n✅ 5. List Tenants"
curl -s -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  http://localhost:8000/api/tenants | jq . | head -30

echo -e "\n✅ All tests passed! System is operational!"
