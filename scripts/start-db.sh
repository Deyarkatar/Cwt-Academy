#!/bin/sh
# start-db.sh — Start the local MariaDB database for Cwt Academy

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

DATA_DIR="${HOME}/mariadb-data"
SOCKET="${DATA_DIR}/mysqld.sock"

# --- Check if already running ---

if [ -S "$SOCKET" ] && mysqladmin -S "$SOCKET" ping --silent > /dev/null 2>&1; then
    echo "${GREEN}✓${NC} MariaDB already running on ${SOCKET}"
    exit 0
fi

# --- Check prerequisites ---

if ! command -v mariadbd > /dev/null 2>&1 && ! command -v mysqld > /dev/null 2>&1; then
    echo "${RED}ERROR: MariaDB/MySQL server is not installed.${NC}"
    echo "  → Install MariaDB server or use Docker:"
    echo "     docker compose up -d"
    exit 1
fi

if ! command -v mysql > /dev/null 2>&1; then
    echo "${RED}ERROR: mysql client is not installed.${NC}"
    exit 1
fi

if ! php -m | grep -q pdo_mysql; then
    echo "${RED}ERROR: pdo_mysql PHP extension is not installed.${NC}"
    exit 1
fi

# --- Initialize data directory if needed ---

if [ ! -d "$DATA_DIR/mysql" ]; then
    echo "${YELLOW}→ Initializing MariaDB data directory: ${DATA_DIR}${NC}"
    mkdir -p "$DATA_DIR"
    mariadb-install-db --datadir="$DATA_DIR" --auth-root-authentication-method=normal > /dev/null 2>&1
fi

# --- Start MariaDB ---

echo "${YELLOW}→ Starting MariaDB on ${SOCKET}${NC}"
mariadbd \
    --datadir="$DATA_DIR" \
    --socket="$SOCKET" \
    --port=3306 \
    --pid-file="${DATA_DIR}/mariadb.pid" \
    --log-error="${DATA_DIR}/error.log" &

# --- Wait for health ---

TRIES=0
MAX_TRIES=30
while [ $TRIES -lt $MAX_TRIES ]; do
    if mysqladmin -S "$SOCKET" ping --silent > /dev/null 2>&1; then
        echo "${GREEN}✓${NC} MariaDB is ready"
        break
    fi
    TRIES=$((TRIES + 1))
    sleep 1
done

if [ $TRIES -eq $MAX_TRIES ]; then
    echo "${RED}ERROR: MariaDB did not start.${NC}"
    echo "  Check: ${DATA_DIR}/error.log"
    exit 1
fi

# --- Create databases and user ---

mysql -S "$SOCKET" -u root -e "
CREATE DATABASE IF NOT EXISTS cwt_academy;
CREATE DATABASE IF NOT EXISTS cwt_academy_test;
CREATE USER IF NOT EXISTS 'cwt'@'localhost' IDENTIFIED BY 'cwt_password';
GRANT ALL PRIVILEGES ON cwt_academy.* TO 'cwt'@'localhost';
GRANT ALL PRIVILEGES ON cwt_academy_test.* TO 'cwt'@'localhost';
FLUSH PRIVILEGES;
" 2>/dev/null

echo "${GREEN}✓${NC} Databases ready: cwt_academy, cwt_academy_test"
echo "${GREEN}✓${NC} User: cwt / cwt_password"
echo "${GREEN}✓${NC} Socket: ${SOCKET}"
