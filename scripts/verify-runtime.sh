#!/bin/sh
# verify-runtime.sh — Run all quality gates for Cwt Academy

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

SOCKET="${HOME}/mariadb-data/mysqld.sock"

echo ""
echo "=========================================="
echo "  Cwt Academy — Runtime Verification"
echo "=========================================="
echo ""

# --- Check DB connectivity ---

if [ ! -S "$SOCKET" ] || ! mysqladmin -S "$SOCKET" ping --silent > /dev/null 2>&1; then
    echo "${RED}ERROR: Database is not running.${NC}"
    echo "  → Start it first:"
    echo "     ./scripts/start-db.sh"
    exit 1
fi

echo "${GREEN}✓${NC} Database connected (${SOCKET})"

# --- Check Redis connectivity ---

if ! docker-compose exec -T redis redis-cli -a "${REDIS_PASSWORD:-redis_secret_local}" ping | grep -q PONG; then
    echo "${RED}ERROR: Redis is not running.${NC}"
    echo "  → Start it first:"
    echo "     docker-compose up -d redis"
    exit 1
fi

echo "${GREEN}✓${NC} Redis connected"

# --- Ensure testing APP_KEY exists ---

if [ ! -f .env.testing ] || ! grep -q "^APP_KEY=base64:" .env.testing; then
    echo "${YELLOW}→ Generating testing APP_KEY${NC}"
    php artisan key:generate --env=testing --ansi
fi

# --- Run migrations with seeders for testing ---

echo ""
echo "=========================================="
echo "  1. Migrations & Seeders (dev + test)"
echo "=========================================="
php artisan migrate:fresh --seed
php artisan migrate:fresh --seed --env=testing

# --- npm build ---

echo ""
echo "=========================================="
echo "  2. Frontend Build"
echo "=========================================="
npm run build

# --- Pint ---

echo ""
echo "=========================================="
echo "  3. Laravel Pint (Code Style)"
echo "=========================================="
./vendor/bin/pint --test

# --- PHPStan ---

echo ""
echo "=========================================="
echo "  4. PHPStan (Static Analysis)"
echo "=========================================="
./vendor/bin/phpstan analyse --no-progress

# --- Tests ---

echo ""
echo "=========================================="
echo "  5. PHPUnit Tests"
echo "=========================================="
php artisan test

# --- Routes ---

echo ""
echo "=========================================="
echo "  6. Route Verification"
echo "=========================================="
php artisan route:list

# --- Cache clear ---

echo ""
echo "=========================================="
echo "  7. Optimize Clear"
echo "=========================================="
php artisan optimize:clear

# --- Summary ---

echo ""
echo "=========================================="
echo "  ${GREEN}All Quality Gates Passed!${NC}"
echo "=========================================="
echo ""
