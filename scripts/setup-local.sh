#!/bin/sh
# setup-local.sh — One-time setup for Cwt Academy local development

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo ""
echo "=========================================="
echo "  Cwt Academy — Local Setup"
echo "=========================================="
echo ""

# --- Check prerequisites ---

check_cmd() {
    if ! command -v "$1" > /dev/null 2>&1; then
        echo "${RED}ERROR: $1 is not installed.${NC}"
        echo "  → Install $2"
        exit 1
    fi
    echo "${GREEN}✓${NC} $1 found ($(command -v $1))"
}

check_cmd php "PHP (https://www.php.net/)"
check_cmd composer "Composer (https://getcomposer.org/)"
check_cmd node "Node.js (https://nodejs.org/)"
check_cmd npm "npm (comes with Node.js)"
check_cmd docker "Docker (https://docs.docker.com/get-docker/)"
check_cmd docker-compose "Docker Compose (comes with Docker Desktop or docker-compose-plugin)"

echo ""

# --- Create .env from .env.example ---

if [ ! -f .env ]; then
    echo "${YELLOW}→ Creating .env from .env.example${NC}"
    cp .env.example .env
else
    echo "${GREEN}✓${NC} .env already exists (skipping)"
fi

if [ ! -f .env.testing ]; then
    echo "${YELLOW}→ Creating .env.testing from .env.testing.example${NC}"
    cp .env.testing.example .env.testing
else
    echo "${GREEN}✓${NC} .env.testing already exists (skipping)"
fi

# --- Composer & npm ---

echo ""
echo "${YELLOW}→ Running composer install${NC}"
composer install --no-interaction

echo ""
echo "${YELLOW}→ Running npm install${NC}"
npm install

# --- Laravel key ---

if ! grep -q "^APP_KEY=" .env | grep -qv "^APP_KEY=$"; then
    echo ""
    echo "${YELLOW}→ Generating APP_KEY${NC}"
    php artisan key:generate --ansi
else
    echo "${GREEN}✓${NC} APP_KEY already set"
fi

if ! grep -q "^APP_KEY=" .env.testing | grep -qv "^APP_KEY=$"; then
    echo ""
    echo "${YELLOW}→ Generating APP_KEY for .env.testing${NC}"
    php artisan key:generate --env=testing --ansi
else
    echo "${GREEN}✓${NC} APP_KEY for testing already set"
fi

# --- Start database ---

echo ""
echo "${YELLOW}→ Starting Docker database${NC}"
docker-compose up -d

# --- Wait for MySQL health ---

echo ""
echo "${YELLOW}→ Waiting for MySQL to be ready...${NC}"
MAX_TRIES=30
TRIES=0
while [ $TRIES -lt $MAX_TRIES ]; do
    if docker-compose exec -T mysql mysqladmin ping -h localhost --silent > /dev/null 2>&1; then
        echo "${GREEN}✓${NC} MySQL is ready"
        break
    fi
    TRIES=$((TRIES + 1))
    echo "  Attempt $TRIES/$MAX_TRIES..."
    sleep 2
done

if [ $TRIES -eq $MAX_TRIES ]; then
    echo "${RED}ERROR: MySQL did not become ready in time.${NC}"
    echo "  Check: docker-compose logs mysql"
    exit 1
fi

# --- Run migrations & seeders ---

echo ""
echo "${YELLOW}→ Running migrations and seeders${NC}"
php artisan migrate:fresh --seed --force

# --- Build frontend ---

echo ""
echo "${YELLOW}→ Building frontend assets${NC}"
npm run build

# --- Storage link ---

echo ""
echo "${YELLOW}→ Creating storage link${NC}"
php artisan storage:link || true

# --- Cache setup ---

echo ""
echo "${YELLOW}→ Caching config and routes${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# --- Summary ---
echo ""
echo "=========================================="
echo "  ${GREEN}Setup Complete!${NC}"
echo "=========================================="
echo ""
echo "  Local URLs:"
echo "    ${GREEN}http://127.0.0.1:8000${NC}      — Public site"
echo "    ${GREEN}http://127.0.0.1:8000/courses${NC} — Course catalog"
echo "    ${GREEN}http://127.0.0.1:8000/track${NC}   — Track request"
echo "    ${GREEN}http://127.0.0.1:8000/login${NC}   — Login"
echo "    ${GREEN}http://127.0.0.1:8000/admin${NC}   — Admin dashboard"
echo ""
echo "  Admin credentials (local only):"
echo "    Email:    ${YELLOW}admin@cwtacademy.local${NC}"
echo "    Password: ${YELLOW}change-me-with-secure-password${NC}"
echo "    IMPORTANT: Change this password before deploying to production."
echo ""
echo "  Next steps:"
echo "    ./scripts/run-local.sh"
echo ""
