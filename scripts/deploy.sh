#!/bin/bash

# SIGEC Deployment Script for Linux/macOS

set -e

echo "=========================================="
echo "SIGEC Deployment Script"
echo "=========================================="

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

cd "$(dirname "$0")/.."

echo "📦 Building Docker images..."
docker-compose -f infra/docker-compose.yml build

echo "🚀 Starting services..."
docker-compose -f infra/docker-compose.yml up -d

echo "⏳ Waiting for services to be ready..."
sleep 5

echo "🔑 Generating Laravel application key..."
docker exec sigec-app php artisan key:generate

echo "🗄️  Running database migrations..."
docker exec sigec-app php artisan migrate --seed

echo "✅ Deployment complete!"
echo ""
echo "📋 Services available at:"
echo "   - Backend API: http://localhost:8000"
echo "   - Frontend: http://localhost:5173"
echo "   - pgAdmin: http://localhost:5050 (admin@sigec.local / admin)"
echo ""
echo "🛑 To stop services, run: docker-compose -f infra/docker-compose.yml down"
