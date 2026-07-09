# Security Findings Verification — Cwt Academy 2026

## Summary

Each finding from the initial security audit was verified against the actual codebase. Findings are marked as **confirmed**, **false positive**, or **already fixed**.

---

## P0 / High Severity

### 1. Admin login must require admin role before issuing admin token

- **Status:** CONFIRMED
- **File:** `app/Http/Controllers/Admin/AuthController.php` (lines 88-105)
- **Risk:** Any authenticated user (including STUDENT role) could obtain an admin Sanctum token with wildcard abilities by submitting valid credentials to `/api/admin/login`.
- **Planned fix:** Add `isAdmin()` check before token creation. Return generic 403 error for non-admin users.

### 2. Account lockout/rate-limit must fail safely when cache/Redis is unavailable

- **Status:** FALSE POSITIVE
- **File:** `app/Services/Auth/AccountLockoutService.php` (lines 33-55)
- **Verification:** The code already had a try/catch on cache operations. The original audit claimed it returned `false` (fail open), but the actual code was updated to use a database fallback via `AuditLog` entries. The `isLockedDatabaseFallback()` method queries recent `LOGIN_FAILED` audit entries from the same IP when cache is unavailable.
- **No fix needed.**

### 3. Admin tokens must not use wildcard `['*']`

- **Status:** CONFIRMED
- **File:** `app/Http/Controllers/Admin/AuthController.php` (line 111)
- **Risk:** Tokens issued with `['*']` abilities violate least-privilege principle.
- **Planned fix:** Replace `['*']` with `['admin']`. Add token ability check in `EnsureAdminAuthenticated` middleware.

---

## P1 / Medium Severity

### 4. Payment proof upload must be protected against unauthorized/fake uploads

- **Status:** CONFIRMED (API endpoint)
- **File:** `app/Http/Controllers/Api/RequestTrackingController.php` (lines 113-130)
- **Risk:** The API `storePaymentProof` endpoint accepted uploads with only a tracking code — no email verification required. Anyone with a tracking code could upload fake payment proofs.
- **Planned fix:** Require `email_hash` parameter matching the course request's student email.

### 5. Tracking page must not leak sensitive information

- **Status:** FALSE POSITIVE (already mitigated)
- **File:** `app/Http/Controllers/Api/RequestTrackingController.php` (lines 16-84)
- **Verification:** The tracking endpoint already implements email_hash gating. Without `email_hash`, only `tracking_code`, `status`, and `course_title` are returned. With `email_hash`, additional payment/telegram data is returned. Rate limiting is applied via `TrackingRateLimitMiddleware` (20 requests/min per IP, 30 per prefix).
- **No fix needed.**

### 6. Payment proof download must enforce authorization and safe storage disk handling

- **Status:** FALSE POSITIVE (already protected)
- **File:** `app/Http/Controllers/Admin/PaymentProofController.php` (lines 55-99), `app/Http/Controllers/Web/AdminPaymentProofDownloadController.php` (lines 14-37)
- **Verification:** Both API and web download controllers enforce authorization via `PaymentProofPolicy::download()` (requires `canManageRequests()`). Path traversal is blocked by `UrlHelper::safePaymentProofPath()`. Content-Type is overridden to prevent MIME sniffing. Cache-Control headers prevent caching.
- **No fix needed.**

### 7. File uploads must be hardened

- **Status:** PARTIALLY CONFIRMED
- **File:** `app/Services/Payments/ManualPaymentService.php` (lines 27-163)
- **Verification:** MIME type validation, magic bytes validation, file content validation, extension allowlist, random UUID filenames, and size limits were all already implemented. However, `virus_scan_status` was not explicitly set on proof creation (relied on DB default).
- **Planned fix:** Explicitly set `virus_scan_status` to `'pending'` on proof creation.

### 8. Docker production config must not use default Redis password

- **Status:** CONFIRMED
- **File:** `docker-compose.yml` (lines 41, 120, 132, 149, 176, 221, 266)
- **Risk:** Default Redis password `change-me-in-production` was hardcoded as fallback in all service definitions.
- **Planned fix:** Replace `${REDIS_PASSWORD:-change-me-in-production}` with `${REDIS_PASSWORD:?Set REDIS_PASSWORD in .env}` to require explicit configuration.

### 9. Docker production config must not mount full source code including `.env`

- **Status:** CONFIRMED
- **File:** `docker-compose.yml` (line 55)
- **Risk:** `volumes: - .:/var/www/html` exposed the entire project including `.env` file with all secrets.
- **Planned fix:** Replace with granular read-only mounts for each needed directory, excluding `.env`, tests, scripts, and docs.

### 10. Horizon/dashboard must be protected by auth or disabled in production

- **Status:** CONFIRMED
- **File:** No `HorizonServiceProvider` existed
- **Risk:** Horizon dashboard was accessible without authentication.
- **Planned fix:** Create `HorizonServiceProvider` with a Gate requiring admin role + verified email.

### 11. `.env.example` must use safe production defaults

- **Status:** CONFIRMED
- **File:** `.env.example` (lines 4, 35, 50, 100)
- **Risk:** `APP_DEBUG=true`, `SESSION_SECURE_COOKIE=false`, default admin password, and weak Redis password.
- **Planned fix:** Set `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, remove default admin password, remove weak Redis password.

---

## P2 / Low Severity

### 12. Course request form must have bot protection and strong throttling

- **Status:** FALSE POSITIVE (already protected)
- **File:** `app/Http/Controllers/Web/CourseRequestController.php` (lines 44-47), `routes/web.php` (lines 46-48)
- **Verification:** CAPTCHA verification is enforced via `CaptchaGuard` before course request creation. Rate limiting is applied via `throttle:5,1` middleware. The API endpoint also has `throttle:5,1`.
- **No fix needed.**

### 13. Audit logs should be tamper-resistant

- **Status:** FALSE POSITIVE (already protected)
- **File:** `app/Models/AuditLog.php` (lines 44-55)
- **Verification:** The `AuditLog` model already has boot hooks that throw `RuntimeException` on both `updating` and `deleting` events, making rows immutable and undeletable through Eloquent. The `UPDATED_AT` constant is set to `null` preventing timestamp updates.
- **No fix needed.**

### 14. Admin course request status filter must validate against enum/allowed values

- **Status:** CONFIRMED
- **File:** `app/Http/Controllers/Admin/CourseRequestController.php` (lines 24-26)
- **Risk:** Any string value was accepted as a status filter without validation against the `CourseRequestStatus` enum.
- **Planned fix:** Validate status parameter against `CourseRequestStatus::cases()` values. Return 422 for invalid values.
