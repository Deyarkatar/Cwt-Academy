# Cwt Academy — Production Deployment Plan

**Version:** v1.0.0-production-go
**Date:** 2026-07-08
**Repository:** <https://github.com/Deyarkatar/Cwt-Academy>
**Verdict:** GO after full remediation and UI/admin verification.

## 1. VPS / Server Requirements

| Requirement | Specification |
| ----------- | ------------- |
| OS | Ubuntu 24.04 LTS (or Debian 12) x86_64 |
| CPU | 2+ vCPUs (4 recommended for Spline/physics frontend bundle) |
| RAM | 4 GB minimum, 8 GB recommended |
| Disk | 60 GB SSD minimum, 120 GB recommended for logs/proofs/backups |
| Network | Static IPv4 + IPv6, ports 22 (SSH), 80 (HTTP), 443 (HTTPS), 3306 (MySQL from app only) |
| Domain | Point `A` and `AAAA` records to the server IP |
| Services | Nginx 1.24+, PHP-FPM 8.3/8.4, MySQL 8.0+, Redis 7.0+, Composer 2, Node.js 22 LTS |

### Required PHP extensions

```bash
php-cli php-fpm php-mysql php-redis php-mbstring php-xml php-curl php-zip php-bcmath php-intl php-gd php-fileinfo php-opcache php-exif
```

### System-level hardening

- Create a non-root deploy user (e.g., `cwtdeploy`).
- Disable root SSH login and password authentication.
- Configure UFW to allow only 22/80/443.
- Install `fail2ban` for SSH/HTTP brute-force protection.
- Enable unattended security updates.

## 2. `.env` Production Values Checklist

Copy `.env.example` to `.env` on the server and fill every value. Do **not** commit `.env`.

| Variable | Purpose | Check |
| -------- | ------- | ----- |
| `APP_NAME` | Application name | `Cwt Academy` |
| `APP_ENV` | Environment | `production` |
| `APP_KEY` | Laravel encryption key | Generate with `php artisan key:generate` |
| `APP_DEBUG` | Debug mode | `false` |
| `APP_URL` | Public URL | `https://cwtacademy.example.com` |
| `APP_TIMEZONE` | Application timezone | `Asia/Baghdad` |
| `APP_LOCALE` | Default locale | `en` or `ku` |
| `LOG_CHANNEL` | Log channel | `daily` |
| `DB_CONNECTION` / `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | MySQL | Strong unique password |
| `REDIS_HOST` / `REDIS_PASSWORD` / `REDIS_PORT` | Redis cache/queue/sessions | Strong password, bind to localhost |
| `BROADCAST_DRIVER` | Broadcasting | `log` |
| `CACHE_DRIVER` | Cache | `redis` |
| `FILESYSTEM_DISK` | Default storage | `local` or `r2` |
| `QUEUE_CONNECTION` | Queue | `redis` |
| `SESSION_DRIVER` | Sessions | `redis` |
| `SESSION_LIFETIME` | Session lifetime | `120` |
| `MAIL_MAILER` | Mail driver | `smtp` or `postmark` |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | Mail | Valid credentials |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` / `AWS_BUCKET` | R2 / S3 | Only if using R2; see section 4 |
| `CLOUDFLARE_TURNSTILE_SITE_KEY` / `CLOUDFLARE_TURNSTILE_SECRET_KEY` | CAPTCHA | Valid Cloudflare Turnstile keys |
| `TELEGRAM_BOT_TOKEN` | Telegram bot | **Leave unset** (manual workflow) |
| `ADMIN_DEFAULT_PASSWORD` | Initial admin seed password | Strong temporary password; unset after first login |
| `HONEY_TOKENS_FAKE_AWS_ACCESS_KEY` etc. | Honey tokens | Placeholder fake secrets for breach detection |

### Generate application key

```bash
php artisan key:generate
```

## 3. Cloudflare Setup

1. **DNS**
   - Add an `A` record pointing the domain to the server IPv4.
   - Add an `AAAA` record for IPv6 if available.
   - Proxy status: **DNS-only** during initial setup, then enable **Proxied** after SSL is confirmed.

2. **SSL/TLS**
   - Mode: **Full (strict)** once origin certificate is installed.
   - Always Use HTTPS: **On**
   - Automatic HTTPS Rewrites: **On**

3. **Security**
   - Security Level: **High**
   - Bot Fight Mode: **On** (or Super Bot Fight Mode if available)
   - Challenge Passage: **30 minutes**
   - Rate limiting rules for `/api/admin/login`, `/login`, `/register`, `/course-requests/store`

4. **Turnstile**
   - Create a managed challenge widget in Cloudflare Turnstile.
   - Add the site key and secret key to `.env`.
   - Set allowed hostnames to the production domain.

5. **Caching**
   - Page Rules or Cache Rules:
     - Cache static assets (`/build/*`, `/storage/*`) for 1 month.
     - Do **not** cache authenticated admin routes or `/api/*`.

