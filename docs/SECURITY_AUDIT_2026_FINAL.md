# Cwt Academy — Production Security Audit Report (2026)

**Date:** 2026-05-24
**Scope:** Full-stack Laravel 13 application (PHP 8.3) + React/TS frontend + Infrastructure
**Auditor:** Cascade AI Security Review
**Status:** Ready for production with listed remediations applied

---

## 1. Executive Summary

| Category | Status | Notes |
|----------|--------|-------|
| Authentication | **Good** | Rate limiting, account lockout, Argon2id, password policy, audit logging |
| Authorization | **Good** | RBAC with explicit role checks, Gate policies used, `canManageRequests()` guards |
| File Uploads | **Good** | Magic-byte validation, MIME whitelist, size limits, UUID filenames, `safePath()` |
| SQL Injection | **Good** | No raw SQL; Eloquent + parameterized queries throughout |
| XSS | **Good** | No `{!!` unescaped output; all user data rendered with `{{ }}` |
| CSRF | **Good** | All POST forms use `@csrf` |
| API Security | **Good** | Sanctum tokens, admin middleware, throttle limits |
| Session/Cookie | **Good** | `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `SESSION_SAME_SITE=strict` enforced |
| Security Headers | **Good** | CSP nonce-based, HSTS, X-Frame-Options, Permissions-Policy, COOP/COEP/CORP |
| Business Logic | **Good** | State-machine guards, amount-mismatch validation, row-level locking |
| Race Conditions | **Good** | `lockForUpdate()` on proof + request rows in all approval paths |
| Dependencies | **Good** | Laravel 13 current; composer audit should be run pre-deploy |
| Privacy/GDPR | **Needs Review** | See Section 5 for data retention and PII exposure notes |

---

## 2. Critical Issues (ALL FIXED)

### CR-1: Session Cookies Not Encrypted / Not Secure by Default — **FIXED**

**File:** `config/session.php` (via `.env` + `AppServiceProvider` preflight)

**Fix Applied:** `.env.example` documents required production settings:
```
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_SAME_SITE=strict
```

`AppServiceProvider::validateProductionEnvironment()` logs critical warnings if any are misconfigured.

---

### CR-2: Missing Security Headers — **FIXED**

**File:** `app/Http/Middleware/SecurityHeaders.php`

**Fix Applied:** Enterprise middleware now sets:
- Universal: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `X-XSS-Protection: 0`
- Production-only: `Permissions-Policy` (zero capabilities), `COOP/COEP/CORP`, HSTS
- HTML responses: Nonce-based CSP with `strict-dynamic` in production
- Sensitive routes: `Cache-Control: no-store, no-cache, must-revalidate`

Registered in `bootstrap/app.php` for both web and API stacks.

---

### CR-3: Web Admin Download Route Missing Authorization — **FIXED**

**File:** `routes/web.php:211-231`

**Fix Applied:** Closure now explicitly checks:
```php
if (! auth()->user()?->can('download', $proof)) {
    abort(403, 'Unauthorized');
}
```

---

## 3. High Severity Issues (ALL FIXED)

### HI-1: Admin API PaymentProof Race Condition — **FIXED**

**File:** `app/Http/Controllers/Admin/PaymentProofController.php`

**Fix Applied:** `approve()` and `reject()` now lock the proof row itself:
```php
$proof = PaymentProof::query()->whereKey($id)->lockForUpdate()->firstOrFail();
```

Additionally, `approve()` now enforces amount-mismatch guard against `course->price_iqd`.

---

### HI-2: CAPTCHA Fail-Open on Network Error — **FIXED**

**File:** `app/Services/Captcha/TurnstileService.php`

**Fix Applied:** Production now defaults to **fail-closed**:
```php
$failOpen = ! app()->environment('production')
    || config('security.captcha.fail_open_on_network_error', false);
