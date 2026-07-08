#!/usr/bin/env bash
# deploy.sh — Bulletproof production deployment for Cwt Academy
#
# This script is intended to run on the production server after the code has
# been delivered (e.g. via git pull, CI/CD artifact, or rsync). It performs
# all runtime optimizations, runs migrations, rebuilds assets, and validates
# the environment before bringing the application back online.
#
# Required environment variables (or sensible defaults):
#   DEPLOY_DIR     /var/www/cwt-academy
#   GIT_BRANCH     main

set -euo pipefail

DEPLOY_DIR="${DEPLOY_DIR:-/var/www/cwt-academy}"
GIT_BRANCH="${GIT_BRANCH:-main}"

MAINTENANCE_FILE="$DEPLOY_DIR/storage/framework/down"

# Colours
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[deploy]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[deploy]${NC} $1"
}

error() {
    echo -e "${RED}[deploy]${NC} $1"
}

# Ensure we are inside the deploy directory
cd "$DEPLOY_DIR"

# --- Pre-flight security checks ------------------------------------------------

log "Running pre-flight security checks..."

APP_ENV=$(grep -E "^APP_ENV=" .env | cut -d= -f2- | tr -d '"' || true)
APP_DEBUG=$(grep -E "^APP_DEBUG=" .env | cut -d= -f2- | tr -d '"' || true)
ADMIN_EMAIL=$(grep -E "^ADMIN_DEFAULT_EMAIL=" .env | cut -d= -f2- | tr -d '"' || true)
ADMIN_PASSWORD=$(grep -E "^ADMIN_DEFAULT_PASSWORD=" .env | cut -d= -f2- | tr -d '"' || true)

if [ "$APP_ENV" != "production" ]; then
    warn "APP_ENV is not set to 'production' (current: ${APP_ENV:-<unset>})."
    warn "Continuing, but this deployment is NOT production-hardened."
fi

if [ "$APP_DEBUG" != "false" ]; then
    error "APP_DEBUG must be 'false' in production. Current value: ${APP_DEBUG:-<unset>}"
    error "Aborting deployment to prevent exposing sensitive information."
    exit 1
fi

if [ "$ADMIN_PASSWORD" = "change-me-with-secure-password" ] || [ "$ADMIN_PASSWORD" = "password" ] || [ -z "$ADMIN_PASSWORD" ]; then
    error "Default/unsafe admin password detected. Set a secure ADMIN_DEFAULT_PASSWORD in .env before deploying."
    exit 1
fi

if [ -n "$ADMIN_EMAIL" ] && [[ "$ADMIN_EMAIL" == *"@example.com" ]]; then
    error "Placeholder admin email detected (${ADMIN_EMAIL}). Use a real organisational email."
    exit 1
fi

# Make sure TRUSTED_PROXIES is set in production
TRUSTED_PROXIES=$(grep -E "^TRUSTED_PROXIES=" .env | cut -d= -f2- | tr -d '"' || true)
if [ "$APP_ENV" = "production" ] && [ -z "$TRUSTED_PROXIES" ]; then
    error "TRUSTED_PROXIES must be configured in production to prevent X-Forwarded-For spoofing."
    exit 1
fi

# --- Enter maintenance mode ----------------------------------------------------

log "Entering maintenance mode..."
touch "$MAINTENANCE_FILE"

trap 'rm -f "$MAINTENANCE_FILE"; error "Deployment failed, maintenance mode disabled"' ERR

# --- Pull latest code ----------------------------------------------------------

log "Pulling latest code from origin/${GIT_BRANCH}..."
git fetch origin
GIT_PREVIOUS=$(git rev-parse HEAD)
git reset --hard "origin/${GIT_BRANCH}"

# --- Build frontend assets -----------------------------------------------------

log "Installing Node dependencies and building frontend assets..."
npm ci
npm run build

# --- Install PHP dependencies --------------------------------------------------

log "Installing PHP dependencies (no dev) with optimized autoloader..."
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# --- Database migrations -------------------------------------------------------

log "Running database migrations..."
php artisan migrate --force --no-interaction

# --- Production optimizations --------------------------------------------------

log "Caching configuration, routes, views, events, and warming caches..."
php artisan app:optimize-prod

# --- Restart queue workers -----------------------------------------------------

log "Restarting queue workers..."
php artisan horizon:terminate 2>/dev/null || true
php artisan queue:restart 2>/dev/null || true

# --- Restart Docker services ---------------------------------------------------

log "Restarting Docker services..."
docker-compose up -d --build

# --- Health check -------------------------------------------------------------

log "Waiting for services to stabilise..."
sleep 5

APP_URL=$(grep -E "^APP_URL=" .env | cut -d= -f2- | tr -d '"' || true)
HEALTH_URL="${APP_URL:-http://localhost}/up"

if command -v curl >/dev/null 2>&1; then
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$HEALTH_URL" || true)
    if [ "$HTTP_STATUS" != "200" ]; then
        error "Health check failed (HTTP ${HTTP_STATUS:-no response} at ${HEALTH_URL}). Rolling back..."
        git reset --hard "$GIT_PREVIOUS"
        docker-compose up -d --build
        exit 1
    fi
    log "Health check passed (HTTP 200 at ${HEALTH_URL})."
else
    warn "curl not installed; skipping HTTP health check."
fi

# --- Disable maintenance mode --------------------------------------------------

log "Disabling maintenance mode..."
rm -f "$MAINTENANCE_FILE"

# --- Integrity baseline --------------------------------------------------------

if [ -x "./scripts/integrity-check.sh" ]; then
    log "Generating integrity baseline..."
    ./scripts/integrity-check.sh || true
fi

log "Deployment completed successfully at $(date -Iseconds)"
