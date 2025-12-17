#!/usr/bin/env bash
# deploy.sh
set -euo pipefail

# Remote target configuration
REMOTE_USER="rouchedaane"
REMOTE_HOST="38.242.224.160"
# Update this path to where you host the project on the server
REMOTE_DIR="/home/rouchedaane/event-prod/artben-sigec"

# Rsync excludes tailored for a Laravel + Vite project
EXCLUDES=(
  "--exclude .git"
  "--exclude node_modules"
  "--exclude backend/vendor"
  "--exclude backend/storage/logs"
  "--exclude backend/storage/framework/cache"
  "--exclude backend/storage/framework/sessions"
  "--exclude backend/storage/framework/views"
  "--exclude backend/.env"
  "--exclude frontend/node_modules"
)


# Only run Docker commands if 'prod' parameter is provided
if [ "$1" = "prod" ]; then
    echo "Pulling latest changes from remote repository... & Rebuilding and restarting containers on remote host..."
    ssh "${REMOTE_USER}@${REMOTE_HOST}" bash -lc "\
      set -euo pipefail && \
      cd '${REMOTE_DIR}' && \
      git pull origin main && \
      docker compose up -d --build --force-recreate --remove-orphans"
else
    echo "Syncing project to ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}..."
    rsync -avz ${EXCLUDES[*]} ./ "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}"
    echo "Skipping container rebuild. To rebuild containers, run with 'prod' parameter."
    ssh "${REMOTE_USER}@${REMOTE_HOST}" bash -lc "\
      set -euo pipefail && \
      cd '${REMOTE_DIR}' && \
      docker compose up -d --build --force-recreate --remove-orphans"
fi