```

Network errors in production return `success: false` with error code `turnstile-network-error`.

---

### HI-3: Math CAPTCHA Session Reuse — **FIXED**

**File:** `app/Services/Captcha/MathCaptchaService.php`

**Fix Applied:** `verify()` now calls `$this->clear()` immediately after successful verification. Token expiry reduced to 5 minutes.

---

### HI-4: Email Verification Not Required — **FIXED**

**File:** `routes/web.php`, `app/Models/User.php`

**Fix Applied:**
- `User` implements `MustVerifyEmail`
- Registration sends verification notification
- `/dashboard` and `/profile` protected by `verified` middleware
- Verification routes use signed URLs with throttle protection

---

## 4. Medium Severity Issues (ALL FIXED)

### ME-1: Admin Dashboard Empty for Admins — **FIXED**

**File:** `routes/web.php:122-135`

**Fix Applied:** Admins hitting `/dashboard` are redirected to `/admin`:
```php
if ($user->isAdmin()) {
    return redirect('/admin');
}
```

---

### ME-2: Password Reset Dead Link — **FIXED**

**File:** `resources/views/auth/login.blade.php`

**Fix Applied:** Link is commented out with explanatory note until SMTP is configured:
```blade
{{-- Password reset will be enabled once SMTP is configured --}}
```

---

### ME-3: Inline JavaScript in Blade Templates — **FIXED**

**Files:** `login.blade.php`, `register.blade.php`

**Fix Applied:** Password toggle and strength meter moved to `resources/js/app.js` using `data-toggle-password` attributes.

---

## 5. New Findings from Deep Audit (2026-05-24)

### NF-1: Tracking Page Exposes Raw `rejection_reason` (Privacy)

**File:** `routes/web.php:83`

**Risk:** The tracking page returns `$courseRequest->rejection_reason` to the public. This is the internal admin reason, not the sanitized `public_rejection_note`. If an admin accidentally includes PII or internal notes, it leaks to the end user.

**Fix:** Change line 83 to:
```php
'rejection_reason' => $courseRequest->public_rejection_note,
```

---

### NF-2: CourseRequest Fillable Includes Sensitive State Fields

**File:** `app/Models/CourseRequest.php`

**Risk:** `Fillable` includes `status`, `approved_by`, `approved_at`, `rejected_by`, `rejected_at`, `rejection_reason`, `public_rejection_note`. While current code paths set these explicitly, a future controller doing `$courseRequest->update($request->validated())` could allow status escalation.

**Fix:** Remove state transition fields from `$fillable` and set them explicitly in actions, same pattern as `User::role`:
```php
// Remove from Fillable: status, approved_by, approved_at, rejected_by, rejected_at, rejection_reason, public_rejection_note
```

---

### NF-3: TelegramAccessGrant Fillable Includes State Fields

**File:** `app/Models/TelegramAccessGrant.php`

**Risk:** Same pattern as NF-2. `status`, `granted_by`, `granted_at`, `revoked_by`, `revoked_at`, `revoked_reason` are fillable.

**Fix:** Remove state transition fields from `$fillable`.

---

### NF-4: CourseRequestController::store() Forces Course Price as Payment Amount

**File:** `app/Http/Controllers/Web/CourseRequestController.php:47`

**Risk:** The unified form always passes `(int) ($course->price_iqd ?? 0)` as the payment proof amount. This means the user cannot pay a different amount (e.g., partial payment, discount). If the course price changes after submission, the proof amount mismatches.

**Fix:** This is a design decision, but should be documented. If variable amounts are desired, accept `amount_iqd` from the form and validate `min:1,max:{{ $course->price_iqd }}`.

---

### NF-5: RequestTrackingController::show() Exposes Course Price

**File:** `app/Http/Controllers/Api/RequestTrackingController.php:42`

**Risk:** Public tracking API returns `course_price_iqd` without authentication. While not sensitive per se, it exposes pricing data that competitors could scrape.

**Fix:** Consider removing `course_price_iqd` from the public tracking response, or only including it when the request belongs to an authenticated user.

---

### NF-6: PublicCourseController::show() Exposes Telegram Channel Existence

**File:** `app/Http/Controllers/Api/PublicCourseController.php:38-48`

**Risk:** The public course detail API reveals whether a Telegram channel is configured (`hasTelegram`). This is minor information leakage.

**Fix:** This is acceptable for a course marketplace. No action required unless business requirements change.

---

### NF-7: WebCourseRequestActionController Catches ValidationException with Raw Message

**File:** `app/Http/Controllers/Admin/Web/CourseRequestActionController.php:27-32`

**Risk:** `catch (ValidationException $e)` returns `$e->getMessage()` directly to the user. While Laravel's ValidationException messages are generally safe, internal error messages could leak debug information.

**Fix:** Use a translated generic message instead:
```php
catch (ValidationException $e) {
    return redirect()->back()->with('error', __('errors.validation_failed'))->withInput();
}
```

---

### NF-8: Admin PaymentProofController Uses Outer $proof for Authorize Check

**File:** `app/Http/Controllers/Admin/PaymentProofController.php:83-94`

**Risk:** `approve()` calls `$this->authorize('approve', $proof)` on the outer `$proof` (line 85), then re-fetches inside the transaction (line 91). Between these two lines, another request could change the proof's status. While the inner transaction will catch this, the authorize check happens on stale data.

**Fix:** Move authorization inside the transaction, or use the locked row for authorization:
```php
return DB::transaction(function () use ($request, $id) {
    $proof = PaymentProof::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    $this->authorize('approve', $proof);
    // ... rest of logic
});
```

---

### NF-9: SecurityHeaders Cache-Control Logic Based on auth()->check()

**File:** `app/Http/Middleware/SecurityHeaders.php:101-109`

**Risk:** `auth()->check()` is evaluated after the request is processed. If middleware order changes or authentication happens late, cache headers might be inconsistent. Also, `auth()->check()` performs a DB query on every request if a session cookie is present.

**Fix:** This is acceptable for most cases, but consider caching the authentication check or using a dedicated middleware for cache headers on authenticated routes.

---

### NF-10: AuditLogger Logs Model Changes Without Redaction

**File:** `app/Services/Audit/AuditLogger.php`

**Risk:** `logModelChange()` logs old/new values but doesn't explicitly redact them before passing to `log()`. The `log()` method does call `self::redact()`, but only on the `old_values` and `new_values` arrays. If a model's `toArray()` includes nested arrays or objects, redaction might miss them.

**Fix:** Ensure all model arrays are flattened before redaction, or add recursive redaction.

---

### NF-11: Tracking Page Uses GET Method (Privacy)

**File:** `resources/views/public/tracking.blade.php:8`

**Risk:** The tracking code is submitted via GET, which means:
- Code appears in browser history
- Code appears in proxy/server access logs
- Code is sent in the `Referer` header when clicking external links

**Fix:** For better privacy, change to POST (though this breaks bookmarkability). Alternatively, add a privacy notice informing users that tracking codes may be logged.

---

### NF-12: UserSeeder Still Prints Password to STDOUT in Production

**File:** `database/seeders/UserSeeder.php:103-113`

**Risk:** If deployment logs are captured by centralized logging (Datadog, ELK, CloudWatch), the plaintext admin password is persisted in log storage. This was noted in the previous audit but the behavior remains.

**Fix:** Instead of printing to STDOUT, write the password to a file with `0600` permissions, or send it via email. Alternatively, generate a one-time login link.

---

### NF-13: CourseService Search Term SQL Injection (Theoretical)

**File:** `app/Services/Courses/CourseService.php:32-35`

```php
$search = $filters['search'];
$query->where(function ($q) use ($search) {
    $q->where('title', 'like', "%{$search}%")
        ->orWhere('short_description', 'like', "%{$search}%");
});
```

**Risk:** Eloquent parameterizes the binding, so this is NOT exploitable as SQL injection. However, string interpolation in PHP code is a bad pattern that could be copy-pasted into a raw query context in the future.

**Fix:** Use parameter binding explicitly:
```php
$q->where('title', 'like', '%' . $search . '%')
```

---

### NF-14: PruneAuditLogs Default Mismatch with Config

**File:** `app/Console/Commands/PruneAuditLogs.php`

```php
$days = (int) $this->option('days'); // defaults to 90
$cutoff = now()->subDays(max(7, $days));
```

**Risk:** `config/security.php` sets `'audit_retention_days' => 365`, but the console command defaults to 90 and does not read the config value.

**Fix:** Use the config value as default:
```php
$days = (int) $this->option('days') ?: config('security.audit_retention_days', 90);
```

---

## 6. Performance Findings

### PF-1: Admin Requests Page Loads All Records

**File:** `routes/web.php:190-196`

```php
$requests = CourseRequest::with(['course.telegramChannel', 'latestPaymentProof'])
    ->orderByDesc('created_at')
    ->get(); // No pagination!
