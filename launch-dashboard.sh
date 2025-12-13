#!/bin/bash

# Launch SIGEC Dashboard + Backend Demo

cat << 'EOF'

╔════════════════════════════════════════════════════════════════════════════╗
║                                                                            ║
║              🚀 SIGEC - DASHBOARD + DÉMO COMPLÈTE                         ║
║                                                                            ║
╚════════════════════════════════════════════════════════════════════════════╝

EOF

# Get the directory where this script is
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Start Python dashboard server
echo "📱 Starting dashboard server..."
python3 "$DIR/serve-dashboard.py" > /tmp/dashboard.log 2>&1 &
DASHBOARD_PID=$!

# Wait for server to start
sleep 2

# Show info
echo ""
echo "✅ Dashboard running at: http://localhost:8888/dashboard.html"
echo ""
echo "─────────────────────────────────────────────────────────────────"
echo ""
echo "🎬 NOW YOU HAVE 3 OPTIONS:"
echo ""
echo "Option 1: RUN DEMO (Recommended - ~3 min)"
echo "  $ cd $DIR && ./start-demo.sh"
echo ""
echo "Option 2: MANUAL BACKEND TEST"
echo "  Terminal 1: cd $DIR/backend && php artisan serve"
echo "  Terminal 2: cd $DIR && ./test-demo.sh"
echo ""
echo "Option 3: CONTINUE WITH ITÉRATION 3 (Sales & Payments)"
echo "  → Just say 'continuer' and we'll implement next phase!"
echo ""
echo "─────────────────────────────────────────────────────────────────"
echo ""
echo "📊 Dashboard is LIVE:"
echo "   http://localhost:8888/dashboard.html"
echo ""
echo "To stop dashboard:"
echo "   kill $DASHBOARD_PID"
echo ""
echo "═════════════════════════════════════════════════════════════════"
echo ""

# Keep process running
wait $DASHBOARD_PID
