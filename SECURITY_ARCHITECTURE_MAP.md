# Security Architecture Map — Cwt Academy (2026 Ultra Audit)

## 1. Application Overview

Cwt Academy is a Laravel 11 course platform with:
- **Public course browsing** (catalog, course detail)
- **Course request flow** (student submits request + payment proof)
- **Tracking page** (students track request status via tracking code + email hash)
- **Admin panel** (approve/reject requests, manage courses, categories, instructors)
- **Manual Telegram workflow** (admin manually adds approved students to private Telegram channels — no bot, no webhook, no auto-invite)
- **API** (Sanctum token-based for admin operations)
- **Web** (session-based for admin + student auth)

## 2. Authentication & Authorization

### Authentication Entry Points
| Entry Point | Method | Protection |
|---|---|---|
| `/login` (web) | POST | Rate limit (5/IP, 5/email), MathCaptcha, reCAPTCHA v3, session regeneration |
| `/api/admin/login` | POST | Rate limit (10/IP, 5/email), Turnstile CAPTCHA, AccountLockoutService |
| `/register` (web) | POST | MathCaptcha, reCAPTCHA v3, PasswordPolicy (12+ chars, mixed case, digit, symbol) |

### Authorization Layers
1. **`EnsureAdminAuthenticated` middleware** — checks auth, email verification, admin role, Sanctum token `admin` ability
2. **Laravel Policies** — `CourseRequestPolicy`, `PaymentProofPolicy`, `CoursePolicy`, `CategoryPolicy`, `InstructorPolicy`, `TelegramChannelPolicy`, `TelegramAccessGrantPolicy`, `AuditLogPolicy`
3. **Self-approval prevention** — admin cannot approve own course request (email match check)
4. **Mass-assignment protection** — `User` model excludes `role` and `status` from `$fillable`

### Token Security
- Sanctum tokens with `['admin']` abilities (no wildcard `*`)
- Token expiration: 240 minutes (configurable)
- Token prefix: `cwt_`
- Token revoked on logout

## 3. Middleware Stack

### Global Middleware (order: outermost first)
1. `AdminAccountLockoutMiddleware` — blocks locked IPs/emails from login routes
2. `ForceHttps` — 301 HTTP→HTTPS in production (exempts local/health checks)
3. `BruteForceDetectionMiddleware` — blocks IP after 20 failed attempts in 5 min (1hr block)
4. `AssignRequestId` — assigns UUID for log correlation
5. `HoneyTokenGuard` — detects leaked honey-token secrets in requests
6. `TrustProxies` — strict validation, rejects empty/wildcard in production

### Web Group
1. `SetLocale` — locale detection
2. `TrackingRateLimitMiddleware` — rate limits tracking lookups (20/IP, 30/prefix per min)
3. `SecurityHeaders` — CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, HSTS, Permissions-Policy
4. `ResponseCacheMiddleware` — response caching

### API Group
1. `ThrottleRequests:api` — API rate limiting
2. `SecurityHeaders` — security headers (CSP skipped for JSON)

## 4. File Upload Security

- **MIME validation**: `jpg`, `jpeg`, `png`, `pdf`, `webp` only
- **Magic byte verification**: checks actual file content, not just extension
- **Max file size**: 10MB (enforced in PHP `upload_max_filesize` + nginx `client_max_body_size`)
- **Storage disk**: `local` (not `public`) — files not web-accessible
- **Path traversal prevention**: `UrlHelper::safePaymentProofPath()` validates path starts with `payment_proofs/`
- **Virus scan status**: explicitly set to `pending` on creation
- **API email hash verification**: requires `email_hash` matching `sha256(student_email)` for API uploads

## 5. File Download Security

- **Authorization**: `PaymentProofPolicy::download()` requires `canManageRequests()`
- **Path validation**: `UrlHelper::safePaymentProofPath()` prevents traversal
- **Content-Type override**: stored MIME used to prevent sniffing
- **Content-Disposition**: forced as `attachment` to prevent inline rendering
- **Cache-Control**: `private, no-store, max-age=0`

## 6. Input Validation

- **Form Requests**: `StoreCourseRequestWithProofRequest`, `StoreCourseRequest`, etc.
- **Status filter validation**: `Admin\CourseRequestController::index` and `Admin\PaymentProofController::index` validate against enum values
- **Tracking code validation**: regex `^[A-Z0-9]{16}$` on API, format check on web
- **Email hash verification**: `sha256(strtolower(trim(student_email)))` for tracking + payment proof API

## 7. Database Security

