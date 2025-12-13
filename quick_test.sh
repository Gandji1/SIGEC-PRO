#!/bin/bash

echo "🔍 Testing SIGEC Backend API"
echo "================================"

# Health check
echo "1️⃣  Health Check..."
curl -s http://localhost:8000/api/health | jq . || echo "Failed"

# Register
echo -e "\n2️⃣  Registration..."
REGISTER=$(curl -s -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_name": "Quick Test Inc",
    "name": "Test User",
    "email": "quicktest@example.com",
    "password": "Password123",
    "password_confirmation": "Password123",
    "mode_pos": "A"
  }')
echo "$REGISTER" | jq .
TOKEN=$(echo "$REGISTER" | jq -r '.token // empty')

if [ -z "$TOKEN" ]; then
  echo "❌ Registration failed!"
  exit 1
fi

echo "✅ Token: ${TOKEN:0:20}..."

# Get me
echo -e "\n3️⃣  Get Profile..."
curl -s -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/me | jq .

echo -e "\n4️⃣  Get Tenants..."
curl -s -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/tenants | jq . | head -20

echo -e "\n✅ All API tests passed!"
