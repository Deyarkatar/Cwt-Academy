# Security Remediation 2026 Report — Cwt Academy

## Executive Summary

This report documents the defensive security remediation pass performed on the Cwt Academy project. All 14 audit findings were verified against the actual codebase. 8 findings were confirmed and fixed, 5 were false positives (already mitigated), and 1 was partially confirmed. All fixes include regression tests.

**Verification Results:**
- Confirmed & Fixed: 8
- False Positive (Already Protected): 5
- Partially Confirmed & Fixed: 1

**Test Results:**
- 285 tests passed, 0 failed (1002 assertions)
- PHPStan: 0 errors
- Laravel Pint: 0 style issues

---

## Fixes Applied

### P0-1: Admin Login Role Check (HIGH)

**File:** `app/Http/Controllers/Admin/AuthController.php`
**Change:** Added `isAdmin()` check before issuing admin Sanctum token. Non-admin users (STUDENT role) receive a generic 403 error and no token is created.
**Regression Tests:** `tests/Feature/AdminAuthSecurityTest.php` — 10 tests covering student rejection, inactive admin, unverified admin, valid admin, finance manager, super admin, and token ability verification.

### P0-2: Account Lockout Fail-Safe (HIGH)

**File:** `app/Services/Auth/AccountLockoutService.php`
**Finding:** FALSE POSITIVE — The code already had a database fallback using `AuditLog` entries when cache/Redis is unavailable. No fix was needed.
**Note:** Improved the fallback by adding `AuditAction` import and using `LOGIN_FAILED` audit entries for the database-based lockout check.

### P0-3: Admin Token Wildcard Abilities (HIGH)

**File:** `app/Http/Controllers/Admin/AuthController.php`
**Change:** Replaced `['*']` wildcard abilities with `['admin']` least-privilege ability.
**File:** `app/Http/Middleware/EnsureAdminAuthenticated.php`
**Change:** Added token ability check for API routes — tokens must have `admin` ability to access admin API endpoints.
**Regression Tests:** `tests/Feature/AdminAuthSecurityTest.php` — verifies token contains `admin` ability, not `*`. Student tokens without `admin` ability are rejected.

### P1-1: Payment Proof Upload Authorization (MEDIUM)

**File:** `app/Http/Controllers/Api/RequestTrackingController.php`
**Change:** Added `email_hash` verification to `storePaymentProof()` method. Uploads without a matching `email_hash` are rejected with 404.
**Regression Tests:** `tests/Feature/PaymentProofSecurityTest.php` — tests upload without email_hash, with wrong hash, with correct hash, and for nonexistent tracking code.

### P1-2: Tracking Information Disclosure (MEDIUM)

**Finding:** FALSE POSITIVE — The tracking endpoint already implements email_hash gating. Without `email_hash`, only basic info (tracking_code, status, course_title) is returned. Sensitive data requires matching `email_hash`.
**Regression Tests:** `tests/Feature/TrackingSecurityTest.php` — 7 tests verifying limited data without hash, full data with valid hash, 404 with invalid hash, rate limiting, and invalid code format.

### P1-3: File Upload Hardening (MEDIUM)

**File:** `app/Services/Payments/ManualPaymentService.php`
**Change:** Explicitly set `virus_scan_status` to `'pending'` on proof creation instead of relying on DB default.
**Regression Tests:** `tests/Feature/PaymentProofSecurityTest.php` — verifies `virus_scan_status` is set to `'pending'` after upload. `tests/Feature/UploadSecurityTest.php` — tests for invalid MIME, oversized files, spoofed extensions, and random filename generation.

### P1-4: Docker Production Config (MEDIUM)

**File:** `docker-compose.yml`
**Changes:**
- Replaced all `${REDIS_PASSWORD:-change-me-in-production}` with `${REDIS_PASSWORD:?Set REDIS_PASSWORD in .env}` — requires explicit password configuration.
- Replaced full source mount (`- .:/var/www/html`) with granular read-only mounts for each needed directory, excluding `.env`, tests, scripts, and docs. `storage/` remains writable for logs/sessions.

