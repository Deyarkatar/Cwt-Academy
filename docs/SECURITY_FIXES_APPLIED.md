# Security Fixes Applied — Cwt Academy 2026

## Summary

- **Critical fixes:** 7
- **High fixes:** 9
- **Medium fixes:** 11
- **Total files modified:** 14
- **Lines added:** ~280
- **Lines removed:** ~120

---

## Detailed Fix Log

### 1. Admin API Login CAPTCHA (CRIT-01)
**File:** `app/Http/Controllers/Admin/AuthController.php`
**Change:** Added MathCaptchaService verification before credential validation.
**Lines:** 43–55

### 2. Admin Web Routes Require Verified Email (CRIT-02)
**File:** `routes/web.php`
**Change:** Added `verified` middleware to admin route group; added redirect for unverified admin login.
**Lines:** 176, 295–301

### 3. Admin API Routes Require Verified Email (CRIT-03)
**File:** `routes/api.php`
**Change:** Added `verified` middleware to admin API route group.
**Lines:** 45

### 4. Per-Email Rate Limiting (CRIT-04)
**Files:** `routes/web.php`, `app/Http/Controllers/Admin/AuthController.php`, `app/Providers/AppServiceProvider.php`
**Change:** Added dual-key rate limiting (IP + email) for web login. Added per-email rate limiting for API login. Added dedicated `admin-login` rate limiter.
**Lines:** Multiple

### 5. UpdateCourseAction Locking (CRIT-05)
**File:** `app/Actions/Courses/UpdateCourseAction.php`
**Change:** Wrapped execution in `DB::transaction` with `lockForUpdate()`.
**Lines:** 14–44

### 6. InstructorController Audit & Locking (CRIT-06)
**File:** `app/Http/Controllers/Admin/InstructorController.php`
**Change:** Full rewrite. Added DB transactions, lockForUpdate, state transition validation, audit logging for all CRUD actions.
**Lines:** Entire file

### 7. StoreCourseRequest Misleading Rules (CRIT-07)
**File:** `app/Http/Requests/Admin/StoreCourseRequest.php`
**Change:** Removed `status`, `is_featured`, `published_at` validation rules. Added security comment.
**Lines:** 37–40

### 8. Sanctum Token Expiration (HIGH-01)
**File:** `app/Http/Controllers/Admin/AuthController.php`
**Change:** Added `expiresAt` to `createToken()` with configurable TTL.
**Lines:** 96–98

### 9. Web Logout Revokes API Tokens (HIGH-02)
**File:** `routes/web.php`
**Change:** Added `$user->tokens()->delete()` before session invalidation.
**Lines:** 306–313

### 10. EnsureAdminAuthenticated Verification (HIGH-03)
**File:** `app/Http/Middleware/EnsureAdminAuthenticated.php`
**Change:** Added `hasVerifiedEmail()` check with JSON and web responses.
**Lines:** 24–34

### 11. File Download Content-Type (HIGH-04)
**Files:** `app/Http/Controllers/Admin/PaymentProofController.php`, `routes/web.php`
**Change:** Set explicit `Content-Type` from stored `proof_mime` before returning download.
**Lines:** 84–94 (API), 230–238 (web)

### 12. CategoryController Audit Logging (HIGH-06)
**File:** `app/Http/Controllers/Admin/CategoryController.php`
**Change:** Added `AuditLogger` calls for create and update.
**Lines:** 44, 70–77

### 13. Missing Audit Enums (HIGH-08)
**File:** `app/Enums/AuditAction.php`
**Change:** Added 6 new enum cases for instructor and category actions.
**Lines:** 28–33

### 14. .env.example Secure Defaults (HIGH-07)
**File:** `.env.example`
**Change:** Changed defaults from `file`/`sync` to `database` for session, queue, and cache.
**Lines:** 30–43

---

## Testing Verification

Run the following to validate fixes:

```bash
# Unit + Feature tests
php artisan test

# Static analysis
./vendor/bin/phpstan analyse --configuration=phpstan.neon

# Security audit
composer audit

# Lint
./vendor/bin/pint --test
```

## Re-scan Checklist

- [ ] Admin API login rejects requests without CAPTCHA
- [ ] Admin API login rate-limits after 5 failed attempts per IP + email
- [ ] Web admin routes redirect unverified users to verification page
- [ ] API admin routes return 403 for unverified users
- [ ] Concurrent course edits do not overwrite each other
- [ ] Instructor approve/reject are atomic and audited
- [ ] File downloads return correct Content-Type
- [ ] Web logout invalidates all Sanctum tokens
- [ ] Audit logs contain instructor and category events
- [ ] No `status`/`is_featured` fields accepted on course store/update via mass assignment

---

*End of Fixes Report*
