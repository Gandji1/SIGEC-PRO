#!/usr/bin/env bash
# deploy.sh
set -euo pipefail

# Remote target configuration
REMOTE_USER="rouchedaane"
REMOTE_HOST="38.242.224.160"
# Update this path to where you host the project on the server
REMOTE_DIR="/home/rouchedaane/event-prod/sigec-frontend"

# Rsync excludes tailored for a React + Vite project
EXCLUDES=(
  "--exclude .git"
  "--exclude node_modules"
  "--exclude dist"
  "--exclude .DS_Store"
  "--exclude *.log"
)

echo "Syncing project to ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}..."
rsync -avz ${EXCLUDES[*]} ./ "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}"

echo "Building and deploying production containers..."
ssh "${REMOTE_USER}@${REMOTE_HOST}" bash -lc "\
  set -euo pipefail && \
  cd '${REMOTE_DIR}' && \
  docker compose down && \
  docker compose build --no-cache && \
  docker compose up -d --force-recreate --remove-orphans"

echo "Deployment completed successfully!"

