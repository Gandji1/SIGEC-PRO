#!/bin/bash
# SIGEC v1.0 - Script de Démarrage Rapide

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}"
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║       SIGEC v1.0 - Démarrage du Système Complet              ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo -e "${NC}\n"

# Vérifier les dépendances
echo -e "${YELLOW}Vérification des dépendances...${NC}"

if ! command -v node &> /dev/null; then
    echo -e "${RED}✗ Node.js n'est pas installé${NC}"
    exit 1
fi

if ! command -v python3 &> /dev/null; then
    echo -e "${RED}✗ Python 3 n'est pas installé${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Node.js${NC} : $(node --version)"
echo -e "${GREEN}✓ Python 3${NC} : $(python3 --version)\n"

# Changer le répertoire
cd /workspaces/SIGEC

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${YELLOW}Démarrage des services...${NC}\n"

# Vérifier si les ports sont libres
echo "Vérification des ports..."

if lsof -i :8000 > /dev/null 2>&1; then
    echo -e "${YELLOW}! Le port 8000 est déjà utilisé. Arrêt du processus existant...${NC}"
    pkill -f "node mock-api" || true
    sleep 1
fi

if lsof -i :6666 > /dev/null 2>&1; then
    echo -e "${YELLOW}! Le port 6666 est déjà utilisé. Arrêt du processus existant...${NC}"
    pkill -f "python.*http.server.*6666" || true
    sleep 1
fi

# Démarrer le Mock API Server
echo -e "\n${BLUE}[1/3]${NC} Démarrage du Mock API Server..."
node mock-api.js > /tmp/mock-api.log 2>&1 &
MOCK_API_PID=$!
echo "      PID: $MOCK_API_PID"

# Attendre que le serveur soit prêt
sleep 2

if ! lsof -i :8000 > /dev/null 2>&1; then
    echo -e "${RED}✗ Échec du démarrage du Mock API Server${NC}"
    cat /tmp/mock-api.log
    exit 1
fi
echo -e "${GREEN}✓ Mock API Server${NC} : http://localhost:8000/api"

# Démarrer le serveur HTTP
echo -e "\n${BLUE}[2/3]${NC} Démarrage du serveur HTTP..."
python3 -m http.server 6666 > /tmp/http-server.log 2>&1 &
HTTP_SERVER_PID=$!
echo "      PID: $HTTP_SERVER_PID"

# Attendre que le serveur soit prêt
sleep 1

if ! lsof -i :6666 > /dev/null 2>&1; then
    echo -e "${RED}✗ Échec du démarrage du serveur HTTP${NC}"
    cat /tmp/http-server.log
    exit 1
fi
echo -e "${GREEN}✓ Serveur HTTP${NC} : http://localhost:6666"

# Tester les endpoints
echo -e "\n${BLUE}[3/3]${NC} Test des endpoints..."

if curl -s http://localhost:8000/api/health | grep -q '"success":true'; then
    echo -e "${GREEN}✓ API Health Check${NC}"
else
    echo -e "${RED}✗ API Health Check FAILED${NC}"
fi

if curl -s http://localhost:6666/ui-demo.html | grep -q 'DOCTYPE' > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Frontend UI${NC}"
else
    echo -e "${RED}✗ Frontend UI FAILED${NC}"
fi

# Afficher les informations
echo -e "\n${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✓ Tous les services sont démarrés avec succès!${NC}\n"

echo -e "${YELLOW}📍 URLs d'Accès:${NC}"
echo "   • Interface Principale : ${GREEN}http://localhost:6666/ui-demo.html${NC}"
echo "   • Test Console        : ${GREEN}http://localhost:6666/test-api.html${NC}"
echo "   • API Backend         : ${GREEN}http://localhost:8000/api${NC}"
echo ""

echo -e "${YELLOW}🔐 Identifiants de Test:${NC}"
echo "   Email    : ${GREEN}demo@sigec.com${NC}"
echo "   Mot passe: ${GREEN}password123${NC}"
echo ""

echo -e "${YELLOW}📊 Services en Arrière Plan:${NC}"
echo "   • Mock API Server (PID $MOCK_API_PID) : Port 8000"
echo "   • HTTP Server (PID $HTTP_SERVER_PID)   : Port 6666"
echo ""

echo -e "${YELLOW}🧪 Tests Disponibles:${NC}"
echo "   • bash /workspaces/SIGEC/test-integration.sh"
echo ""

echo -e "${YELLOW}⚠️  Pour arrêter les services:${NC}"
echo "   • kill $MOCK_API_PID"
echo "   • kill $HTTP_SERVER_PID"
echo "   ou"
echo "   • bash /workspaces/SIGEC/stop-services.sh"
echo ""

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✨ SIGEC v1.0 est prêt à l'emploi!${NC}\n"

# Garder les processus actifs
wait