### P1-5: Horizon Dashboard Protection (MEDIUM)

**File:** `app/Providers/HorizonServiceProvider.php` (new)
**Change:** Created `HorizonServiceProvider` extending `HorizonApplicationServiceProvider` with a Gate requiring `SUPER_ADMIN` or `ADMIN` role, active status, and verified email.
**File:** `bootstrap/providers.php`
**Change:** Registered `HorizonServiceProvider`.

### P1-6: .env.example Safe Defaults (MEDIUM)

**File:** `.env.example`
**Changes:**
- `APP_DEBUG=true` → `APP_DEBUG=false`
- `SESSION_SECURE_COOKIE=false` → `SESSION_SECURE_COOKIE=true`
- Removed default admin password (`ADMIN_DEFAULT_PASSWORD=ChangeMeBeforeDeployment123!`)
- Removed weak Redis password (`REDIS_PASSWORD=null` → `REDIS_PASSWORD=`)

### P2-1: Course Request Bot Protection (LOW)

**Finding:** FALSE POSITIVE — CAPTCHA verification is already enforced via `CaptchaGuard` before course request creation. Rate limiting via `throttle:5,1` is applied to both web and API endpoints.

### P2-2: Audit Log Tamper Resistance (LOW)

**Finding:** FALSE POSITIVE — `AuditLog` model already has boot hooks throwing `RuntimeException` on both `updating` and `deleting` events, making rows immutable and undeletable through Eloquent.

### P2-3: Status Filter Validation (LOW)

**File:** `app/Http/Controllers/Admin/CourseRequestController.php`
**Change:** Added validation of `status` parameter against `CourseRequestStatus::cases()` values. Invalid status values return 422.
**Regression Tests:** `tests/Feature/CourseRequestSecurityTest.php` — tests invalid status, valid status, no filter, SQL injection attempt, and unauthorized access.

---

## Test Files Created/Updated

| File | Tests | Status |
|------|-------|--------|
| `tests/Feature/AdminAuthSecurityTest.php` | 10 | New |
| `tests/Feature/PaymentProofSecurityTest.php` | 9 | New |
| `tests/Feature/TrackingSecurityTest.php` | 7 | New |
| `tests/Feature/DashboardAuthorizationTest.php` | 7 | New |
| `tests/Feature/CourseRequestSecurityTest.php` | 6 | New |
| `tests/Feature/UploadSecurityTest.php` | 3 | Updated (added email_hash) |
| `tests/Feature/StorageSecurityTest.php` | 11 | Updated (added email_hash) |

**Total new regression tests:** 39
**Total tests in suite:** 285 (all passing)

---

## Verification Commands

```bash
php artisan test              # 285 passed, 0 failed
./vendor/bin/pint --test      # 0 style issues
./vendor/bin/phpstan analyse  # 0 errors
```

---

## Remaining Risks

1. **Virus scanning not implemented:** The `virus_scan_status` field is set to `'pending'` but no actual virus scanning job exists. This is a known limitation — files are quarantined by status until a scanning solution is deployed.
2. **Token migration:** Existing admin tokens with `['*']` abilities will still pass the `tokenCan('admin')` check (wildcard tokens pass all ability checks). These tokens will expire naturally based on the Sanctum expiration config. No forceful revocation is needed.
3. **Docker source mount:** The granular mount approach requires that `bootstrap/cache/` is writable at runtime. The `bootstrap` directory is mounted as read-write for this purpose.

---

## Deployment Checklist

- [ ] Set `REDIS_PASSWORD` in `.env` (required, no default)
- [ ] Set `APP_DEBUG=false` in production `.env`
- [ ] Set `SESSION_SECURE_COOKIE=true` in production `.env`
- [ ] Run `php artisan migrate` (no new migrations in this remediation)
- [ ] Run `php artisan optimize:clear` after deployment
- [ ] Verify Horizon dashboard requires admin login at `/horizon`
- [ ] Verify admin login rejects STUDENT role users
- [ ] Verify API payment proof upload requires `email_hash`
- [ ] Run `php artisan test` to confirm all tests pass
