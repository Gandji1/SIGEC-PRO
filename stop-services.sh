#!/bin/bash
# SIGEC v1.0 - Script d'Arrêt des Services

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}╔═══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║       SIGEC v1.0 - Arrêt des Services                        ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════════════╝${NC}\n"

# Arrêter le Mock API
echo -e "${YELLOW}Arrêt du Mock API Server...${NC}"
if pkill -f "node mock-api"; then
    echo -e "${GREEN}✓ Mock API Server arrêté${NC}\n"
else
    echo -e "${RED}✗ Mock API Server introuvable${NC}\n"
fi

# Arrêter le serveur HTTP
echo -e "${YELLOW}Arrêt du serveur HTTP...${NC}"
if pkill -f "python.*http.server.*6666"; then
    echo -e "${GREEN}✓ Serveur HTTP arrêté${NC}\n"
else
    echo -e "${RED}✗ Serveur HTTP introuvable${NC}\n"
fi

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✓ Tous les services ont été arrêtés${NC}\n"
