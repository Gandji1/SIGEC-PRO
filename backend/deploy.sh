#!/usr/bin/env bash
# deploy.sh
set -euo pipefail

# Remote target configuration
REMOTE_USER="rouchedaane"
REMOTE_HOST="38.242.224.160"
# Update this path to where you host the project on the server
REMOTE_DIR="/home/rouchedaane/event-prod/sigec-backend"

# Rsync excludes tailored for a Laravel + Vite project
EXCLUDES=(
  "--exclude .git"
  "--exclude node_modules"
  "--exclude vendor"
  "--exclude storage/logs"
  "--exclude storage/framework/cache"
  "--exclude storage/framework/sessions"
  "--exclude storage/framework/views"
  "--exclude frontend/node_modules"
  "--exclude database/database.sqlite"
  "--exclude .env"
)



echo "Syncing project to ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}..."
rsync -avz ${EXCLUDES[*]} ./ "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}"
echo "Starting containers..."
ssh "${REMOTE_USER}@${REMOTE_HOST}" bash -lc "\
  set -euo pipefail && \
  cd '${REMOTE_DIR}' && \
  docker compose up -d --force-recreate --remove-orphans"

