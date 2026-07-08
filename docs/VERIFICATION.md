# Cwt Academy Backend Verification Guide

This document provides step-by-step instructions for verifying the Cwt Academy Laravel backend locally.

## Prerequisites

- PHP 8.4+
- Composer
- MySQL 8.0 or MariaDB 10.11+
- Node.js (for frontend builds, if needed)
- Docker & Docker Compose (optional, for database setup)

## Quick Setup

### 1. Install PHP dependencies

```bash
composer install
```

### 2. Environment configuration

```bash
cp .env.example .env
php artisan key:generate
```

Update `.env` with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cwt_academy
DB_USERNAME=cwt
DB_PASSWORD=cwt_password
```

### 3. Database setup (choose one)

#### Option A: Using Docker Compose

```bash
docker compose up -d
```

This starts a MySQL container with:

- Database: `cwt_academy`
- User: `cwt` / `cwt_password`
- Root password: `root_password`

#### Option B: Using existing MySQL/MariaDB

Create the database and user manually:

```sql
CREATE DATABASE IF NOT EXISTS cwt_academy;
CREATE DATABASE IF NOT EXISTS cwt_academy_test;
CREATE USER IF NOT EXISTS 'cwt'@'localhost' IDENTIFIED BY 'cwt_password';
GRANT ALL PRIVILEGES ON cwt_academy.* TO 'cwt'@'localhost';
GRANT ALL PRIVILEGES ON cwt_academy_test.* TO 'cwt'@'localhost';
FLUSH PRIVILEGES;
```

### 4. Run migrations and seeders

```bash
php artisan migrate:fresh --seed
```

This creates all tables and seeds default admin users:

- Super Admin: `admin@cwtacademy.local` / `change-me-with-secure-password`
- Finance Manager: `finance@cwtacademy.local` / `change-me-with-secure-password`
- Admin User: `admin2@cwtacademy.local` / `change-me-with-secure-password`

**Important:** Change the default password before deploying to production. Use `php artisan admin:create` for production admin creation.

## Verification Commands

Run these commands in order to verify the backend:

### 1. Code Style (Pint)

```bash
./vendor/bin/pint --test
```

Expected: `PASS` with 0 issues.

### 2. Static Analysis (PHPStan)

```bash
./vendor/bin/phpstan analyse
```

Expected: `[OK] No errors`

### 3. Feature & Unit Tests

```bash
php artisan test
```

Expected: All tests pass (~35 tests, 1 skipped if GD extension is missing).

### 4. Migrations & Seeders

```bash
php artisan migrate:fresh --seed
```

Expected: All migrations and seeders run successfully.

### 5. Route Verification

```bash
php artisan route:list --except-vendor
```

Expected: ~48 routes including public API, admin API, and web routes.

**Note:** There are no Telegram webhook routes. The application uses manual Telegram access workflow.

### 6. Config Cache

```bash
php artisan config:cache
```

Expected: `Configuration cached successfully.`

### 7. Optimize Clear

```bash
php artisan optimize:clear
```

Expected: All cached files cleared successfully.

## Test Database Strategy

Tests use MySQL by default (configured in `phpunit.xml`). The test database is `cwt_academy_test`.

To use SQLite instead (if `pdo_sqlite` is installed):

1. Edit `phpunit.xml`:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

2. Remove MySQL-specific env variables from `phpunit.xml`.

## Troubleshooting

### "Access denied for user 'root'@'localhost'"

Update `.env` with the correct database username and password.

### "Rate limiter [api] is not defined"

This was fixed by defining the rate limiter in `AppServiceProvider`. If you see this error, ensure `AppServiceProvider::boot()` contains the rate limiter definition.

### "GD extension is not installed" in tests

One upload security test is skipped when the GD extension is missing. This is expected and does not affect other tests.

### "Unsupported cipher or incorrect key length"

Ensure `APP_KEY` in `.env.testing` is a valid base64-encoded 32-byte key. Generate one with:

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

## Environment Defaults

The `.env.example` file includes default values for:

- **Admin default credentials** (`ADMIN_DEFAULT_EMAIL`, `ADMIN_DEFAULT_PASSWORD`)
- **Payment proof max size** (`PAYMENT_PROOF_MAX_MB`)
- **Cache store** (`CACHE_STORE=file` to avoid DB dependency)

**Telegram Configuration Note:** 
- The application uses a manual Telegram join-request workflow
- No `TELEGRAM_BOT_TOKEN` or `TELEGRAM_WEBHOOK_SECRET` is required
- Telegram channel URLs are configured in the admin panel
- See `docs/MANUAL_TELEGRAM_ACCESS_FLOW.md` for details
