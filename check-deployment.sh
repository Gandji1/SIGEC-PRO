#!/bin/bash

echo "╔══════════════════════════════════════════╗"
echo "║  SIGEC v1.0 - Vérification Pré-Vercel   ║"
echo "╚══════════════════════════════════════════╝"
echo ""

CHECKS_PASSED=0
CHECKS_TOTAL=0

# Helper functions
check_file() {
    local file=$1
    local name=$2
    CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
    
    if [ -f "$file" ]; then
        echo "✅ $name"
        CHECKS_PASSED=$((CHECKS_PASSED + 1))
    else
        echo "❌ $name (manquant: $file)"
    fi
}

check_dir() {
    local dir=$1
    local name=$2
    CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
    
    if [ -d "$dir" ]; then
        echo "✅ $name"
        CHECKS_PASSED=$((CHECKS_PASSED + 1))
    else
        echo "❌ $name (manquant: $dir)"
    fi
}

echo "📁 Structure du Projet"
echo "────────────────────────────────────────"
check_dir "app" "Dossier Next.js App Router"
check_dir "app/api" "API Routes"
check_dir "app/dashboard" "Pages Dashboard"
check_file "app/layout.jsx" "Root Layout"
check_file "app/page.jsx" "Home Page"
check_file "app/login/page.jsx" "Login Page"
check_file "app/demo/page.jsx" "Demo Page"
echo ""

echo "🔧 Configuration"
echo "────────────────────────────────────────"
check_file "package.json" "Package.json"
check_file "next.config.js" "Next.js Config"
check_file "vercel.json" "Vercel Config"
check_file "tailwind.config.js" "Tailwind Config"
check_file "postcss.config.js" "PostCSS Config"
check_file ".env.local" "Environment Variables"
check_file "app/globals.css" "Global CSS"
echo ""

echo "📄 Pages"
echo "────────────────────────────────────────"
check_file "app/dashboard/page.jsx" "Dashboard Home"
check_file "app/dashboard/layout.jsx" "Dashboard Layout"
check_file "app/dashboard/tenants/page.jsx" "Tenants Page"
check_file "app/dashboard/users/page.jsx" "Users Page"
check_file "app/dashboard/procurement/page.jsx" "Procurement Page"
check_file "app/dashboard/sales/page.jsx" "Sales Page"
check_file "app/dashboard/expenses/page.jsx" "Expenses Page"
check_file "app/dashboard/reports/page.jsx" "Reports Page"
check_file "app/dashboard/export/page.jsx" "Export Page"
echo ""

echo "🔌 API Routes"
echo "────────────────────────────────────────"
check_file "app/api/auth/login/route.js" "Auth Login"
check_file "app/api/stats/route.js" "Stats Endpoint"
check_file "app/api/tenants/route.js" "Tenants Endpoint"
check_file "app/api/users/route.js" "Users Endpoint"
check_file "app/api/sales/route.js" "Sales Endpoint"
check_file "app/api/procurement/route.js" "Procurement Endpoint"
check_file "app/api/expenses/route.js" "Expenses Endpoint"
check_file "app/api/reports/route.js" "Reports Endpoint"
echo ""

echo "📦 Dépendances"
echo "────────────────────────────────────────"
if grep -q '"next":' package.json; then
    echo "✅ Next.js trouvé"
    CHECKS_PASSED=$((CHECKS_PASSED + 1))
else
    echo "❌ Next.js manquant"
fi
CHECKS_TOTAL=$((CHECKS_TOTAL + 1))

if grep -q '"react":' package.json; then
    echo "✅ React trouvé"
    CHECKS_PASSED=$((CHECKS_PASSED + 1))
else
    echo "❌ React manquant"
fi
CHECKS_TOTAL=$((CHECKS_TOTAL + 1))

if grep -q '"tailwindcss":' package.json; then
    echo "✅ Tailwind CSS trouvé"
    CHECKS_PASSED=$((CHECKS_PASSED + 1))
else
    echo "❌ Tailwind CSS manquant"
fi
CHECKS_TOTAL=$((CHECKS_TOTAL + 1))
echo ""

echo "📝 Documentation"
echo "────────────────────────────────────────"
check_file "VERCEL_DEPLOYMENT.md" "Deployment Guide"
check_file "README.md" "README"
check_file "start-dev.sh" "Development Script"
echo ""

# Summary
echo "╔══════════════════════════════════════════╗"
PERCENT=$((CHECKS_PASSED * 100 / CHECKS_TOTAL))
if [ $PERCENT -eq 100 ]; then
    STATUS="✅ PRÊT POUR VERCEL"
    SYMBOL="✓"
else
    STATUS="⚠️  VÉRIFIER LES POINTS MANQUANTS"
    SYMBOL="!"
fi
echo "║  $STATUS"
echo "║  Résultat: $CHECKS_PASSED/$CHECKS_TOTAL ($PERCENT%)"
echo "╚══════════════════════════════════════════╝"
echo ""

if [ $PERCENT -eq 100 ]; then
    echo "🚀 Prêt pour le déploiement !"
    exit 0
else
    echo "⚠️  Complétez les éléments manquants avant le déploiement"
    exit 1
fi
