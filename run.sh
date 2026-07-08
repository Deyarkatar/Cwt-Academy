#!/bin/bash
set -e

echo "=== Cwt Academy Startup Script ==="

# Check if any MariaDB/MySQL is already running (user-local or system)
USER_SOCKET="${HOME}/mariadb-data/mysqld.sock"
DB_UP=false

if [ -S "$USER_SOCKET" ] && mysqladmin -S "$USER_SOCKET" ping --silent > /dev/null 2>&1; then
    echo "✓ User-local MariaDB already running (${USER_SOCKET})"
    DB_UP=true
elif systemctl is-active --quiet mariadb 2>/dev/null || systemctl is-active --quiet mysql 2>/dev/null; then
    echo "✓ System MariaDB already running"
    DB_UP=true
fi

# Start user-local MariaDB if nothing is running
if [ "$DB_UP" = false ]; then
    if [ -d "${HOME}/mariadb-data/mysql" ]; then
        echo "→ Starting user-local MariaDB..."
        nohup mariadbd \
            --datadir="${HOME}/mariadb-data" \
            --socket="$USER_SOCKET" \
            --port=3306 \
            --pid-file="${HOME}/mariadb-data/mariadb.pid" \
            --log-error="${HOME}/mariadb-data/error.log" > /dev/null 2>&1 &
        sleep 3
        if mysqladmin -S "$USER_SOCKET" ping --silent > /dev/null 2>&1; then
            echo "✓ User-local MariaDB started"
        else
            echo "✗ User-local MariaDB failed to start. Check: ${HOME}/mariadb-data/error.log"
            exit 1
        fi
    else
        echo "→ Starting system MariaDB..."
        sudo systemctl start mariadb || sudo systemctl start mysql
    fi
fi

# Ensure correct DB port
cd "$(dirname "$0")"
if grep -q "^DB_PORT=3307" .env 2>/dev/null; then
    echo "Fixing DB port 3307 -> 3306"
    sed -i 's/^DB_PORT=3307/DB_PORT=3306/' .env
fi

# Clear caches
echo "Clearing Laravel caches..."
php artisan view:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan config:clear 2>/dev/null || true

# Build frontend if needed
if [ ! -d "public/build" ] || [ "resources/js" -nt "public/build" ] 2>/dev/null; then
    echo "Building frontend assets..."
    npm run build
fi

echo "Starting server on http://127.0.0.1:8000"
php artisan serve --host=127.0.0.1 --port=8000
