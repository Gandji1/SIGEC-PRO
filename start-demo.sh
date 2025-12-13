#!/bin/bash

# ============================================================================
# 🚀 SIGEC AUTO START - Setup + Demo
# ============================================================================

set -e

COLORS_BLUE='\033[0;34m'
COLORS_GREEN='\033[0;32m'
COLORS_RESET='\033[0m'

print_header() {
    echo -e "\n${COLORS_BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${COLORS_RESET}"
    echo -e "${COLORS_BLUE}$1${COLORS_RESET}"
    echo -e "${COLORS_BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${COLORS_RESET}\n"
}

print_success() {
    echo -e "${COLORS_GREEN}✅ $1${COLORS_RESET}"
}

# ============================================================================
# STEP 1: Prepare Backend
# ============================================================================
print_header "STEP 1: Prepare Backend"

cd /workspaces/SIGEC/backend

# Check if .env exists
if [ ! -f .env ]; then
    print_success "Creating .env"
    cp .env.example .env 2>/dev/null || echo "APP_URL=http://localhost:8000" > .env
    echo "APP_KEY=" >> .env
fi

# Generate key if not present
if ! grep -q "APP_KEY=base64:" .env; then
    print_success "Generating APP_KEY"
    php artisan key:generate --quiet
fi

# Create database
if [ ! -f database/database.sqlite ]; then
    print_success "Creating database.sqlite"
    touch database/database.sqlite
fi

# Run migrations
print_success "Running migrations + seeding"
php artisan migrate --seed --quiet

print_success "Backend ready!"

# ============================================================================
# STEP 2: Start Backend Server
# ============================================================================
print_header "STEP 2: Starting Backend Server"

print_success "Launching Laravel on http://localhost:8000"
echo -e "${COLORS_GREEN}(Keep this terminal open, will switch to another for demo)${COLORS_RESET}\n"

# Start in background
php artisan serve &
SERVER_PID=$!

# Wait for server to be ready
sleep 3

# Check if server is running
if ! curl -s http://localhost:8000/api/register > /dev/null 2>&1; then
    print_success "Server is starting... (waiting 3 more seconds)"
    sleep 3
fi

print_success "✅ Backend running (PID: $SERVER_PID)"

# ============================================================================
# STEP 3: Run Demo
# ============================================================================
cd /workspaces/SIGEC

print_header "STEP 3: Running Live Demo"
print_success "Executing ./test-demo.sh\n"

bash test-demo.sh

# ============================================================================
# CLEANUP
# ============================================================================
print_header "CLEANUP"

print_success "Stopping backend server (PID: $SERVER_PID)"
kill $SERVER_PID 2>/dev/null || true

print_success "Demo completed! ✅"
echo -e "\n${COLORS_GREEN}Next: Run Itération 3 (POS & Sales)${COLORS_RESET}\n"
