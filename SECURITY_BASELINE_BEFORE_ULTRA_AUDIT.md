# Security Baseline Before Ultra Audit — Cwt Academy

## Date

2026-01-30

## Current Commit

`90de144` — Security remediation pass (previous session)

## Baseline Verification Results

### Commands Run

| Command | Result |
|---|---|
| `php artisan optimize:clear` | ✅ Cleared successfully |
| `npm run build` | ✅ Built successfully |
| `php artisan test` | ✅ All tests passed |
| `./vendor/bin/pint --test` | ✅ No formatting issues |
| `./vendor/bin/phpstan analyse` | ✅ No errors |

### Known Working Flows

- Homepage (English + Kurdish)
- Courses listing page
- Course detail page
- Course request form (unified with payment proof)
- Tracking page (with email hash for extended data)
- Admin login (web + API)
- Admin dashboard
- Admin course request management (approve/reject)
- Admin payment proof management (approve/reject/download)

## Security Controls Already In Place (Pre-Ultra Audit)

### Authentication

- Rate limiting on login (IP + email based)
- Account lockout with exponential backoff
- Brute force detection middleware (IP blocking after 20 failures)
- CAPTCHA (MathCaptcha for web, Turnstile for API)
- reCAPTCHA v3 on web auth forms
- Password policy: 12+ chars, mixed case, digit, symbol, HIBP check in prod
- Session regeneration on login
- Sanctum tokens with `['admin']` ability (no wildcard)
- Token expiration (240 minutes)

### Authorization

- `EnsureAdminAuthenticated` middleware (auth + verified email + admin role + token ability)
- Laravel Policies for all models
- Self-approval prevention (email match check)
- Mass-assignment protection (User model excludes role/status)
- Dashboard authorization (guest/student/admin/super_admin)

### Input Validation

- Form Requests for all user input
- Status filter validation against enum (CourseRequestController)
- Tracking code format validation (regex `^[A-Z0-9]{16}$`)
- Email hash verification for API payment proof upload
- Tracking API email hash for extended data access

### File Upload

- MIME type validation (jpg, jpeg, png, pdf, webp)
- Magic byte verification
- Max file size (10MB)
- Local disk storage (not public)
- Path traversal prevention
- Virus scan status set to `pending`

### Infrastructure

- Nginx hardening (server_tokens, rate limiting, PHP lockdown, dotfile blocking)
- PHP-FPM hardening (disable_functions, allow_url_fopen off, display_errors off)
- Docker container hardening (no-new-privileges, cap_drop, tmpfs)
- Redis hardening (password required, protected mode, command renaming)
- MySQL hardening (password required, localhost bind, prepared statements)
- Trusted proxy validation (strict, rejects empty/wildcard in production)

### Security Headers

- CSP (nonce-based, strict-dynamic, no unsafe-inline in prod)
- X-Frame-Options: DENY
- X-Content-Type-Options: nosniff
- Referrer-Policy: strict-origin-when-cross-origin
- Permissions-Policy (zero capabilities by default in prod)
- HSTS (prod + HTTPS)
- Server/X-Powered-By stripped

### Audit Trail

- Immutable audit logs (throws on update/delete)
- All auth events logged (success + failure)
- All admin actions logged with actor, entity, old/new values
- Sensitive data redaction in audit payloads
- Request ID correlation

### Other

- Honey token guard (breach detection)
- Force HTTPS middleware (production)
- Open redirect prevention (UrlHelper::safeRedirect)
- XSS prevention (Blade escaping, no `{!!}`, no v-html)
- SQL injection prevention (Eloquent ORM, prepared statements, no raw user input)
- CORS locked to APP_URL
- Session encryption, SameSite=strict, HttpOnly, secure cookie

## Risks Already Fixed (Previous Session)

1. ✅ API payment proof upload now requires `email_hash` verification
2. ✅ Virus scan status explicitly set to `pending` on upload
3. ✅ Docker Redis password no longer hardcoded with weak default
4. ✅ Docker PHP container uses granular read-only volume mounts
5. ✅ `.env.example` safe defaults (APP_DEBUG=false, SESSION_SECURE_COOKIE=true)
6. ✅ `.env.example` admin password commented out
7. ✅ Horizon dashboard protected by admin auth gate
8. ✅ Admin CourseRequestController status filter validated against enum
9. ✅ Admin tokens use `['admin']` ability, not `['*']`
10. ✅ Unused import removed from Admin CourseRequestController
