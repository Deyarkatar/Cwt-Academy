# Cwt Academy — Full-Stack Security Audit Report (2026)

**Date:** 2026-05-24
**Scope:** Backend (Laravel 13 / PHP 8.3), Frontend (React/TS + Blade), Infrastructure
**Auditor:** Cascade AI Security Review

---

## 1. Executive Summary

| Category | Status | Notes |
|----------|--------|-------|
| Authentication | **Good** | Rate limiting, account lockout, password policy, audit logging |
| Authorization | **Good** | RBAC with explicit role checks, Gate policies used |
| File Uploads | **Good** | Magic-byte validation, mime whitelist, size limits, UUID filenames, `safePath()` |
| SQL Injection | **Good** | No raw SQL found; Eloquent + parameterized queries throughout |
| XSS | **Good** | No `{!!` unescaped output found; all user data rendered with `{{ }}` |
| CSRF | **Good** | All POST forms use `@csrf` |
| API Security | **Good** | Sanctum tokens, admin middleware, throttle limits |
| Session/Cookie | **Needs Fix** | `SESSION_SECURE_COOKIE` and `SESSION_ENCRYPT` defaults are **false** |
| Security Headers | **Needs Fix** | No CSP, no HSTS middleware, no X-Frame-Options middleware |
| Business Logic | **Minor Issues** | A few edge cases noted below |
| Dependencies | **Monitor** | Laravel 13 is current; no known CVEs at time of audit |

---

## 2. Critical Issues (Fix Before Production)

### 🔴 CR-1: Session Cookies Not Encrypted / Not Secure by Default

**File:** `config/session.php`
```php
'secure' => env('SESSION_SECURE_COOKIE'),       // default = null/false
'encrypt' => env('SESSION_ENCRYPT', false),     // default = false
```

**Risk:** In production, session cookies may be transmitted over HTTP and stored in plaintext.

**Fix:** In `.env`:
```
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_SAME_SITE=strict
```

The `AppServiceProvider` already warns about this in `validateProductionEnvironment()`, but it only logs — it does not block startup.

---

### 🔴 CR-2: Missing Security Headers (CSP, HSTS, X-Frame-Options, X-Content-Type-Options)

**Risk:** No Content-Security-Policy, no HSTS, no clickjacking protection, no MIME-sniffing protection.

**Fix:** Create a middleware or add to `AppServiceProvider::boot()`:
```php
\Illuminate\Support\Facades\Response::macro('securityHeaders', function ($response) {
    $response->headers->set('X-Frame-Options', 'DENY');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
    if (app()->environment('production')) {
        $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
    }
    return $response;
});
```

For CSP (recommended but complex for existing Spline 3D integration):
```
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' challenges.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none';
```

---

### 🔴 CR-3: Web Admin Routes Missing Authorization on Download Route

**File:** `routes/web.php:212-226`
```php
Route::get('/payment-proofs/{id}/download', function (int $id) {
    $proof = PaymentProof::findOrFail($id);
    // ... download file
});
```

**Risk:** This closure route is inside the `['auth', 'admin']` middleware group, but there is **no `$this->authorize()` or Policy check**. Any authenticated admin can download any proof.

**Fix:** Add policy authorization:
```php
$this->authorize('download', $proof);
```

(The API version in `Admin/PaymentProofController.php` already does this correctly.)

---

## 3. High Severity Issues

### 🟠 HI-1: Admin API PaymentProof `approve` and `reject` — Race Condition + State Machine Gap

**File:** `app/Http/Controllers/Admin/PaymentProofController.php:74-182`

The `approve()` and `reject()` methods lock the `CourseRequest` but **do NOT lock the `PaymentProof`** itself. Two concurrent admin requests could both try to approve the same proof.

**Fix:** Lock the proof row too:
```php
$proof = PaymentProof::query()->whereKey($id)->lockForUpdate()->firstOrFail();
```

Also, `approve()` does not verify that the proof amount matches the course price (the `ApproveCourseRequestAction` does this correctly). The standalone proof-approve endpoint should either:
- Delegate to `ApproveCourseRequestAction`, or
- Add its own amount-mismatch guard.

