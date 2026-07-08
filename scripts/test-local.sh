#!/bin/sh
# test-local.sh — Run all quality gates for Cwt Academy

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo ""
echo "=========================================="
echo "  Cwt Academy — Test Local"
echo "=========================================="
echo ""

# --- Ensure Docker services are running ---

if ! docker-compose exec -T mysql mysqladmin ping -h localhost --silent > /dev/null 2>&1; then
    echo "${YELLOW}→ Starting Docker services${NC}"
    docker-compose up -d
    echo "${YELLOW}→ Waiting for MySQL...${NC}"
    sleep 5
fi

if ! docker-compose exec -T redis redis-cli -a "${REDIS_PASSWORD:-redis_secret_local}" ping | grep -q PONG; then
    echo "${YELLOW}→ Redis is not ready, restarting services${NC}"
    docker-compose up -d
    echo "${YELLOW}→ Waiting for Redis...${NC}"
    sleep 5
fi

# --- Clear caches first ---

echo "${YELLOW}→ Clearing caches${NC}"
php artisan optimize:clear || true

# --- npm build ---

echo ""
echo "=========================================="
echo "  1. Frontend Build"
echo "=========================================="
npm run build

# --- Pint ---

echo ""
echo "=========================================="
echo "  2. Laravel Pint (Code Style)"
echo "=========================================="
./vendor/bin/pint --test

# --- PHPStan ---

echo ""
echo "=========================================="
echo "  3. PHPStan (Static Analysis)"
echo "=========================================="
./vendor/bin/phpstan analyse --no-progress

# --- Tests ---

echo ""
echo "=========================================="
echo "  4. PHPUnit Tests"
echo "=========================================="
php artisan test

# --- Cache ---

echo ""
echo "=========================================="
echo "  5. Cache Warmup"
echo "=========================================="
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize:clear

# --- Summary ---

echo ""
echo "=========================================="
echo "  ${GREEN}All Quality Gates Passed!${NC}"
echo "=========================================="
echo ""
