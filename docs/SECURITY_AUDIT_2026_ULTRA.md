# SECURITY_AUDIT_2026_ULTRA.md

**Cwt Academy — Full-Stack Military-Grade Security Audit**

*Classification: PRODUCTION READINESS / RED-TEAM ASSESSMENT*

---

## Table of Contents

1. [Authentication & Authorization](#1-authentication--authorization)
2. [Session Security](#2-session-security)
3. [API & Sanctum](#3-api--sanctum)
4. [Input Validation & Injection](#4-input-validation--injection)
5. [Cross-Site Scripting (XSS)](#5-cross-site-scripting-xss)
6. [Cross-Site Request Forgery (CSRF)](#6-cross-site-request-forgery-csrf)
7. [Content Security Policy (CSP)](#7-content-security-policy-csp)
8. [File Upload Security](#8-file-upload-security)
9. [Open Redirect & SSRF](#9-open-redirect--ssrf)
10. [Rate Limiting & Brute Force](#10-rate-limiting--brute-force)
11. [Sensitive Data Exposure](#11-sensitive-data-exposure)
12. [Audit & Logging](#12-audit--logging)
13. [Infrastructure & Docker](#13-infrastructure--docker)
14. [Dependency Security](#14-dependency-security)

---

## 1. Authentication & Authorization

### 1.1 Login Endpoint Enumeration (HIGH)

- **Severity:** HIGH | **CVSS:** 5.3 | **CWE:** CWE-204
- **Location:** `routes/web.php:286-314`, `app/Http/Controllers/Admin/AuthController.php:62-91`
- **Issue:** The login flows return **different error messages** for invalid credentials, suspended accounts, and unverified emails. This allows an attacker to enumerate valid user accounts, distinguish admins from students, and identify unverified accounts.
- **Evidence:**
  - Web: `'email' => __('auth.invalid_credentials')` vs `'email' => __('auth.account_suspended')` vs redirect to `verification.notice`
  - API: `'message' => 'Invalid credentials.'` vs `'message' => 'Account is suspended.'` vs `'message' => 'Email verification required...'`
- **Exploit:** `curl -X POST /login -d 'email=victim@example.com&password=wrong'` → observe response to determine account existence and status.
- **Fix:** Return a **single generic message** for all authentication failures: "Invalid credentials or account unavailable."

### 1.2 Mass Assignment Prevention (MEDIUM — DESIGN VERIFIED SAFE)

- **Location:** `app/Models/User.php`
- **Status:** ACCEPTABLE RISK
- **Observation:** `role` and `status` are explicitly excluded from `$fillable` via `#[Fillable(...)]` attribute. Registration in `routes/web.php:372-379` assigns them explicitly. This is 2026-grade mass-assignment defense.

### 1.3 Admin Role Separation (LOW)

- **Location:** `app/Models/User.php:46-98`
- **Status:** ACCEPTABLE
- **Observation:** Role hierarchy is well-designed with `SUPER_ADMIN` > `ADMIN` > `FINANCE_MANAGER`. No single role has absolute power except `SUPER_ADMIN`. `delete` on courses is restricted to `SUPER_ADMIN` only.

---

## 2. Session Security

### 2.1 Session Configuration Defaults (MEDIUM)

- **Severity:** MEDIUM | **CVSS:** 4.3 | **CWE:** CWE-319
- **Location:** `config/session.php:50`, `config/session.php:172`
- **Issue:** `SESSION_ENCRYPT` defaults to `false`; `SESSION_SECURE_COOKIE` defaults to `false`. The `.env.example` sets them correctly, but if an operator deploys without copying `.env` properly, sessions are transmitted unencrypted over HTTP and stored as plaintext.
- **Fix:** Change defaults in `config/session.php` to `true` for both values, or add an abort-on-missing check in `AppServiceProvider`.

### 2.2 Session Serialization (ACCEPTABLE)

- **Location:** `config/session.php:231`
- **Status:** SAFE
- **Observation:** `serialization` is explicitly set to `'json'`, preventing PHP object deserialization attacks even if the `APP_KEY` is compromised.

---

## 3. API & Sanctum

### 3.1 Missing Sanctum Configuration (CRITICAL)

- **Severity:** CRITICAL | **CVSS:** 7.5 | **CWE:** CWE-16
- **Location:** `config/` (file absent)
- **Issue:** There is **no `config/sanctum.php`** file. Sanctum operates entirely with framework defaults, meaning:
  - Token expiration defaults to whatever Laravel hardcodes (historically 1 year in some versions, or 480 minutes)
  - Cookie `secure` and `same_site` are uncontrolled
  - SPA authentication state is unverified
  - The `expiresAt` parameter in `AuthController.php:98` uses `config('sanctum.expiration')` which returns `null` (fallbacks to 480), but other Sanctum behaviors are opaque.
- **Fix:** Publish and harden `config/sanctum.php` with explicit `expiration`, `token_prefix`, `middleware`, and `cookie` settings.

### 3.2 API Guard Configuration Gap (MEDIUM)

- **Location:** `config/auth.php:40-45`
- **Issue:** Only a `web` guard is defined. No dedicated `api` guard exists. Sanctum's `auth:sanctum` middleware works but falls back to implicit behavior. If a future developer adds an `api` guard without understanding the Sanctum integration, token validation could break or fall back to session cookies.
- **Fix:** Explicitly define a `sanctum` guard and reference it in `auth.defaults.guard` logic or API middleware groups.

---

## 4. Input Validation & Injection

### 4.1 SQL Injection (ACCEPTABLE)

- **Status:** SAFE
- **Observation:** All database queries use Eloquent/Query Builder with parameterized bindings. Raw SQL is only used in `DB::statement` for `CHECK` constraints in migrations, which are hardcoded and safe.

### 4.2 LIKE Wildcard Abuse / ReDoS (MEDIUM)

- **Severity:** MEDIUM | **CVSS:** 4.3 | **CWE:** CWE-89
- **Location:** `app/Services/Courses/CourseService.php:43`
- **Issue:** The `search` filter uses `LIKE '%{$search}%'` where `$search` is user-controlled. While parameterized, MySQL `LIKE` treats `%` and `_` as wildcards. An attacker can submit `%` to match all courses, or craft slow patterns that bypass indexes and cause full table scans.
- **Fix:** Escape `%` and `_` in search strings before passing to `like`, or use full-text search instead.

---

## 5. Cross-Site Scripting (XSS)

### 5.1 Blade Auto-Escaping (ACCEPTABLE)

- **Status:** SAFE
- **Observation:** All Blade templates use `{{ }}` syntax which auto-escapes HTML entities. No `{!! !!}` unescaped output was found in public or student views.

### 5.2 Flash Message Injection (LOW)

- **Location:** `resources/views/components/flash-messages.blade.php`
- **Status:** ACCEPTABLE
- **Observation:** `session('success')`, `session('error')`, and `$errors->all()` are rendered with `{{ }}`. Safe.

### 5.3 `strip_tags` Insufficiency in Rejection Notes (MEDIUM)

- **Severity:** MEDIUM | **CVSS:** 5.4 | **CWE:** CWE-79
- **Location:** `app/Actions/CourseRequests/RejectCourseRequestAction.php:77-84`
- **Issue:** `sanitizePublicNote` uses `strip_tags($reason)` then `substr()`. `strip_tags` does not sanitize HTML entities or event handlers in malformed tags. If the admin enters a reason like `<img src=x onerror=alert(1)>`, `strip_tags` removes the tag but leaves `onerror=alert(1)` as text. While Blade escapes output, if this text is ever rendered in a context without escaping (e.g., a JSON API consumed by a frontend using `v-html`), XSS is possible.
- **Fix:** Use `htmlspecialchars(..., ENT_QUOTES | ENT_HTML5, 'UTF-8')` after `strip_tags`, or apply `e()` helper when rendering.

---

## 6. Cross-Site Request Forgery (CSRF)

### 6.1 Web Routes (ACCEPTABLE)

- **Status:** SAFE
- **Observation:** All mutating web routes use `POST`. Logout uses `POST`. The `csrf_token()` meta tag is present in `layouts/app.blade.php`. No `Route::get` mutations found.

---

## 7. Content Security Policy (CSP)

### 7.1 `@vite` Directive Missing Nonces (CRITICAL)

- **Severity:** CRITICAL | **CVSS:** 7.5 | **CWE:** CWE-16
- **Location:** `resources/views/layouts/app.blade.php:14`, `app/Http/Middleware/SecurityHeaders.php:141-145`
- **Issue:** The CSP `script-src` requires `nonce-{$nonce}` + `strict-dynamic` in production. Laravel's `@vite` directive injects `<script type="module" src="...">` tags that **do not carry the nonce**. In production, browsers will block all Vite-injected scripts, breaking the entire frontend.
- **Evidence:**
  - `@vite(['resources/css/app.css', 'resources/js/app.js'])` generates tags like `<script type="module" src="http://localhost:5173/@vite/client">` during dev, and built `<script type="module" src="/build/assets/app-xxx.js">` in production.
  - Neither tag receives `nonce="..."`.
- **Fix:** Replace `@vite` with manually nonce-tagged script/link tags, or use a custom Vite directive that injects nonces.

### 7.2 Inline `style` Attributes Blocked (CRITICAL)

- **Severity:** CRITICAL | **CVSS:** 7.5 | **CWE:** CWE-16
- **Location:** All views using Material Symbols with `style="font-variation-settings: ..."`
- **Issue:** Production CSP has `style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com`. Inline `style="..."` attributes are **blocked** because `unsafe-inline` is not present for styles in production (only in dev). This breaks all Material Symbols icon rendering.
- **Fix:** Move inline styles to a `<style nonce="...">` block or use a CSS class-based approach.

---

## 8. File Upload Security

### 8.1 Magic Bytes Validation (ACCEPTABLE)

- **Status:** SAFE
- **Location:** `app/Services/Payments/ManualPaymentService.php:126-166`
- **Observation:** File uploads validate MIME type via `getMimeType()`, then re-validate against magic bytes (JPEG, PNG, WEBP, PDF signatures). This prevents polyglot and MIME-confusion attacks.

### 8.2 Upload Path Isolation (MEDIUM)

- **Severity:** MEDIUM | **CVSS:** 5.3 | **CWE:** CWE-22
- **Location:** `app/Services/Payments/ManualPaymentService.php:77`
- **Issue:** Files are stored in `storage/app/payment_proofs/` with UUID filenames. However, there is **no per-user directory isolation**. If an attacker compromises one filename (via brute force or side channel), they can access any proof file.
- **Fix:** Store files in `payment_proofs/{course_request_id}/{uuid}.{ext}` directories.

### 8.3 Download Path Validation Gap (HIGH)

- **Severity:** HIGH | **CVSS:** 7.5 | **CWE:** CWE-22
- **Location:** `routes/web.php:230`, `app/Http/Controllers/Admin/PaymentProofController.php:68`
- **Issue:** `UrlHelper::safePath($path)` only checks for `..` sequences. It does **not** verify the path starts with `payment_proofs/` or prevent absolute paths like `/etc/passwd`. If a database row is manipulated to contain `proof_file_path = '/etc/passwd'`, the download route would attempt to serve it (though `Storage::disk('local')` restricts to the storage root).
- **Fix:** Add a prefix check: `str_starts_with($path, 'payment_proofs/')`.

---

## 9. Open Redirect & SSRF

### 9.1 Locale Switch Redirect (ACCEPTABLE)

- **Status:** SAFE
- **Location:** `routes/web.php:28-38`
- **Observation:** Uses `UrlHelper::safeRedirect()` which validates same-origin and rejects protocol-relative URLs.

### 9.2 SSRF via Telegram URL (LOW)

- **Severity:** LOW | **CVSS:** 3.1 | **CWE:** CWE-918
- **Location:** `app/Support/Security/UrlHelper.php:84-101`
- **Issue:** `safeTelegramUrl` only validates the host is `t.me`, `telegram.me`, or `telegram.org`. It does not validate the path. An attacker could store `https://t.me/@attackerchannel` as a Telegram channel URL, which is valid per the validator but could be used for social engineering.
- **Fix:** Add a path regex to ensure the URL matches known Telegram channel patterns (e.g., `/joinchat/...` or `/+[a-zA-Z0-9_]+`).

---

## 10. Rate Limiting & Brute Force

### 10.1 Distributed Brute Force on Admin API (MEDIUM)

- **Severity:** MEDIUM | **CVSS:** 5.3 | **CWE:** CWE-307
- **Location:** `app/Providers/AppServiceProvider.php:49-51`
- **Issue:** The `admin-login` rate limiter is `Limit::perMinute(5)->by($request->ip())`. An attacker with a botnet can rotate IPs and attempt 5 guesses per IP per minute per account, bypassing the per-email limit which is only enforced in the controller itself.
- **Fix:** Add a per-account fallback limiter keyed by email hash, even for API login.

### 10.2 Rate Limit Key Collision Behind Proxy (MEDIUM)

- **Severity:** MEDIUM | **CVSS:** 5.3 | **CWE:** CWE-307
- **Location:** `routes/web.php:250-251`, `bootstrap/app.php:33-40`
- **Issue:** If `TRUSTED_PROXIES` is empty in production (misconfiguration), `$request->ip()` returns the load balancer IP, causing all users to share the same rate-limit bucket.
- **Fix:** Add a startup check in `AppServiceProvider` that warns/fails when `TRUSTED_PROXIES` is empty in production.

---

## 11. Sensitive Data Exposure

### 11.1 Health Check Reconnaissance (HIGH)

- **Severity:** HIGH | **CVSS:** 5.3 | **CWE:** CWE-200
- **Location:** `app/Http/Controllers/HealthCheckController.php`
- **Issue:** The `/health` endpoint is public and returns detailed internal diagnostics: database driver, cache driver, queue driver, storage disk names, latency timings, and exact error messages on failure. This aids attackers in profiling the stack before exploitation.
- **Fix:** Restrict detailed health checks to authenticated admin users. Public health should return only `{"ok": true}` or `{"ok": false}`.

### 11.2 Payment PII in Plaintext (MEDIUM)

- **Severity:** MEDIUM | **CVSS:** 5.3 | **CWE:** CWE-311
- **Location:** `database/migrations/2024_01_01_000060_create_course_requests_table.php`, `2024_01_01_000070_create_payment_proofs_table.php`
- **Issue:** Student names, emails, phone numbers, transaction references, and sender names are stored in plaintext. No column-level encryption is applied.
- **Fix:** Implement Laravel's encrypted casts for sensitive fields (phone, email, transaction_reference).

---

## 12. Audit & Logging

### 12.1 Audit Log Mutability (HIGH)

- **Severity:** HIGH | **CVSS:** 6.5 | **CWE:** CWE-778
- **Location:** `app/Models/AuditLog.php`
- **Issue:** `AuditLog` sets `UPDATED_AT = null` but does not prevent `UPDATE` or `DELETE` operations. A compromised admin account or malicious insider with DB access can modify or erase audit history.
- **Fix:** Add `Model:: preventingSilentlyDiscardingAttributes()` or DB triggers to reject `UPDATE`/`DELETE` on `audit_logs`.

### 12.2 Missing Request ID Correlation (MEDIUM)

- **Severity:** MEDIUM | **CVSS:** 4.3 | **CWE:** CWE-778
- **Location:** `app/Services/Audit/AuditLogger.php`
- **Issue:** Audit log entries do not include the `X-Request-ID` generated by `AssignRequestId`. This makes it impossible to correlate audit events with application logs during incident response.
- **Fix:** Add `request_id` column to `audit_logs` table and populate it from `request()->header('X-Request-ID')`.

---

## 13. Infrastructure & Docker

### 13.1 Hardcoded Docker Secrets (CRITICAL)

- **Severity:** CRITICAL | **CVSS:** 7.5 | **CWE:** CWE-798
- **Location:** `docker-compose.yml:7-10`
- **Issue:** MySQL `root_password`, `cwt_password`, and database credentials are hardcoded in the Docker Compose file. If this file is committed to a repository (even a private one), secrets are exposed.
- **Fix:** Use `${MYSQL_ROOT_PASSWORD}` interpolation and a `.env` file, or Docker Secrets.

### 13.2 MySQL Authentication Plugin (MEDIUM)

- **Severity:** MEDIUM | **CVSS:** 4.3 | **CWE:** CWE-327
- **Location:** `docker-compose.yml:16-17`
- **Issue:** `--default-authentication-plugin=mysql_native_password` forces the older, less secure authentication plugin. MySQL 8.0 recommends `caching_sha2_password`.
- **Fix:** Remove the override and use the default `caching_sha2_password`, ensuring application connectivity is tested.

---

## 14. Dependency Security

### 14.1 `roave/security-advisories` (ACCEPTABLE)

- **Status:** SAFE
- **Location:** `composer.json:22`
- **Observation:** The project includes `roave/security-advisories` in `require-dev`, which blocks installation of known-vulnerable packages.

### 14.2 `composer audit` Script (ACCEPTABLE)

- **Status:** SAFE
- **Location:** `composer.json:37`
- **Observation:** A `security-audit` script is defined. This should be run in CI before every deployment.

---

*End of SECURITY_AUDIT_2026_ULTRA.md*

---

## Post-Refactor Performance Addendum (2026-05-26)

### Infrastructure Changes
- Redis added for cache, session, and queue
- Docker stack now includes nginx, php-fpm, queue-worker, scheduler
- OPcache enabled with JIT tracing
- Route caching now functional (zero closures)

### Database Changes
- 11 new production indexes added
- Query optimizations applied to dashboard and payment flows
- N+1 queries eliminated in critical paths

### Frontend Changes
- Spline 3D lazy-loaded with IntersectionObserver
- Google Fonts consolidated with preload
- Image lazy loading added to course cards
