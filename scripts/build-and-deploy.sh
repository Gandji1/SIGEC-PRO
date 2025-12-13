#!/bin/bash

# Build and Deploy Frontend to Backend Public Directory

set -e

echo "🔨 Building frontend..."
cd /workspaces/SIGEC/frontend
npm run build

echo "📦 Copying to backend public directory..."
cp -r /workspaces/SIGEC/frontend/dist/* /workspaces/SIGEC/backend/public/

echo "✅ Frontend deployed successfully!"
echo "🌐 Access at: https://improved-robot-vjjr5wpv6pqhx4pg-8000.app.github.dev"