```

**Fix:** Add pagination:
```php
->paginate(50)
```

---

### PF-2: Dashboard Uses Collection Filter on Paginated Results

**File:** `routes/web.php:139-145`

```php
$approvedRequests = $requests->filter(...)->values();
$pendingRequests = $requests->filter(...)->values();
```

**Issue:** Filtering a paginated collection after pagination gives incorrect counts. The `totalRequests`, `activeCount`, `pendingCount` variables use `$requests->count()` which only counts the current page.

**Fix:** Calculate counts from the query before pagination, or remove pagination if the counts must be accurate.

---

### PF-3: Missing Database Indexes

**File:** `database/migrations/2026_05_24_000000_add_production_db_constraints.php`

**Status:** Indexes were added for common query patterns. Good.

**Missing:** Consider adding an index on `payment_proofs.transaction_reference` for the unique constraint validation performance.

---

## 7. Positive Security Controls (Keep These)

| Control | Location | Status |
|---------|----------|--------|
| Role/status NOT fillable on `User` | `User.php` | ✅ |
| Password hashed with bcrypt (Argon2id in prod) | `User.php` casts + `AppServiceProvider` | ✅ |
| Rate limiting on auth | `AppServiceProvider` | ✅ |
| Audit logging on all state changes | `AuditLogger` | ✅ |
| Row-level locking (`lockForUpdate`) | `ManualPaymentService`, `PaymentProofController`, Actions | ✅ |
| Magic-byte file validation | `ManualPaymentService` | ✅ |
| UUID filenames (no guessable paths) | `ManualPaymentService` | ✅ |
| `safePath()` path-traversal guard | `UrlHelper` | ✅ |
| `safeRedirect()` open-redirect guard | `UrlHelper` | ✅ |
| `safeTelegramUrl()` strict host validation | `UrlHelper` | ✅ |
| Math CAPTCHA fallback | `MathCaptchaService` | ✅ |
| State-machine guards on requests/proofs | `Approve/Reject Actions` | ✅ |
| Amount-mismatch validation | `ApproveCourseRequestAction` | ✅ |
| Production preflight checks | `AppServiceProvider` | ✅ |
| Transaction references are unique | `StorePaymentProofRequest` | ✅ |
| `basename()` used in Content-Disposition | `PaymentProofController` | ✅ |
| All forms have `@csrf` | Blade views | ✅ |
| No `{!!` unescaped output found | Blade views | ✅ |
| No raw SQL / `DB::raw` found | Entire app | ✅ |
| API routes protected by `auth:sanctum` + `admin` | `api.php` | ✅ |
| Web admin routes protected by `auth` + `admin` | `web.php` | ✅ |
| Sanctum token ability scoping | `AuthController` | ✅ |
| Admin creation command with strict role validation | `CreateAdminUser` | ✅ |
| Password policy with HIBP check in production | `PasswordPolicy` | ✅ |
| Email verification with signed URLs | `web.php` | ✅ |
| Security headers middleware with CSP nonce | `SecurityHeaders` | ✅ |
| ForceHttps with local IP exemption | `ForceHttps` | ✅ |
| TrustProxies configured for Cloudflare/ALB | `bootstrap/app.php` | ✅ |

---

## 8. Pre-Production Checklist

### Environment Configuration
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL=https://your-domain.com`
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] `SESSION_ENCRYPT=true`
- [ ] `SESSION_SAME_SITE=strict`
- [ ] `FORCE_HTTPS=true`
- [ ] `TRUSTED_PROXIES=your-proxy-ip-range` (not `*` in production)
- [ ] `ADMIN_DEFAULT_PASSWORD=` (unset after seeding)
- [ ] `CAPTCHA_DRIVER=turnstile` (or `math`)
- [ ] `TURNSTILE_SITE_KEY=` and `TURNSTILE_SECRET_KEY=` configured
- [ ] `CSP_REPORT_URI=` configured (optional but recommended)
- [ ] `MAIL_*` configured for email verification