- **Prepared statements**: `PDO::ATTR_EMULATE_PREPARES => false` (real server-side)
- **Stacked query prevention**: `Mysql::ATTR_MULTI_STATEMENTS => false`
- **Strict mode**: MySQL strict mode enabled
- **No raw SQL**: only `selectRaw('1')` in subquery (no user input)
- **Eloquent ORM**: all queries use parameterized bindings

## 8. Session Security

| Setting | Value |
|---|---|
| Driver | `redis` |
| Encryption | `true` |
| Lifetime | 120 minutes |
| Cookie Secure | `true` (env: `SESSION_SECURE_COOKIE`) |
| Cookie HttpOnly | `true` |
| Cookie SameSite | `strict` |
| Serialization | `json` |
| Expire on close | `false` |

## 9. CORS Configuration

- **Paths**: `api/*`, `sanctum/csrf-cookie`
- **Allowed origins**: `APP_URL` only (no wildcards)
- **Allowed methods**: standard REST methods
- **Allowed headers**: standard + `X-Request-ID`, `X-CSRF-TOKEN`, `X-XSRF-TOKEN`
- **Supports credentials**: `true`

## 10. Security Headers

| Header | Value |
|---|---|
| Content-Security-Policy | Nonce-based, `strict-dynamic`, no `unsafe-inline` (prod), no `unsafe-eval` |
| X-Frame-Options | `DENY` |
| X-Content-Type-Options | `nosniff` |
| Referrer-Policy | `strict-origin-when-cross-origin` |
| Permissions-Policy | Zero capabilities by default (production) |
| Strict-Transport-Security | `max-age=63072000; includeSubDomains; preload` (prod + HTTPS) |
| X-XSS-Protection | `0` (deprecated, explicitly disabled) |
| Server | Stripped |
| X-Powered-By | Stripped |

## 11. Docker/Infrastructure Security

- **Nginx**: `server_tokens off`, PHP file lockdown (only `index.php` reaches FPM), method filtering, rate limiting zones
- **PHP-FPM**: `disable_functions` (exec, passthru, shell_exec, system, proc_open, popen, etc.), `allow_url_fopen=off`, `display_errors=off`, `expose_php=off`
- **Redis**: password required, protected mode, dangerous commands renamed/disabled
- **MySQL**: bound to `127.0.0.1`, password required
- **Container hardening**: `no-new-privileges`, `cap_drop: ALL`, minimal `cap_add`, `tmpfs: noexec,nosuid`
- **Volume mounts**: granular read-only mounts for php container, writable only for `storage/` and `bootstrap/`

## 12. Audit Trail

- **Immutable**: `AuditLog` model throws `RuntimeException` on update/delete
- **No updated_at**: `UPDATED_AT = null` — no update timestamp
- **Sensitive data redaction**: `config/security.audit_redact_keys` strips passwords, tokens, secrets
- **Login attempts**: both successful and failed logins logged
- **Payment proof actions**: submitted, approved, rejected, downloaded all logged
- **Request ID correlation**: every audit log entry includes `request_id`

## 13. Key File Locations

| Component | Path |
|---|---|
| Bootstrap/middleware | `bootstrap/app.php` |
| Security headers | `app/Http/Middleware/SecurityHeaders.php` |
| Brute force detection | `app/Http/Middleware/BruteForceDetectionMiddleware.php` |
| Account lockout | `app/Services/Auth/AccountLockoutService.php` |
| Honey token guard | `app/Http/Middleware/HoneyTokenGuard.php` |
| Force HTTPS | `app/Http/Middleware/ForceHttps.php` |
| Trusted proxy validator | `app/Support/Security/TrustedProxyValidator.php` |
| URL helper | `app/Support/Security/UrlHelper.php` |
| Password policy | `app/Support/Security/PasswordPolicy.php` |
| Manual payment service | `app/Services/Payments/ManualPaymentService.php` |
| Admin auth controller | `app/Http/Controllers/Admin/AuthController.php` |
| Web auth controller | `app/Http/Controllers/Web/AuthWebController.php` |
| API tracking controller | `app/Http/Controllers/Api/RequestTrackingController.php` |
| Admin course request | `app/Http/Controllers/Admin/CourseRequestController.php` |
| Admin payment proof | `app/Http/Controllers/Admin/PaymentProofController.php` |
| Nginx config | `docker/nginx/nginx.conf` |
| PHP-FPM config | `docker/php/www.conf` |
| PHP ini | `docker/php/php.ini` |
| Docker compose | `docker-compose.yml` |
| Security config | `config/security.php` |
| CORS config | `config/cors.php` |
| Sanctum config | `config/sanctum.php` |
| Session config | `config/session.php` |
| Database config | `config/database.php` |
