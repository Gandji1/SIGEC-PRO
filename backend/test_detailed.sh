#!/bin/bash

# Script de test DÉTAILLÉ pour Phase 1
# Test complet: Tenant Config, Payments, Collaborators, Expenses

BASE_URL="http://localhost:8000/api"
TOKEN=""

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Helper functions
log_test() {
    echo -e "${BLUE}▶ $1${NC}"
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
}

error() {
    echo -e "${RED}❌ $1${NC}"
}

# LOGIN FIRST
echo -e "${YELLOW}═══════════════════════════════════════${NC}"
echo -e "${YELLOW}🔐 ÉTAPE 0: Connexion${NC}"
echo -e "${YELLOW}═══════════════════════════════════════${NC}"

LOGIN=$(curl -s -X POST "$BASE_URL/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@demo.local","password":"password"}')

TOKEN=$(echo "$LOGIN" | grep -o '"token":"[^"]*' | cut -d'"' -f4)
if [ -z "$TOKEN" ]; then
    error "Impossible de se connecter"
    exit 1
fi
success "Connecté avec admin@demo.local"
echo ""

# ═══════════════════════════════════════
# TEST 1: TENANT-CONFIG
# ═══════════════════════════════════════
echo -e "${YELLOW}═══════════════════════════════════════${NC}"
echo -e "${YELLOW}📋 TEST 1: TENANT-CONFIG (GET/PUT)${NC}"
echo -e "${YELLOW}═══════════════════════════════════════${NC}"