## 4. R2 Storage Setup

If storing payment proofs on Cloudflare R2:

1. Create an R2 bucket (e.g., `cwt-academy-proofs`).
2. Create an R2 API token with:
   - Object Read & Write
   - Bucket: `cwt-academy-proofs`
3. Add the credentials to `.env`:

```env
AWS_ACCESS_KEY_ID=your-r2-access-key
AWS_SECRET_ACCESS_KEY=your-r2-secret-key
AWS_DEFAULT_REGION=auto
AWS_BUCKET=cwt-academy-proofs
AWS_URL=https://cwt-academy-proofs.your-account-id.r2.cloudflarestorage.com
AWS_ENDPOINT=https://your-account-id.r2.cloudflarestorage.com
R2_ACCESS_KEY_ID=your-r2-access-key
R2_SECRET_ACCESS_KEY=your-r2-secret-key
R2_BUCKET=cwt-academy-proofs
R2_ENDPOINT=https://your-account-id.r2.cloudflarestorage.com
```

1. Keep the bucket **private**. Serve proof downloads through Laravel, not via public R2 URL.
2. Enable object lifecycle rules if required by compliance.

If using local disk for proofs, ensure `/storage/app/private/payment_proofs` is not web-accessible and the directory is backed up.

## 5. MySQL / Redis Setup

### MySQL

```sql
CREATE DATABASE cwt_academy_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cwt_app'@'127.0.0.1' IDENTIFIED BY 'STRONG_UNIQUE_PASSWORD';
GRANT ALL PRIVILEGES ON cwt_academy_production.* TO 'cwt_app'@'127.0.0.1';
FLUSH PRIVILEGES;
```

- Bind MySQL to `127.0.0.1` only.
- Enable `mysql_native_password` or `caching_sha2_password` as required.
- Schedule weekly `mysqldump` backups (see Backup Plan).

### Redis

```bash
sudo apt install redis-server
sudo systemctl enable redis-server
```

- Enable Redis AUTH password in `/etc/redis/redis.conf`.
- Bind to `127.0.0.1` only.
- Set `maxmemory 512mb` and `maxmemory-policy allkeys-lru`.
- Use Redis for cache, sessions, queues, and Horizon.

## 6. Queue / Horizon Setup

The project uses Redis queues. Install and configure Laravel Horizon or use `queue:work` supervisors.

### Option A: Supervisor + `queue:work`

```ini
; /etc/supervisor/conf.d/cwt-worker.conf
[program:cwt-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/cwt-academy/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
cwd=/var/www/cwt-academy
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=cwtdeploy
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/cwt-worker.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start cwt-worker:*
```

### Option B: Laravel Horizon

If Horizon is installed:

```bash
php artisan horizon:install
php artisan horizon:publish
```

Run Horizon under Supervisor:

```ini
[program:cwt-horizon]
command=php /var/www/cwt-academy/artisan horizon
cwd=/var/www/cwt-academy
autostart=true
autorestart=true
user=cwtdeploy
redirect_stderr=true
stdout_logfile=/var/log/cwt-horizon.log
```

- Restrict `/horizon` dashboard to admin IPs if exposed.

### Cron

```bash
* * * * * cd /var/www/cwt-academy && php artisan schedule:run >> /dev/null 2>&1
```

## 7. Nginx / SSL Setup

### Obtain SSL certificate

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d cwtacademy.example.com
```

### Nginx configuration

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name cwtacademy.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name cwtacademy.example.com;

    root /var/www/cwt-academy/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/cwtacademy.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/cwtacademy.example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ^~ /storage/ {
        deny all;
    }
}
```

### PHP-FPM

- Set `user = cwtdeploy`, `group = cwtdeploy` in `/etc/php/8.4/fpm/pool.d/www.conf`.
- Set `upload_max_filesize = 20M`, `post_max_size = 20M`, `memory_limit = 512M`.
- Enable and tune OPcache for production.

### File permissions

```bash
sudo chown -R cwtdeploy:cwtdeploy /var/www/cwt-academy
sudo chmod -R 755 /var/www/cwt-academy
sudo chmod -R 775 /var/www/cwt-academy/storage /var/www/cwt-academy/bootstrap/cache
```

## 8. Backup Plan

| Asset | Frequency | Method | Retention |
| ----- | ----------- | ------ | --------- |
| MySQL database | Daily at 02:00 | `mysqldump` to encrypted backup storage | 14 days |
| Payment proof files | Daily | R2 object versioning / local `rsync` to backup server | 30 days |
| `.env` file | After every change | Secure password manager / encrypted vault | Permanent |
| Application code | On every deploy | Git tags + GitHub repository | Indefinite |
| Nginx/PHP configs | Weekly | `rsync` to backup server | 30 days |

### Example database backup script

