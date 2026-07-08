#!/bin/sh
# reset-local.sh — Reset the local database (DESTRUCTIVE)

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo ""
echo "=========================================="
echo "  ${RED}WARNING: This will DESTROY all data${NC}"
echo "=========================================="
echo ""
echo "  This script will:"
echo "    1. Drop and recreate all database tables"
echo "    2. Re-run all seeders"
echo "    3. Clear all caches"
echo ""

printf "  Type 'yes' to continue: "
read CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "  Cancelled."
    exit 0
fi

echo ""
echo "${YELLOW}→ Resetting database...${NC}"
php artisan migrate:fresh --seed --force

echo ""
echo "${YELLOW}→ Clearing caches...${NC}"
php artisan optimize:clear

echo ""
echo "=========================================="
echo "  ${GREEN}Reset Complete${NC}"
echo "=========================================="
echo ""