---

### 🟠 HI-2: CAPTCHA Fail-Open on Network Error

**File:** `app/Services/Captcha/TurnstileService.php:56-60`

```php
catch (ConnectionException $e) {
    // Fail-open on network error...
    return ['success' => true, ...];
}
```

**Risk:** If Cloudflare is unreachable, CAPTCHA is bypassed. This is documented as an intentional trade-off, but combined with rate-limiting it may still allow brute-force during an outage.

**Recommendation:** Consider fail-closed for login/register in production, with a fallback to the math CAPTCHA when Turnstile is unreachable.

---

### 🟠 HI-3: Math CAPTCHA Session Fixation / Reuse Risk

**File:** `app/Services/Captcha/MathCaptchaService.php`

- The math CAPTCHA answer is stored in the session with a 30-minute expiry.
- There is no **one-time-use** token consumption: the same CAPTCHA answer can be replayed across multiple requests within the window.
- No CSRF token is bound to the CAPTCHA challenge.

**Fix:** After successful CAPTCHA verification, immediately delete the session key:
```php
session()->forget('captcha.math_answer');
```

---

### 🟠 HI-4: Tracking Code Enumeration Possible

**File:** `routes/web.php:61-116`

```php
$code = request('code');
if (! preg_match('/^[A-Z0-9]{16}$/', (string) $code)) {
    return view('public.tracking', ['code' => $code, 'requestData' => null]);
}
```

**Risk:** 16-character alphanumeric codes have ~8×10²⁸ combinations, so brute-force is infeasible. However, there is **no rate limiting on the tracking page itself** (the `/track` route has `throttle:10,1`, which is good).

**Status:** Acceptable given the entropy, but consider adding a small delay or CAPTCHA after 5 failed tracking attempts.

---

## 4. Medium Severity Issues

### 🟡 ME-1: `UserSeeder` Prints Password to STDOUT in Production

**File:** `database/seeders/UserSeeder.php:103-113`

```php
if (app()->environment('production')) {
    $this->command->getOutput()->writeln(sprintf(
        '    password: <fg=yellow;options=bold>%s</>',
        $password
    ));
}
```

**Risk:** If deployment logs are captured by centralized logging (Datadog, ELK, CloudWatch), the plaintext admin password is persisted in log storage.

**Fix:** Do not print the password. Instead, email it or write it to a one-time file with `0600` permissions.

---

### 🟡 ME-2: Admin Web Actions Return Generic Error Messages

**File:** `app/Http/Controllers/Admin/Web/TelegramAccessActionController.php`

```php
catch (\Throwable $e) {
    report($e);
    return redirect()->back()->with('error', __('errors.generic'));
}
```

This pattern is fine, but in production with `APP_DEBUG=false`, the `report($e)` may still leak stack traces to the log file. Ensure log files are **not** web-accessible (they are in `storage/logs` by default, which is good).

---

### 🟡 ME-3: No Email Verification Required

**File:** `routes/web.php:300-335`

Registration completes immediately with `Auth::login($user)` without email verification. This allows:
- Typos in email that prevent course delivery
- Account takeovers if someone registers with another person's email before they do

**Fix:** Implement `MustVerifyEmail` or add an email-verification gate before course access.

---

### 🟡 ME-4: Password Reset Flow Missing

There is a link to `/forgot-password` in the login form, but **no routes or controllers** implement it. This is a dead link.

**Fix:** Either remove the link or implement the full Laravel password-reset flow (`php artisan make:auth` resources).

---

## 5. Low Severity / Best Practice Issues

### 🟢 LO-1: Frontend No `rel="noopener noreferrer"` on External Links

The navigation language-switcher links and `telegram_url` may open external domains. Ensure all `<a>` tags to external sites have:
```html
<a href="..." target="_blank" rel="noopener noreferrer">...</a>
```

### 🟢 LO-2: No Subresource Integrity (SRI) on External Scripts

If Turnstile or Spline scripts are loaded from CDN, add `integrity` attributes.

### 🟢 LO-3: `X-Powered-By` and Server Banner Leakage