log_test "GET /tenant-config"
CONFIG=$(curl -s -X GET "$BASE_URL/tenant-config" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json")

if echo "$CONFIG" | grep -q '"tva_rate"'; then
    TVA=$(echo "$CONFIG" | grep -o '"tva_rate":[0-9.]*' | cut -d':' -f2)
    success "Lecture config OK (TVA: $TVA)"
else
    error "Lecture config échouée"
fi
echo ""

log_test "PUT /tenant-config (mise à jour TVA à 20%)"
UPDATE=$(curl -s -X PUT "$BASE_URL/tenant-config" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"tva_rate": 20, "default_markup": 35}')

if echo "$UPDATE" | grep -q '"tva_rate":20'; then
    success "Mise à jour config OK"
else
    error "Mise à jour config échouée: $UPDATE"
fi
echo ""

log_test "Vérifier la mise à jour avec GET"
VERIFY=$(curl -s -X GET "$BASE_URL/tenant-config" \
  -H "Authorization: Bearer $TOKEN")

if echo "$VERIFY" | grep -q '"tva_rate":20'; then
    success "Vérification OK - TVA mise à jour"
else
    error "Vérification échouée"
fi
echo ""

# ═══════════════════════════════════════
# TEST 2: PAYMENT METHODS
# ═══════════════════════════════════════
echo -e "${YELLOW}═══════════════════════════════════════${NC}"
echo -e "${YELLOW}💳 TEST 2: PAYMENT METHODS${NC}"
echo -e "${YELLOW}═══════════════════════════════════════${NC}"

log_test "GET /tenant-config/payment-methods"
METHODS=$(curl -s -X GET "$BASE_URL/tenant-config/payment-methods" \
  -H "Authorization: Bearer $TOKEN")

if echo "$METHODS" | grep -q '"data"'; then
    success "Lecture moyens de paiement OK"
else
    error "Lecture échouée"
fi
echo ""

log_test "POST /tenant-config/payment-methods (créer Kkiapay)"
CREATE_PAYMENT=$(curl -s -X POST "$BASE_URL/tenant-config/payment-methods" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "kkiapay",
    "name": "Kkiapay Test",
    "is_active": true,
    "api_key": "test_key_12345",
    "api_secret": "test_secret_12345"
  }')

if echo "$CREATE_PAYMENT" | grep -q '"kkiapay"'; then
    success "Création Kkiapay OK"
else
    error "Création Kkiapay échouée: $CREATE_PAYMENT"
fi
echo ""

log_test "POST /tenant-config/payment-methods (créer FedaPay)"
CREATE_FEDAPAY=$(curl -s -X POST "$BASE_URL/tenant-config/payment-methods" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "fedapay",
    "name": "FedaPay Test",
    "is_active": true,
    "api_key": "test_fedapay_key",
    "api_secret": "test_fedapay_secret"
  }')

if echo "$CREATE_FEDAPAY" | grep -q '"fedapay"'; then
    success "Création FedaPay OK"
else
    error "Création FedaPay échouée"
fi
echo ""

# ═══════════════════════════════════════
# TEST 3: COLLABORATORS
# ═══════════════════════════════════════
echo -e "${YELLOW}═══════════════════════════════════════${NC}"
echo -e "${YELLOW}👥 TEST 3: COLLABORATORS (CRUD)${NC}"
echo -e "${YELLOW}═══════════════════════════════════════${NC}"

log_test "GET /collaborators"
COLLABS=$(curl -s -X GET "$BASE_URL/collaborators" \
  -H "Authorization: Bearer $TOKEN")

if echo "$COLLABS" | grep -q '"data"'; then
    COUNT=$(echo "$COLLABS" | grep -o '"id":[0-9]*' | wc -l)
    success "Lecture collaborateurs OK ($COUNT collaborateurs)"
else
    error "Lecture échouée"
fi
echo ""

log_test "POST /collaborators (créer nouveau collaborateur)"
CREATE_COLLAB=$(curl -s -X POST "$BASE_URL/collaborators" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jean Dupont",
    "email": "jean.dupont@demo.local",
    "phone": "+221 77 666 66 66",
    "role": "magasinier_gros"
  }')

if echo "$CREATE_COLLAB" | grep -q '"email":"jean.dupont'; then
    NEW_COLLAB_ID=$(echo "$CREATE_COLLAB" | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
    success "Création collaborateur OK (ID: $NEW_COLLAB_ID)"
else
    error "Création échouée: $CREATE_COLLAB"
    NEW_COLLAB_ID=""
fi
echo ""

if [ ! -z "$NEW_COLLAB_ID" ]; then
    log_test "PUT /collaborators/{id} (mettre à jour collaborateur)"
    UPDATE_COLLAB=$(curl -s -X PUT "$BASE_URL/collaborators/$NEW_COLLAB_ID" \
      -H "Authorization: Bearer $TOKEN" \
      -H "Content-Type: application/json" \
      -d '{
        "name": "Jean Dupont Updated",
        "phone": "+221 77 777 77 77"
      }')
    
    if echo "$UPDATE_COLLAB" | grep -q '"Jean Dupont Updated"'; then
        success "Mise à jour collaborateur OK"
    else
        error "Mise à jour échouée"
    fi
    echo ""
fi

log_test "GET /collaborators/roles (lister les rôles disponibles)"
ROLES=$(curl -s -X GET "$BASE_URL/collaborators/roles" \
  -H "Authorization: Bearer $TOKEN")

if echo "$ROLES" | grep -q '"owner"'; then
    success "Lecture des rôles OK"
else
    error "Lecture des rôles échouée"
fi
echo ""

# ═══════════════════════════════════════
# TEST 4: EXPENSES
# ═══════════════════════════════════════
echo -e "${YELLOW}═══════════════════════════════════════${NC}"
echo -e "${YELLOW}💰 TEST 4: EXPENSES (CRUD)${NC}"
echo -e "${YELLOW}═══════════════════════════════════════${NC}"

log_test "POST /expenses (créer nouvelle charge)"
CREATE_EXPENSE=$(curl -s -X POST "$BASE_URL/expenses" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "category": "Loyer",
    "description": "Loyer novembre 2025",
    "amount": 500000,
    "is_fixed": true,
    "payment_method": "virement",
    "expense_date": "2025-11-25"
  }')

if echo "$CREATE_EXPENSE" | grep -q '"Loyer"'; then
    EXPENSE_ID=$(echo "$CREATE_EXPENSE" | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
    success "Création charge OK (ID: $EXPENSE_ID)"
else
    error "Création charge échouée: $CREATE_EXPENSE"
    EXPENSE_ID=""
fi
echo ""

log_test "GET /expenses (lister les charges)"
EXPENSES=$(curl -s -X GET "$BASE_URL/expenses" \
  -H "Authorization: Bearer $TOKEN")

if echo "$EXPENSES" | grep -q '"data"'; then
    success "Lecture charges OK"
else
    error "Lecture charges échouée"
fi
echo ""

if [ ! -z "$EXPENSE_ID" ]; then
    log_test "GET /expenses/{id} (afficher une charge)"
    GET_EXPENSE=$(curl -s -X GET "$BASE_URL/expenses/$EXPENSE_ID" \
      -H "Authorization: Bearer $TOKEN")
    
    if echo "$GET_EXPENSE" | grep -q '"Loyer"'; then
        success "Affichage charge OK"
    else
        error "Affichage charge échouée"
    fi
    echo ""

    log_test "PUT /expenses/{id} (mettre à jour charge)"
    UPDATE_EXPENSE=$(curl -s -X PUT "$BASE_URL/expenses/$EXPENSE_ID" \
      -H "Authorization: Bearer $TOKEN" \
      -H "Content-Type: application/json" \
      -d '{
        "amount": 600000,
        "description": "Loyer novembre 2025 - Augmentation"
      }')
    
    if echo "$UPDATE_EXPENSE" | grep -q '600000'; then
        success "Mise à jour charge OK"
    else
        error "Mise à jour charge échouée"
    fi
    echo ""
fi

log_test "GET /expenses/statistics (statistiques)"
STATS=$(curl -s -X GET "$BASE_URL/expenses/statistics" \
  -H "Authorization: Bearer $TOKEN")

if echo "$STATS" | grep -q '"total"'; then
    TOTAL=$(echo "$STATS" | grep -o '"total":[0-9.]*' | cut -d':' -f2)
    success "Statistiques OK (Total: $TOTAL)"
else
    error "Statistiques échouées"
fi
echo ""

# ═══════════════════════════════════════
# RÉSUMÉ
# ═══════════════════════════════════════
echo -e "${YELLOW}═══════════════════════════════════════${NC}"
echo -e "${GREEN}✅ TOUS LES TESTS DÉTAILLÉS COMPLÉTÉS!${NC}"
echo -e "${YELLOW}═══════════════════════════════════════${NC}"
