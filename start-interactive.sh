#!/bin/bash

# 🎯 SIGEC v1.0 - Interactive Quick Start Guide
# Ce script vous guide à travers l'installation et le démarrage de SIGEC

clear

echo "╔════════════════════════════════════════════════════════╗"
echo "║                                                        ║"
echo "║   🔷 BIENVENUE DANS SIGEC v1.0                        ║"
echo "║   Gestion Stocks & Comptabilité                        ║"
echo "║                                                        ║"
echo "╚════════════════════════════════════════════════════════╝"
echo ""

# Vérifier les prérequis
echo "📋 Vérification des prérequis..."
echo ""

if ! command -v node &> /dev/null; then
    echo "❌ Node.js n'est pas installé"
    echo "   Installez Node.js 18+ depuis https://nodejs.org"
    exit 1
else
    NODE_VERSION=$(node -v)
    echo "✅ Node.js $NODE_VERSION trouvé"
fi

if ! command -v npm &> /dev/null; then
    echo "❌ npm n'est pas installé"
    exit 1
else
    NPM_VERSION=$(npm -v)
    echo "✅ npm $NPM_VERSION trouvé"
fi

if ! command -v git &> /dev/null; then
    echo "❌ Git n'est pas installé"
    echo "   Installez Git depuis https://git-scm.com"
    exit 1
else
    GIT_VERSION=$(git --version)
    echo "✅ $GIT_VERSION trouvé"
fi

echo ""
echo "✨ Tous les prérequis sont satisfaits!"
echo ""

# Menu principal
show_menu() {
    echo "╔════════════════════════════════════════════════════════╗"
    echo "║  Que voulez-vous faire ?                              ║"
    echo "╠════════════════════════════════════════════════════════╣"
    echo "║  1. 🚀 Démarrage rapide (Mock API + Frontend)         ║"
    echo "║  2. 💻 Développement frontend uniquement              ║"
    echo "║  3. 🔌 Mock API uniquement                            ║"
    echo "║  4. 🌐 Ouvrir la démo en ligne (Vercel)              ║"
    echo "║  5. 📦 Installer/Réinstaller les dépendances         ║"
    echo "║  6. 🏗️  Compiler pour production                      ║"
    echo "║  7. ✅ Vérifier la configuration                      ║"
    echo "║  8. 📖 Afficher la documentation                      ║"
    echo "║  9. ❌ Quitter                                         ║"
    echo "╚════════════════════════════════════════════════════════╝"
    echo ""
    read -p "Sélectionnez une option (1-9): " choice
}

# Traiter les choix
while true; do
    show_menu
    
    case $choice in
        1)
            echo ""
            echo "🚀 Démarrage complet..."
            echo ""
            echo "Installation des dépendances..."
            npm install --silent
            echo ""
            echo "✨ Services en cours de démarrage:"
            echo "   • Mock API sur http://localhost:8000"
            echo "   • Frontend Next.js sur http://localhost:3000"
            echo ""
            echo "Appuyez sur Ctrl+C pour arrêter"
            echo ""
            sleep 2
            
            # Lancer le script de démarrage
            if [ -f "./start-dev.sh" ]; then
                ./start-dev.sh
            else
                # Démarrage manuel
                node mock-api.js &
                sleep 2
                npm run dev
            fi
            ;;
            
        2)
            echo ""
            echo "💻 Démarrage frontend uniquement..."
            echo ""
            echo "Installation des dépendances..."
            npm install --silent
            echo ""
            echo "✨ Frontend Next.js sur http://localhost:3000"
            echo ""
            echo "Appuyez sur Ctrl+C pour arrêter"
            echo ""
            sleep 2
            npm run dev
            ;;
            
        3)
            echo ""
            echo "🔌 Démarrage Mock API uniquement..."
            echo ""
            echo "Installation des dépendances..."
            npm install --silent
            echo ""
            echo "✨ Mock API sur http://localhost:8000"
            echo ""
            echo "Appuyez sur Ctrl+C pour arrêter"
            echo ""
            sleep 2
            npm run mock-api
            ;;
            
        4)
            echo ""
            echo "🌐 Ouverture de la démo Vercel..."
            echo ""
            if command -v $BROWSER &> /dev/null; then
                $BROWSER https://sigec-pi.vercel.app
            else
                echo "Ouvrez dans votre navigateur: https://sigec-pi.vercel.app"
            fi
            echo ""
            ;;
            
        5)
            echo ""
            echo "📦 Installation/Réinstallation des dépendances..."
            echo ""
            echo "Suppression des dépendances existantes..."
            rm -rf node_modules package-lock.json
            echo ""
            echo "Installation des nouvelles dépendances..."
            npm install
            echo ""
            echo "✅ Installation terminée!"
            echo ""
            ;;
            
        6)
            echo ""
            echo "🏗️  Compilation pour production..."
            echo ""
            npm run build
            echo ""
            if [ $? -eq 0 ]; then
                echo "✅ Compilation réussie!"
                echo ""
                echo "Pour tester en local:"
                echo "  npm start"
            else
                echo "❌ Erreur lors de la compilation"
                echo ""
                echo "Solutions possibles:"
                echo "  1. npm install (réinstaller les dépendances)"
                echo "  2. rm -rf .next (supprimer le cache)"
                echo "  3. Vérifier les erreurs ci-dessus"
            fi
            echo ""
            ;;
            
        7)
            echo ""
            echo "✅ Vérification de la configuration..."
            echo ""
            if [ -f "./check-deployment.sh" ]; then
                ./check-deployment.sh
            else
                echo "Script de vérification non trouvé"
            fi
            echo ""
            ;;
            
        8)
            echo ""
            echo "📖 Documentation Disponible:"
            echo ""
            echo "  1. Démarrage Rapide:   QUICKSTART.md"
            echo "  2. Déploiement Vercel: VERCEL_DEPLOYMENT.md"
            echo "  3. Migration Next.js:  MIGRATION_COMPLETE.md"
            echo "  4. Développement:      DEVELOPMENT.md"
            echo "  5. Index complet:      INDEX_DOCUMENTATION.md"
            echo "  6. Vue UI:             UI_OVERVIEW.md"
            echo "  7. Commandes:          COMMANDS.md"
            echo ""
            read -p "Quelle documentation voulez-vous voir? (Fichier ou nombre): " doc_choice
            
            case $doc_choice in
                1) less QUICKSTART.md ;;
                2) less VERCEL_DEPLOYMENT.md ;;
                3) less MIGRATION_COMPLETE.md ;;
                4) less DEVELOPMENT.md ;;
                5) less INDEX_DOCUMENTATION.md ;;
                6) less UI_OVERVIEW.md ;;
                7) less COMMANDS.md ;;
                *) 
                    if [ -f "$doc_choice" ]; then
                        less "$doc_choice"
                    else
                        echo "Fichier non trouvé: $doc_choice"
                    fi
                    ;;
            esac
            echo ""
            ;;
            
        9)
            echo ""
            echo "👋 Au revoir!"
            echo ""
            exit 0
            ;;
            
        *)
            echo ""
            echo "❌ Option invalide. Veuillez sélectionner 1-9"
            echo ""
            ;;
    esac
    
    read -p "Appuyez sur Entrée pour continuer..."
done
