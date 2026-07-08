#!/bin/sh
# run-local.sh — Start the local development environment

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo ""
echo "=========================================="
echo "  Cwt Academy — Run Local"
echo "=========================================="
echo ""

# --- Start database ---

echo "${YELLOW}→ Starting Docker services${NC}"
docker-compose up -d

# --- Wait for MySQL ---

echo "${YELLOW}→ Waiting for MySQL...${NC}"
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
    echo "${RED}ERROR: MySQL did not become ready.${NC}"
    echo "  Check: docker-compose logs mysql"
    exit 1
fi

# --- Wait for Redis ---

echo "${YELLOW}→ Waiting for Redis...${NC}"
MAX_TRIES=30
TRIES=0
while [ $TRIES -lt $MAX_TRIES ]; do
    if docker-compose exec -T redis redis-cli -a "${REDIS_PASSWORD:-redis_secret_local}" ping | grep -q PONG; then
        echo "${GREEN}✓${NC} Redis is ready"
        break
    fi
    TRIES=$((TRIES + 1))
    echo "  Attempt $TRIES/$MAX_TRIES..."
    sleep 2
done

if [ $TRIES -eq $MAX_TRIES ]; then
    echo "${RED}ERROR: Redis did not become ready.${NC}"
    echo "  Check: docker-compose logs redis"
    exit 1
fi

# --- Clear caches ---

echo ""
echo "${YELLOW}→ Clearing caches${NC}"
php artisan optimize:clear || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

# --- Start dev server ---

echo ""
echo "${GREEN}→ Starting Laravel development server${NC}"
echo "  URL: http://127.0.0.1:8000"
echo "  Press Ctrl+C to stop"
echo ""
echo "  ${YELLOW}NOTE:${NC} Run 'npm run dev' in a separate terminal for Vite HMR."
echo ""

php artisan serve --host=127.0.0.1 --port=8000