```bash
#!/bin/bash
# /var/www/cwt-academy/scripts/backup.sh
DB_NAME="cwt_academy_production"
DB_USER="cwt_app"
BACKUP_DIR="/backups/mysql"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p "$BACKUP_DIR"
mysqldump -u "$DB_USER" -p"$DB_PASSWORD" --single-transaction "$DB_NAME" | gzip > "$BACKUP_DIR/cwt_$DATE.sql.gz"
find "$BACKUP_DIR" -name 'cwt_*.sql.gz' -mtime +14 -delete
```

### R2 / local proof backup

- For R2: enable object versioning and configure a lifecycle rule to replicate to another bucket/region.
- For local disk: `rsync -avz --delete /var/www/cwt-academy/storage/app/private/payment_proofs backup-host:/backups/proofs/`

## 9. Rollback Plan

### Before every deploy

1. Tag the current release: `git tag -a v<prev>-pre-deploy -m "Pre-deploy snapshot"`
2. Create a database dump.
3. Create a backup of `storage/app/private/payment_proofs` if using local disk.

### Rollback procedure

1. **Stop queue workers:**

   ```bash
   sudo supervisorctl stop cwt-worker:*
   ```

2. **Restore code:**

   ```bash
   cd /var/www/cwt-academy
   git fetch origin
   git checkout v<previous-stable-tag>
   composer install --no-dev --optimize-autoloader
   php artisan migrate:rollback  # Only if the deploy ran migrations
   ```

3. **Restore database (if needed):**

   ```bash
   zcat /backups/mysql/cwt_YYYYMMDD_HHMMSS.sql.gz | mysql -u cwt_app -p cwt_academy_production
   ```

4. **Rebuild caches:**

   ```bash
   php artisan config:clear && php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan cache:clear
   ```

5. **Restart workers and services:**

   ```bash
   sudo supervisorctl start cwt-worker:*
   sudo systemctl reload nginx
   sudo systemctl reload php8.4-fpm
   ```

6. **Verify rollback** with post-deployment smoke tests.

## 10. Post-Deployment Smoke Tests

Run these immediately after deployment to confirm health:

### 10.1 Health and cache

```bash
cd /var/www/cwt-academy
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### 10.2 Application smoke tests

```bash
php artisan test --filter PublicRequestFlowTest
php artisan test --filter WebAuthTest
php artisan test --filter AdminApprovalTest
php artisan test --filter StorageSecurityTest
```

### 10.3 Manual browser checks

1. **Public pages**
   - Homepage loads and Vite assets render.
   - Course catalog page loads.
   - Course detail page shows price / "Free" label correctly.
   - Public request form renders CAPTCHA and accepts submission.

2. **Student flow**
   - Register a test student account.
   - Log in and access student dashboard.
   - Submit a course request with payment proof.
   - Use the tracking page with the tracking code and email hash.

3. **Admin flow**
   - Log in as admin.
   - View course requests and payment proofs.
   - Approve a pending request and confirm Telegram access grant is created.
   - Download a payment proof file.
   - Confirm lockout after repeated failed login attempts.

4. **Security checks**
   - HTTPS redirect works.
   - Security headers present (`X-Frame-Options`, `X-Content-Type-Options`, etc.).
   - `/storage/` and `/.env` are not accessible via HTTP.
   - Upload a 1 MB payload to a public route and confirm `HoneyTokenGuard` does not hang.

### 10.4 Monitoring

- Verify application logs in `/var/www/cwt-academy/storage/logs/laravel-*.log`.
- Confirm Redis memory usage is stable.
- Confirm queue workers are processing jobs.
- Set up uptime monitoring and alerts for 5xx errors.

## Constraints and Decisions

- **Manual Telegram workflow preserved.** Administrators manually add students to Telegram channels using the generated access grants. No bot token, webhook, or auto-generated invite links are used.
- **No automated Telegram actions.** The `ApproveCourseRequestAction` creates a `TelegramAccessGrant` record with status `PENDING_MANUAL_ADD` for staff to process.
- **Read replica middleware disabled.** All reads/writes use the primary MySQL connection to avoid data-consistency risks.
- **CAPTCHA enforced.** Public course-request form uses Cloudflare Turnstile or MathCaptcha with server-side verification.

## Deployment Checklist Summary

- [ ] Server provisioned and hardened
- [ ] Domain DNS configured
- [ ] `.env` populated with production values
- [ ] MySQL database and user created
- [ ] Redis installed, password-protected, bound to localhost
- [ ] Nginx + SSL configured
- [ ] Code cloned/deployed to `/var/www/cwt-academy`
- [ ] Composer dependencies installed (`--no-dev --optimize-autoloader`)
- [ ] Frontend built (`npm ci && npm run build`)
- [ ] Migrations run (`php artisan migrate --force`)
- [ ] Storage link created if using public assets (`php artisan storage:link`)
- [ ] Caches warmed (`config:cache`, `route:cache`, `view:cache`)
- [ ] Queue workers / Horizon running under Supervisor
- [ ] Cron schedule configured
- [ ] Backups configured and tested
- [ ] Smoke tests passed
- [ ] Monitoring enabled
- [ ] Rollback tag created before deploy
