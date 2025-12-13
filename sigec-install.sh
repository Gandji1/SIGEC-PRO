#!/bin/bash

# SIGEC Installation & Quick Test Script
# Usage: ./sigec-install.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$SCRIPT_DIR/backend"
FRONTEND_DIR="$SCRIPT_DIR"

echo "🚀 SIGEC v1.0 Installation Script"
echo "=================================="

# Step 1: Backend setup
echo ""
echo "📦 Step 1: Backend Dependencies"
cd "$BACKEND_DIR"
composer install --no-interaction

# Step 2: Frontend setup
echo ""
echo "📦 Step 2: Frontend Dependencies"
cd "$SCRIPT_DIR"
npm install

# Step 3: Database
echo ""
echo "🗄️  Step 3: Database Setup"
cd "$BACKEND_DIR"

# Check if .env exists
if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
    php artisan key:generate
fi

# Run migrations and seed
echo "Running migrations and seeders..."
php artisan migrate --force
php artisan db:seed

echo ""
echo "✅ Installation Complete!"
echo ""
echo "🎯 Next Steps:"
echo ""
echo "1. Start Backend (Terminal 1):"
echo "   cd $BACKEND_DIR"
echo "   php artisan serve --host=0.0.0.0 --port=8000"
echo ""
echo "2. Start Frontend (Terminal 2):"
echo "   cd $SCRIPT_DIR"
echo "   npm run dev"
echo ""
echo "3. Login with demo credentials:"
echo "   Email: demo@sigec.com"
echo "   Password: password123"
echo ""
echo "4. Test endpoints:"
echo "   curl -X GET http://localhost:8000/api/dashboard/stats \\"
echo "     -H 'Authorization: Bearer {token}'"
echo ""
echo "5. Run tests:"
echo "   cd $BACKEND_DIR"
echo "   php artisan test tests/Feature/PurchaseReceiveFlowTest.php"
echo ""
