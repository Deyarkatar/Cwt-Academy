# Secrets Audit Report — Cwt Academy (2026 Ultra Audit)

## Audit Scope

Reviewed all configuration files, environment templates, source code, and Docker configuration for hardcoded secrets, sensitive data exposure, and secret management practices.

## Findings

### 1. `.env.example` — Safe Defaults ✅

| Key | Value | Status |
|---|---|---|
| `APP_DEBUG` | `false` | ✅ Safe default |
| `SESSION_SECURE_COOKIE` | `true` | ✅ Safe default |
| `ADMIN_DEFAULT_PASSWORD` | Commented out | ✅ Not exposed |
| `REDIS_PASSWORD` | `null` | ✅ No hardcoded password |
| `MYSQL_ROOT_PASSWORD` | Not set (requires explicit) | ✅ Docker requires it |
| `MYSQL_PASSWORD` | Not set (requires explicit) | ✅ Docker requires it |

### 2. Docker Compose — Secret Handling ✅

- Redis password: `${REDIS_PASSWORD:?Set REDIS_PASSWORD in .env}` — **fails if not set**
- MySQL passwords: `${MYSQL_PASSWORD:?Please set MYSQL_PASSWORD in .env}` — **fails if not set**
- R2/Sentry keys: passed as empty defaults, not hardcoded

### 3. Source Code — No Hardcoded Secrets ✅

- No `env()` calls outside config files
- No API keys in source code
- No private keys in source code
- No database passwords in source code
- `config/security.php` reads all sensitive values from `env()`

### 4. Sensitive Data Redaction ✅

`config/security.audit_redact_keys` redacts the following from audit log payloads:
- `password`, `password_confirmation`, `current_password`
- `remember_token`, `api_token`, `access_token`, `refresh_token`, `token`
- `secret`, `authorization`, `cookie`
- `cf-turnstile-response`, `g-recaptcha-response`, `h-captcha-response`

### 5. Error Responses — No Secret Leakage ✅

- `bootstrap/app.php` exception handler masks 500 errors with generic message
- `APP_DEBUG=false` prevents stack traces in production
- `display_errors=Off` in PHP-FPM
- API 500 responses return `{"ok": false, "message": "Server error"}` only

### 6. Web-Accessible Files — Blocked ✅

Nginx blocks access to:
- `.env` files (dotfile rule)
- `composer.json`, `composer.lock`
- `artisan`
- `.git/` directory
- `*.log`, `*.sql`, `*.bak` files
- `*.blade.php` files

### 7. Honey Tokens ✅

`HoneyTokenGuard` middleware checks all request headers and body for fake secrets:
- `fake_aws_access_key`
- `fake_aws_secret_key`
- `fake_db_password`

If any are detected, request is aborted with 403 and critical log alert.

## Recommendations

1. **Rotate secrets regularly** — Set up a schedule for rotating Redis, MySQL, and API keys
2. **Use Docker secrets** — For production, use Docker Swarm secrets or external secret managers
3. **Never commit `.env`** — `.gitignore` already excludes it; verified no `.env` in repo
4. **Enable CSP reporting** — Set `CSP_REPORT_URI` in production to collect violation reports
5. **Enable Sentry** — Set `SENTRY_LARAVEL_DSN` in production for error tracking