Ensure `expose_php = Off` in `php.ini` and the web server does not send `X-Powered-By: PHP/8.3`.

### 🟢 LO-4: `RunTests` / Debug Routes Not Disabled

Check that `/test`, `/debug`, `/phpinfo`, `/server-info` are blocked at the web-server level.

---

## 6. Positive Security Controls (Keep These)

| Control | Location | Status |
|---------|----------|--------|
| Role/status NOT fillable on `User` | `User.php` | ✅ |
| Password hashed with bcrypt | `User.php` casts | ✅ |
| Rate limiting on auth | `AppServiceProvider` | ✅ |
| Audit logging on all state changes | `AuditLogger` | ✅ |
| Row-level locking (`lockForUpdate`) | `ManualPaymentService`, Actions | ✅ |
| Magic-byte file validation | `ManualPaymentService` | ✅ |
| UUID filenames (no guessable paths) | `ManualPaymentService` | ✅ |
| `safePath()` path-traversal guard | `UrlHelper` | ✅ |
| `safeRedirect()` open-redirect guard | `UrlHelper` | ✅ |
| `safeHref()` XSS link guard | `UrlHelper` | ✅ |
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
| `html, body { overflow-x: hidden }` prevents scroll leak | `app.css` | ✅ |

---

## 7. Logical / Business Mistakes

### LOG-1: Dashboard Route Returns Empty Data for Admins

**File:** `routes/web.php:122-135`

```php
if ($user->isAdmin()) {
    return view('student.dashboard', [
        'requests' => collect(),
        'approvedRequests' => collect(),
        ...all empty...
    ]);
}
```

**Issue:** Admins hitting `/dashboard` see the student dashboard with empty data. This is confusing. Redirect admins to `/admin` instead.

**Fix:**
```php
if ($user->isAdmin()) {
    return redirect('/admin');
}
```

---

### LOG-2: `RejectCourseRequestAction` Sanitizes Public Note but Not Private Reason

```php
$lockedRequest->update([
    'rejection_reason' => $reason,          // stored raw
    'public_rejection_note' => $this->sanitizePublicNote($reason),
]);
```

The private `rejection_reason` is stored verbatim. If an admin accidentally pastes PII into the reason field, it is stored unfiltered. This is not a security bug, but a data-hygiene issue.

**Fix:** Optionally strip tags from `rejection_reason` too, or at least log a warning.

---

### LOG-3: Course Request `user_id` Nullable but No Guest Flow Clarified

`CourseRequest` allows `user_id = null` (guest checkout), but the tracking page and success page do not clearly differentiate guest vs. authenticated flows. Ensure guest data privacy (e.g., do not expose email in tracking page unless authenticated).

---

## 8. Pre-Production Checklist

- [ ] `.env`: `APP_DEBUG=false`
- [ ] `.env`: `APP_ENV=production`
- [ ] `.env`: `SESSION_SECURE_COOKIE=true`
- [ ] `.env`: `SESSION_ENCRYPT=true`
- [ ] `.env`: `SESSION_SAME_SITE=strict`
- [ ] `.env`: `FORCE_HTTPS=true`
- [ ] `.env`: `ADMIN_DEFAULT_PASSWORD=` (unset after seeding)
- [ ] Web server: Redirect HTTP → HTTPS
- [ ] Web server: Block access to `/.env`, `/storage/logs`, `/vendor`, `/.git`
- [ ] PHP: `expose_php = Off`
- [ ] Add security-headers middleware (CSP, HSTS, X-Frame-Options, etc.)
- [ ] Fix dead `/forgot-password` link
- [ ] Add `lockForUpdate()` to `PaymentProofController::approve/reject`
- [ ] Add `$this->authorize('download', $proof)` to web download closure
- [ ] Delete math CAPTCHA session key after successful verification
- [ ] Consider implementing email verification
- [ ] Run `composer audit` before deploy
- [ ] Run `npm audit` before deploy
- [ ] Set up automated log monitoring for `Production preflight: configuration issues detected`

---

*Report generated by Cascade AI — 2026-05-24*
