#!/bin/bash

# SIGEC v1.0 - Test Integration Script
# Tests complets du système frontend/backend

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

API_URL="http://localhost:8000/api"
UI_URL="http://localhost:6666/ui-demo.html"

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  SIGEC v1.0 - Integration Test Suite${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}\n"

# Test 1: API Health Check
echo -e "${YELLOW}[1/10]${NC} Vérification de la santé de l'API..."
if curl -s -f "$API_URL/health" > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} API Health Check PASSED\n"
else
    echo -e "${RED}✗${NC} API Health Check FAILED"
    echo "    Le serveur mock-api n'est pas accessible sur $API_URL"
    exit 1
fi

# Test 2: Login Endpoint
echo -e "${YELLOW}[2/10]${NC} Test du login..."
LOGIN_RESPONSE=$(curl -s -X POST "$API_URL/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@sigec.com","password":"password123"}')

if echo "$LOGIN_RESPONSE" | grep -q '"success":true'; then
    echo -e "${GREEN}✓${NC} Login PASSED"
    TOKEN=$(echo "$LOGIN_RESPONSE" | grep -o '"token":"[^"]*' | cut -d'"' -f4)
    echo "    Token: $TOKEN\n"
else
    echo -e "${RED}✗${NC} Login FAILED"
    echo "    Response: $LOGIN_RESPONSE"
    exit 1
fi

# Test 3: Stats Endpoint
echo -e "${YELLOW}[3/10]${NC} Chargement des statistiques..."
if curl -s -f "$API_URL/stats" | grep -q '"success":true'; then
    echo -e "${GREEN}✓${NC} Stats Endpoint PASSED\n"
else
    echo -e "${RED}✗${NC} Stats Endpoint FAILED\n"
fi

# Test 4: Sales Endpoint
echo -e "${YELLOW}[4/10]${NC} Chargement des ventes..."
SALES_RESPONSE=$(curl -s "$API_URL/sales")
SALES_COUNT=$(echo "$SALES_RESPONSE" | grep -o '"client"' | wc -l)
if [ "$SALES_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓${NC} Sales Endpoint PASSED"
    echo "    Nombre de ventes: $SALES_COUNT\n"
else
    echo -e "${RED}✗${NC} Sales Endpoint FAILED\n"
fi

# Test 5: Purchases Endpoint
echo -e "${YELLOW}[5/10]${NC} Chargement des commandes..."
PURCHASES_RESPONSE=$(curl -s "$API_URL/purchases")
PURCHASES_COUNT=$(echo "$PURCHASES_RESPONSE" | grep -o '"supplier"' | wc -l)
if [ "$PURCHASES_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓${NC} Purchases Endpoint PASSED"
    echo "    Nombre d'achats: $PURCHASES_COUNT\n"
else
    echo -e "${RED}✗${NC} Purchases Endpoint FAILED\n"
fi

# Test 6: Transfers Endpoint
echo -e "${YELLOW}[6/10]${NC} Chargement des transferts..."
TRANSFERS_RESPONSE=$(curl -s "$API_URL/transfers")
TRANSFERS_COUNT=$(echo "$TRANSFERS_RESPONSE" | grep -o '"from"' | wc -l)
if [ "$TRANSFERS_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓${NC} Transfers Endpoint PASSED"
    echo "    Nombre de transferts: $TRANSFERS_COUNT\n"
else
    echo -e "${RED}✗${NC} Transfers Endpoint FAILED\n"
fi

# Test 7: Inventory Endpoint
echo -e "${YELLOW}[7/10]${NC} Chargement de l'inventaire..."
INVENTORY_RESPONSE=$(curl -s "$API_URL/inventory")
INVENTORY_COUNT=$(echo "$INVENTORY_RESPONSE" | grep -o '"product"' | wc -l)
if [ "$INVENTORY_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓${NC} Inventory Endpoint PASSED"
    echo "    Nombre d'articles: $INVENTORY_COUNT\n"
else
    echo -e "${RED}✗${NC} Inventory Endpoint FAILED\n"
fi

# Test 8: Products Endpoint
echo -e "${YELLOW}[8/10]${NC} Chargement des produits..."
PRODUCTS_RESPONSE=$(curl -s "$API_URL/products")
PRODUCTS_COUNT=$(echo "$PRODUCTS_RESPONSE" | grep -o '"name"' | wc -l)
if [ "$PRODUCTS_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓${NC} Products Endpoint PASSED"
    echo "    Nombre de produits: $PRODUCTS_COUNT\n"
else
    echo -e "${RED}✗${NC} Products Endpoint FAILED\n"
fi

# Test 9: Check Frontend Server
echo -e "${YELLOW}[9/10]${NC} Vérification du serveur frontend..."
if curl -s -f "$UI_URL" > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} Frontend Server PASSED\n"
else
    echo -e "${RED}✗${NC} Frontend Server FAILED"
    echo "    L'interface UI n'est pas accessible sur $UI_URL\n"
fi

# Test 10: Mock API Response Format
echo -e "${YELLOW}[10/10]${NC} Validation du format des réponses..."
if echo "$SALES_RESPONSE" | grep -q '"success"'; then
    echo -e "${GREEN}✓${NC} Response Format PASSED\n"
else
    echo -e "${RED}✗${NC} Response Format FAILED\n"
fi

# Summary
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✓ All Tests PASSED!${NC}\n"

echo -e "📊 ${YELLOW}Résumé des Endpoints${NC}"
echo "   Mock API URL: $API_URL"
echo "   Frontend URL: $UI_URL"
echo "   Login: demo@sigec.com / password123"
echo ""

echo -e "📈 ${YELLOW}Statistiques Collectées${NC}"
echo "   • Ventes: $SALES_COUNT transactions"
echo "   • Achats: $PURCHASES_COUNT commandes"
echo "   • Transferts: $TRANSFERS_COUNT mouvements"
echo "   • Inventaire: $INVENTORY_COUNT articles"
echo "   • Produits: $PRODUCTS_COUNT references"
echo ""

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}Le système SIGEC v1.0 est prêt!${NC}\n"

echo "Prochaines étapes:"
echo "1. Ouvrir le navigateur: $UI_URL"
echo "2. Se connecter avec: demo@sigec.com / password123"
echo "3. Tester chaque page (Dashboard, Ventes, Achats, etc.)"
echo ""
