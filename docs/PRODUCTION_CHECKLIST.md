# Production Security Checklist — CWT Academy 2026

## Pre-Flight (do NOT skip)

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` set to 32+ char random string (`php artisan key:generate`)
- [ ] `FORCE_HTTPS=true` (enforced by `ForceHttps` middleware)
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] `SESSION_ENCRYPT=true`
- [ ] `SESSION_SAME_SITE=strict`
- [ ] `SESSION_LIFETIME=120` (or shorter)
- [ ] Database migrated: `php artisan migrate`
- [ ] DB constraints migration run: `php artisan migrate --path=database/migrations/2026_05_24_000000_add_production_db_constraints.php`
- [ ] Storage linked: `php artisan storage:link`
- [ ] Config cached: `php artisan config:cache`
- [ ] Routes cached: `php artisan route:cache`
- [ ] Views cached: `php artisan view:cache`
- [ ] Maintenance mode off (except during deploys)

## Environment Variables

```env
# Core
APP_NAME="CWT Academy"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# HTTPS / TLS
FORCE_HTTPS=true

# Database (use separate read-only user for app if possible)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cwt_academy
DB_USERNAME=cwt_app
DB_PASSWORD=<STRONG_RANDOM_32+>

# Session hardening
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict

# Mail (configure for verification / password reset)
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourservice.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="CWT Academy"

# CAPTCHA (Cloudflare Turnstile recommended)
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=

# Security config
TRUSTED_PROXIES=173.245.48.0/20,103.21.244.0/22,103.22.200.0/22,...
CSP_REPORT_URI=
AUDIT_RETENTION_DAYS=365

# Admin default password (unset after first seed!)
ADMIN_DEFAULT_PASSWORD=
```

## Nginx Hardening (minimal)

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:...';
    ssl_prefer_server_ciphers off;

    root /var/www/cwt-academy/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive files
    location ~ /\.(env|git|gitignore|gitattributes|lock)$ { deny all; }
    location ~ ^/(storage|bootstrap|config|database|resources|routes|tests)/ { deny all; }

    # Security headers (fallback; app middleware is primary source)
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
```

## Post-Deploy Verification

Run these commands after each deploy:

```bash
php artisan app:check-prod   # If available; otherwise manually verify .env
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

## Cloudflare (if used)

- SSL/TLS encryption mode: **Full (strict)**
- Always Use HTTPS: **ON**
- Security Level: **Medium** or **High**
- Browser Integrity Check: **ON**
- Add IPs to `TRUSTED_PROXIES` env var.

## Monitoring

- [ ] Enable Laravel Telescope or similar in staging (NOT production).
- [ ] Configure Sentry / Bugsnag for error reporting.
- [ ] Set up log rotation for `storage/logs`.
- [ ] Monitor disk space for `storage/app/payment_proofs`.

## Notes

- The application uses `Argon2id` for password hashing in production.
- `role` and `status` fields are protected from mass assignment.
- File uploads validate MIME type and magic bytes.
- Admin routes require both `auth` and `admin` middleware.
- Email verification is enforced for student dashboard access.
