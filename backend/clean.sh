#!/usr/bin/env bash
# clean.sh - Clean up Docker resources
set -euo pipefail

echo "Starting Docker cleanup process..."

# Stop all running containers
echo "Stopping all running containers..."
docker compose down

# Remove stopped containers
echo "Removing stopped containers..."
docker container prune -f

# Remove unused images
echo "Removing unused images..."
docker image prune -a -f

# Remove unused volumes
echo "Removing unused volumes..."
docker volume prune -f

# Remove unused networks
echo "Removing unused networks..."
docker network prune -f

# Remove build cache
echo "Removing build cache..."
docker builder prune -a -f

echo "Docker cleanup completed successfully!"