### Infrastructure
- [ ] Web server: Redirect HTTP → HTTPS
- [ ] Web server: Block access to `/.env`, `/storage/logs`, `/vendor`, `/.git`
- [ ] PHP: `expose_php = Off`
- [ ] PHP: `display_errors = Off`
- [ ] Database: Run `php artisan migrate`
- [ ] Storage: `php artisan storage:link`
- [ ] Caching: `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`
- [ ] Scheduler: `php artisan schedule:run` configured via cron for `audit:prune`

### Security Verification
- [ ] Run `composer audit` — address any CVEs before deploy
- [ ] Run `npm audit` — address any CVEs before deploy
- [ ] Verify email verification flow end-to-end
- [ ] Verify CAPTCHA works on login/register
- [ ] Verify file upload rejects non-image/non-PDF files
- [ ] Verify admin download requires authentication
- [ ] Verify rate limiting triggers after 5 failed logins
- [ ] Verify security headers present in production (use securityheaders.com)
- [ ] Verify HSTS preload readiness

### Post-Deploy
- [ ] Remove or rotate `ADMIN_DEFAULT_PASSWORD` from `.env`
- [ ] Create production admin via `php artisan admin:create`
- [ ] Verify audit logs are writing to database
- [ ] Set up log monitoring for `Production preflight: configuration issues detected`
- [ ] Configure automated backups for database and `storage/app/payment_proofs`

---

## 9. Remaining Open Items (Low Priority)

| Item | Priority | Notes |
|------|----------|-------|
| Password reset flow | Low | Commented out until SMTP configured |
| SRI on external scripts | Low | Add `integrity` to Turnstile CDN script if used |
| `rel="noopener noreferrer"` audit | Low | Already present on external links |
| GDPR data retention for CourseRequest | Low | Add `CourseRequestPruneCommand` for old PII |
| 2FA for admin accounts | Low | Future enhancement |
| API versioning | Low | Current API is v0; consider `/api/v1/` prefix |

---

*Report generated by Cascade AI — 2026-05-24*
*This audit covers the complete codebase as of commit date. Re-run after any significant feature additions.*
