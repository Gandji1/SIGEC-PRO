#!/bin/bash

# Script pour démarrer à la fois le serveur mock API et le frontend Next.js

echo "🚀 Démarrage de SIGEC (Mock API + Frontend Next.js)..."
echo ""

# Vérifier les dépendances
if ! command -v node &> /dev/null; then
    echo "❌ Node.js n'est pas installé"
    exit 1
fi

# Créer un dossier pour les logs
mkdir -p logs

echo "📦 Installation des dépendances..."
npm install > /dev/null 2>&1

# Démarrer le serveur mock API en arrière-plan
echo "🔌 Démarrage du serveur Mock API sur le port 8000..."
node mock-api.js > logs/mock-api.log 2>&1 &
MOCK_API_PID=$!
echo "✓ Mock API démarré (PID: $MOCK_API_PID)"

# Attendre que le serveur mock soit prêt
sleep 2

# Démarrer le frontend Next.js en arrière-plan
echo "⚡ Démarrage du frontend Next.js sur le port 3000..."
npm run dev > logs/next-dev.log 2>&1 &
NEXT_PID=$!
echo "✓ Frontend démarré (PID: $NEXT_PID)"

echo ""
echo "✨ SIGEC est maintenant disponible !"
echo "🌐 Frontend: http://localhost:3000"
echo "🔌 Mock API: http://localhost:8000"
echo ""
echo "📝 Logs:"
echo "   - Mock API: tail -f logs/mock-api.log"
echo "   - Frontend: tail -f logs/next-dev.log"
echo ""
echo "⏹️  Pour arrêter:"
echo "   - Appuyez sur Ctrl+C"
echo ""

# Fonction pour nettoyer les processus à l'arrêt
cleanup() {
    echo ""
    echo "🛑 Arrêt de SIGEC..."
    kill $MOCK_API_PID 2>/dev/null
    kill $NEXT_PID 2>/dev/null
    echo "✓ Services arrêtés"
    exit 0
}

trap cleanup SIGINT

# Garder le script actif
wait
