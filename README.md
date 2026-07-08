# Cwt Academy

A Kurdish/Kurdistan-focused course marketplace and manual-payment approval platform where course content is delivered through private Telegram channels. Manual approval workflow — no Telegram bot integration, no auto-invite links.

## How It Works - Manual Telegram Join-Request Access Flow

1. **Browse** — Students browse the course catalog on the website
2. **Request** — Students fill out a request form and upload payment proof
3. **Approval** — Admin team manually reviews and approves/rejects payments
4. **Telegram Link Shown** — Approved students see the Telegram join/request link on their dashboard
5. **Student Requests** — Student clicks the link and sends a join request in Telegram
6. **Manual Accept** — Staff manually accepts the join request inside Telegram

## Tech Stack

- **Laravel 13** / PHP 8.3+
- **MySQL 8.0** (via Docker)
- **Tailwind CSS v4** / Vite
- **Laravel Sanctum** (API auth)
- **Blade** templates

## Local Development Setup

### Requirements

- PHP 8.3+ with extensions: `pdo_mysql`, `mbstring`, `xml`, `bcmath`, `json`, `openssl`, `fileinfo`
- Composer 2.x
- Node.js 20+ / npm 10+
- Docker & Docker Compose

### Fast Start

```bash
# Clone and enter the project
cd Cwt_academy

# One-time setup
./scripts/setup-local.sh

# Start development
./scripts/run-local.sh
```

### Manual Setup (if scripts don't work)

```bash
# 1. Copy environment files
cp .env.example .env
cp .env.testing.example .env.testing

# 2. Install dependencies
composer install
npm install

# 3. Generate application keys
php artisan key:generate
php artisan key:generate --env=testing

# 4. Start MySQL via Docker
docker-compose up -d

# 5. Wait for MySQL to be ready (usually 10-15 seconds)
docker-compose exec mysql mysqladmin ping -h localhost --wait=30

# 6. Run migrations and seeders
php artisan migrate:fresh --seed

# 7. Build frontend assets
npm run build

# 8. Create storage link
php artisan storage:link

# 9. Start Laravel server
php artisan serve
```

### Browser URLs

| URL | Description |
|-----|-------------|
| http://127.0.0.1:8000 | Public homepage |
| http://127.0.0.1:8000/courses | Course catalog |
| http://127.0.0.1:8000/courses/{slug} | Course detail |
| http://127.0.0.1:8000/courses/{slug}/request | Course request form |
| http://127.0.0.1:8000/track | Track request status |
| http://127.0.0.1:8000/login | Student login |
| http://127.0.0.1:8000/admin | Admin dashboard |
| http://127.0.0.1:8000/admin/requests | Admin course requests |
| http://127.0.0.1:8000/admin/telegram-access | Admin manual Telegram access |

### Local Admin Credentials

> **IMPORTANT:** These credentials are for local development only. The default password is a placeholder. Change it before deploying to production.

| Email | Password | Role |
|-------|----------|------|
| admin@cwtacademy.local | `change-me-with-secure-password` | Super Admin |
| finance@cwtacademy.local | `change-me-with-secure-password` | Finance Manager |
| admin2@cwtacademy.local | `change-me-with-secure-password` | Admin |

Create a custom admin in production:

```bash
php artisan admin:create --name="Admin Name" --email="admin@example.com" --role=super_admin
```

## Helper Scripts

| Script | Purpose |
|--------|---------|
| `./scripts/setup-local.sh` | One-time project setup |
| `./scripts/run-local.sh` | Start dev server |
| `./scripts/test-local.sh` | Run all quality gates |
| `./scripts/reset-local.sh` | Reset database (destructive) |

## Quality Gates

```bash
# Run all checks
./scripts/test-local.sh

# Or individually:
npm run build                 # Frontend build
./vendor/bin/pint --test      # Code style
./vendor/bin/phpstan analyse  # Static analysis
php artisan test              # Tests
```

## Common Problems

### MySQL connection refused
- Ensure Docker is running: `docker-compose up -d`
- Wait for healthcheck: `docker-compose exec mysql mysqladmin ping -h localhost`
- Check port 3306 is not in use: `lsof -i :3306`

### Port 3306 already used
```bash
# Kill existing MySQL on port 3306
sudo lsof -t -i:3306 | xargs kill -9
# Or change the port in docker-compose.yml and .env
```

### APP_KEY missing
```bash
php artisan key:generate
```

### npm build fails
```bash
rm -rf node_modules package-lock.json
npm install
npm run build
```

### Vite manifest missing
```bash
npm run build
```

### Permission denied on scripts
```bash
chmod +x scripts/*.sh
```

### Storage link issue
```bash
rm public/storage
php artisan storage:link
```

### Cache config issue
```bash
php artisan optimize:clear
php artisan config:cache
```

## Reset Everything

```bash
./scripts/reset-local.sh   # Interactive confirmation required
# Or manually:
php artisan migrate:fresh --seed
php artisan optimize:clear
```

## Testing

Tests use MySQL via Docker by default (see `.env.testing`). If you prefer SQLite, install `pdo_sqlite` and change `DB_CONNECTION=sqlite` in `.env.testing`.

```bash
php artisan test
```

## API Endpoints

### Public
- `GET /api/courses` — List active courses
- `GET /api/courses/{slug}` — Course details
- `POST /api/course-requests` — Create request
- `GET /api/course-requests/{tracking_code}` — Track request
- `POST /api/course-requests/{tracking_code}/payment-proof` — Submit proof

### Admin
- `POST /api/admin/login` — Admin login
- `GET /api/admin/dashboard` — Dashboard stats
- `GET /api/admin/courses` — Manage courses
- `GET /api/admin/course-requests` — Manage requests
- `GET /api/admin/payment-proofs` — Review proofs
- `GET /api/admin/telegram-channels` — Manage channels
- `GET /api/admin/telegram-access-grants` — Manage access
- `GET /api/admin/audit-logs` — View audit logs

## Documentation

- [Project Overview](docs/PROJECT_OVERVIEW.md)
- [API Documentation](docs/API_DOCUMENTATION.md)
- [Telegram Flow](docs/TELEGRAM_FLOW.md)
- [Admin Guide](docs/ADMIN_GUIDE.md)
- [Security](docs/SECURITY.md)
- [Deployment](docs/DEPLOYMENT.md)

## License

Proprietary - Cwt Academy
