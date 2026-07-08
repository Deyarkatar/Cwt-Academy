# Cwt Academy — Full-Stack Security & Production Readiness Audit 2026

**Audit scope:** Entire repository (Laravel 13 backend, React 19/Vite frontend, MySQL 8, Redis, Docker/nginx).  
**Methodology:** Line-by-line static review against OWASP WSTG/ASVS 5.0, NIST SP 800-63B, Laravel 13 security conventions, PHP 8.3+ hardening, MySQL 8 best practice, React 19/TypeScript reliability, and Docker/nginx CIS baselines. All findings cite exact file paths and line numbers from the current codebase. Automated verification was executed with PHPUnit, Laravel Pint, PHPStan, and `npm run build`.  
**Report date:** 2026 (generated from current codebase snapshot).  

---

## 1. Executive Summary

Cwt Academy is a Laravel 13 + React 19 course-marketplace application centred on a **manual Telegram channel access workflow**. The codebase demonstrates **mature security awareness** for a project of this size: encrypted PII, database-level uniqueness for encrypted transaction references, immutable audit logs, CSP nonces, Argon2id hashing, strict file upload validation, nginx hardening, and comprehensive feature tests. However, several **high-impact functional and security gaps** remain that block unconditional production approval.

| Area | Score / 100 | Verdict |
| ------ | ------------- | --------- |
| Security & Auth | 76 | Needs remediation |
| Business Logic / Workflow Integrity | 68 | Needs remediation |
| Backend / Laravel Architecture | 78 | Acceptable with fixes |
| Database Design & Integrity | 82 | Largely ready |
| Frontend / UI / UX / Accessibility | 74 | Needs polish |
| Performance & Scalability | 79 | Acceptable with fixes |
| Testing & Quality Assurance | 77 | Tests pass; static analysis fails |
| DevOps / Docker / Deployment | 80 | Largely ready |
| Documentation & Configuration | 72 | Inconsistent defaults |
| **Overall** | **76 / 100** | **Conditional No-Go** |

**Final verdict: CONDITIONAL NO-GO for production.** The application can reach a **GO** state after remediating the items marked **HIGH** in Sections 3–4 and resolving the static-analysis failures. None of the findings require architectural rewrites; most are single-file or single-line fixes.

---

## 2. Automated Verification Results

Executed on the workspace:

```text
./vendor/bin/phpunit --testdox        → 168 tests, 546 assertions, OK
./vendor/bin/pint --test              → FAILURES (style violations, mostly in tests)
./vendor/bin/phpstan analyse          → 11 errors
npm run build                         → successful
```

Key take-aways:

- **Functional correctness is strong** — all 168 feature tests pass.
- **Code-style discipline is inconsistent** — Pint reports failures.
- **Static analysis has regressions** — PHPStan reports 11 errors, including a real null-safety issue in `WebAuthnPasskeyController` and test helper type issues.

---

## 3. Security & Authentication Findings

### 3.1 HIGH — No bot protection on public course-request form

**Evidence:**

```php
@/home/fsociety/Cwt_academy/routes/web.php:46-48
Route::post('/course-requests/store', [CourseRequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('course-requests.store');
```

```php
@/home/fsociety/Cwt_academy/resources/views/public/request-form.blade.php:21-123
```

The unified public submission form only relies on a **per-IP throttle of 5 req/min**. It accepts PII (name, email, phone, city) and a file upload. There is no CAPTCHA, Turnstile, or hidden honeypot field. An attacker can script submissions (upload abuse, spam, PII flooding) from a small pool of IPs.

**Remediation:** Add the `turnstile` or `math` CAPTCHA to the public form and controller, reusing the existing `VerifyTurnstile` / `MathCaptchaService` infrastructure.

### 3.2 HIGH — API payment-proof approve endpoint does not complete the workflow

**Evidence:**

```php
@/home/fsociety/Cwt_academy/app/Http/Controllers/Admin/PaymentProofController.php:100-154
```

`PaymentProofController::approve()` sets the proof status to `APPROVED` but:

1. Never transitions the parent `CourseRequest` out of `PENDING_REVIEW`.
2. Never creates the `TelegramAccessGrant` that is required for the student to receive channel access.

The correct workflow is already implemented in `ApproveCourseRequestAction` (used by `POST /api/admin/course-requests/{id}/approve` and the web admin form). The standalone payment-proof endpoint is therefore a **dead-end action** from a business perspective and a source of support tickets.

**Remediation:** Deprecate or remove `POST /api/admin/payment-proofs/{id}/approve`, or make it a thin wrapper that delegates to `ApproveCourseRequestAction` with the proof’s request.

### 3.3 HIGH — Payment-proof download controller only works with `local` disk

**Evidence:**

```php
@/home/fsociety/Cwt_academy/app/Services/Payments/ManualPaymentService.php:94-95
$disk = config('filesystems.disks.r2.bucket') ? 'r2' : 'local';
$path = $file->storeAs('payment_proofs', $filename, $disk);
```

```php
@/home/fsociety/Cwt_academy/app/Http/Controllers/Web/AdminPaymentProofDownloadController.php:27
$response = Storage::disk('local')->download($path);
```

The upload service chooses `r2` when `R2_BUCKET` is configured, but the web download controller hard-codes `Storage::disk('local')`. In production with R2 enabled, admins will receive **404s for files that exist in object storage**.

**Remediation:** Use the same disk-resolution logic in `AdminPaymentProofDownloadController`, or store the disk name alongside `proof_file_path`.

### 3.4 MEDIUM — Admin API account-lockout pattern is mismatched to route

**Evidence:**

```php
@/home/fsociety/Cwt_academy/app/Http/Middleware/AdminAccountLockoutMiddleware.php:20-22
if (! $request->is('admin/api/login') && ! $request->is('login')) {
    return $next($request);
}
```

The actual admin login route is `/api/admin/login` (`routes/api.php:40`), not `/admin/api/login`. Laravel’s `Request::is()` matches leading path segments, so the pattern `admin/api/login` does **not** match `api/admin/login`. The middleware is therefore silently skipped for the admin API login, removing a key brute-force defence.

**Remediation:** Change the pattern to `api/admin/login`, or, better, guard the route explicitly in `routes/api.php` using the `lockout` middleware alias.

### 3.5 MEDIUM — `ReadReplicaMiddleware` can poison writes on GET requests

**Evidence:**

```php
@/home/fsociety/Cwt_academy/app/Http/Middleware/ReadReplicaMiddleware.php:18-27
if (
    $request->isMethodSafe()
    && ! auth()->check()
    && config('database.connections.mysql_read') !== null
    && config('database.connections.mysql_read.host') !== config('database.connections.mysql.host')
) {
    DB::setDefaultConnection('mysql_read');
}
```

Switching the **default connection** for the entire request means any side-effect that happens later (audit log, cache increment, queued job dispatch inside the same process, etc.) may write to the read replica. The middleware also never restores the connection, so background tasks or subsequent middleware that issue writes will use the replica. This is a data-consistency and availability risk.

**Remediation:** Route only explicit read queries to the replica connection (e.g. via a repository pattern or `DB::connection('mysql_read')->...`), and never change the default connection for the whole request.

### 3.6 MEDIUM — Honey-token guard is computationally expensive and may DoS itself

**Evidence:**

```php
@/home/fsociety/Cwt_academy/app/Http/Middleware/HoneyTokenGuard.php:127-147
$needleHash = hash('sha256', $needle);
$iterations = $haystackLength - $needleLength + 1;
for ($i = 0; $i < $iterations; $i++) {
    $candidate = substr($haystack, $i, $needleLength);
    if (hash_equals($needleHash, hash('sha256', $candidate))) {
        return true;
    }
}
```

For a 1 MB upload body and a 30-byte token, this performs ~1 million SHA-256 operations per token on every request. An attacker can trivially cause CPU exhaustion by sending large bodies that contain substrings matching the token length.

**Remediation:** Use `str_contains()` with `hash_equals` only on exact header/body values, or pre-hash and compare via a prefix tree. Never slide a hash window across the entire request body.

### 3.7 MEDIUM — `BruteForceDetectionMiddleware` blocks by IP only and lacks proxy-aware source IP

**Evidence:**

```php
@/home/fsociety/Cwt_academy/app/Http/Middleware/BruteForceDetectionMiddleware.php:20-27
private const string WINDOW = '300';
private const string THRESHOLD = '10';
private const string BLOCK_THRESHOLD = '20';
private const string BLOCK_DURATION = '3600';
```

```php
@/home/fsociety/Cwt_academy/app/Http/Middleware/BruteForceDetectionMiddleware.php:30
$ip = (string) $request->ip();
```

Counting by `$request->ip()` behind a CDN/load balancer without verified proxy headers means legitimate users behind a NAT or corporate gateway can be blocked because of other users’ failures. The thresholds are also global rather than per-endpoint.

**Remediation:** Use a normalized client identifier (e.g. `X-Forwarded-For` after trusted-proxy validation + endpoint name) and separate thresholds per surface.

### 3.8 MEDIUM — Self-approval not blocked in payment-proof endpoint

**Evidence:**

```php
@/home/fsociety/Cwt_academy/app/Policies/PaymentProofPolicy.php:25-28
public function approve(User $user, PaymentProof $paymentProof): bool
{
    return $user->canApprovePayments();
}
```

The course-request policy checks email mismatch to prevent self-approval (`CourseRequestPolicy.php:20-23`), but the payment-proof policy does not. A finance manager who is also registered as a student could, in theory, approve their own proof via `POST /api/admin/payment-proofs/{id}/approve`.

**Remediation:** Add a conflict-of-interest check comparing the approver email to the request’s decrypted `student_email`.

### 3.9 LOW — `DigitalEntropyMiddleware` leaks its own existence and adds latency

**Evidence:**

```php
@/home/fsociety/Cwt_academy/app/Http/Middleware/DigitalEntropyMiddleware.php:13-22
$randomDelay = random_int(0, 100000);
usleep($randomDelay);
...
if (app()->environment('production')) {
    $response->headers->set('X-Entropy', bin2hex(random_bytes(8)));
}
```

The random delay is too small to mitigate meaningful timing attacks but adds measurable latency. The `X-Entropy` header advertises the presence of a non-standard defence mechanism, which is mild information disclosure.

**Remediation:** Remove the middleware or replace it with request-level timing jitter applied only to sensitive endpoints and do not emit the header.

### 3.10 LOW — Register form promises 8-character password while backend requires 12

**Evidence:**

```php
@/home/fsociety/Cwt_academy/app/Support/Security/PasswordPolicy.php:19
public const MIN_LENGTH = 12;
```

```php
@/home/fsociety/Cwt_academy/resources/views/auth/register.blade.php:41-58
<li id="req-length" ...> 8 chars ...</li>
```

The frontend strength meter labels the length requirement as 8 characters, but `PasswordPolicy::rule()` enforces 12. Users will see passwords marked as “strong” that the backend rejects.

**Remediation:** Update the view to reflect the 12-character minimum and keep frontend/backend requirements in sync.

### 3.11 POSITIVE — Strong security controls observed

- Encrypted casts for PII in `CourseRequest` and `PaymentProof`.
- SHA-256 hash column for duplicate transaction-reference detection on encrypted data.
- Immutable `AuditLog` model prevents tampering.
- File upload validation includes MIME, magic bytes, `getimagesize()`, and PDF tail scanning.
- Argon2id configured in production.
- Strict CSP nonces and modern security headers via `SecurityHeaders` middleware.
- nginx config denies direct `.php` execution and blocks source/config artifacts.
- Docker containers drop capabilities and use `no-new-privileges`.
- Redis command renaming and password protection in Docker.

---

## 4. Business Logic / Workflow Integrity Findings

### 4.1 HIGH — Public unified form hard-codes amount and null reference, losing audit trail

**Evidence:**

```php
@/home/fsociety/Cwt_academy/app/Http/Controllers/Web/CourseRequestController.php:59-67
$amountIqd = is_int($course->price_iqd) ? $course->price_iqd : (int) $course->price_iqd;

$paymentService->storeProof(
    courseRequest: $courseRequest,
    amountIqd: $amountIqd,
    senderName: $this->stringFromValidated($validated, 'student_name'),
    transactionReference: null,
    file: $file,
);
```

The web form always records the exact course price and a `null` transaction reference. This prevents genuine partial/over-payments and removes the ability to correlate a bank transfer with the uploaded receipt. Every unified submission has the same (null) reference, defeating the duplicate-reference check.

**Remediation:** Add optional `amount_iqd` and `transaction_reference` fields to the unified form, validated against the course price, and pass them to `storeProof`.

### 4.2 MEDIUM — Duplicate course requests are not prevented

**Evidence:**

```php
@/home/fsociety/Cwt_academy/app/Actions/CourseRequests/CreateCourseRequestAction.php:13-35
```

The action creates a new `CourseRequest` on every call without checking whether an identical pending request already exists for the same course + email. Students can accidentally (or maliciously) flood the queue.

**Remediation:** Add an idempotency check for an existing pending request from the same student email + course within a short window, or expose an idempotency key.

### 4.3 MEDIUM — Free courses break the unified proof upload

**Evidence:**

```php
@/home/fsociety/Cwt_academy/app/Services/Payments/ManualPaymentService.php:45-49
if ($amountIqd < 1 || $amountIqd > 10_000_000) {
    throw ValidationException::withMessages([...]);
}
```

```php
@/home/fsociety/Cwt_academy/app/Http/Requests/Admin/StoreCourseRequest.php:35
'price_iqd' => ['required', 'integer', 'min:0'],
```

A course priced at `0` IQD passes admin validation, but the unified form then tries to store a proof with amount `0`, which `ManualPaymentService` rejects.

**Remediation:** Either disallow free courses (`min:1`) or skip proof upload when the course price is zero.

### 4.4 LOW — Reject action overwrites public rejection note even when empty

**Evidence:**

```php
@/home/fsociety/Cwt_academy/app/Actions/CourseRequests/RejectCourseRequestAction.php:40
$lockedRequest->public_rejection_note = $this->sanitizePublicNote($reason);
```

A sanitized note is always written. If the admin later wants to add a student-friendly note, there is no dedicated field; the raw admin reason is exposed (after HTML stripping). This conflates internal and public reasoning.

**Remediation:** Add a separate optional `public_rejection_note` input to the reject forms/controllers.

### 4.5 POSITIVE — State-machine protection and locking

- `ApproveCourseRequestAction` and `RejectCourseRequestAction` use `lockForUpdate()`.
- Status transitions are allow-listed.
- Amount mismatch between proof and course price is rejected.
- Duplicate approval does not create duplicate Telegram grants.

---

## 5. Backend / Laravel Architecture Findings

### 5.1 MEDIUM — PHPStan regression in `WebAuthnPasskeyController`

**Evidence:**

```php
@/home/fsociety/Cwt_academy/app/Http/Controllers/WebAuthn/WebAuthnPasskeyController.php:15-16
$credentials = $request->user()
    ->webAuthnCredentials()
```

`$request->user()` is nullable; PHPStan correctly flags it. Although the route is protected by `auth` middleware, the code should be statically safe.

**Remediation:** Use a typed local variable or null-safe assertion.

### 5.2 MEDIUM — Laravel Pint style failures

`./vendor/bin/pint --test` reported failures (single-quote style in tests). Style failures are not security issues but indicate that the CI gate configured in `composer.json` (`composer run format-check`) would fail.

**Remediation:** Run `./vendor/bin/pint` and commit the changes.

### 5.3 LOW — `VerifyTurnstile` middleware alias is unused

**Evidence:**

```php
@/home/fsociety/Cwt_academy/bootstrap/app.php:86-90
$middleware->alias([
    'admin' => EnsureAdminAuthenticated::class,
    'turnstile' => VerifyTurnstile::class,
    'lockout' => AdminAccountLockoutMiddleware::class,
]);
```

The `turnstile` alias is registered but no route uses it; Turnstile verification is inlined in `AuthController@login`. This is dead configuration and a maintenance inconsistency.

**Remediation:** Either apply `turnstile` middleware to relevant routes or remove the alias.

### 5.4 LOW — `AdminAccountLockoutMiddleware` returns 423 for web login attempts

The middleware returns a JSON 423 response even when the request is to the web `/login` route. A non-JSON browser request will receive an API-style JSON body, breaking the user experience.

**Remediation:** Detect `expectsJson()` and return an HTML redirect with an error flash for web requests.

### 5.5 LOW — `EnsureAdminAuthenticated` redirects to `/login` without intended URL

**Evidence:**

```php
@/home/fsociety/Cwt_academy/app/Http/Middleware/EnsureAdminAuthenticated.php:21
return redirect('/login');
```

No `intended()` redirect is stored, so users return to the admin dashboard after login only if the frontend hard-codes it.

**Remediation:** Store the intended URL before redirecting.

### 5.6 POSITIVE — Architecture strengths

- Action/service/repository separation (`CourseService`, `Actions`, `Repositories`).
- Policies for all admin resources.
- Form requests for validation.
- Queue-backed audit logging and verification emails.
- `afterResponse()` dispatch for audit logs.

---

## 6. Database Design & Integrity Findings

### 6.1 MEDIUM — No database-level unique constraint on `transaction_reference_hash` for NULLs

**Evidence:**

```php
@/home/fsociety/Cwt_academy/database/migrations/2026_05_29_000001_add_transaction_reference_hash.php
```

The migration adds `transaction_reference_hash` and indexes it, but the code treats `null` references as acceptable. Depending on the RDBMS, a unique index on a nullable column may allow only one `NULL` value. This has not been observed as a failure path, but the design should be verified against the target MySQL version.

**Remediation:** Make the column nullable but ensure the unique index is a partial index excluding NULLs, or change the code to store a hash of an empty string for missing references.

### 6.2 LOW — `public_tracking_code` length not explicitly constrained

The boot method generates 16-character uppercase codes, but the migration should explicitly set `string('public_tracking_code', 16)` and a unique index to guarantee storage and performance.

**Remediation:** Add an explicit length and unique index if not already present.

### 6.3 POSITIVE — Database hardening

- Enum CHECK constraints added (skipped only on SQLite).
- Production indexes added for all hot paths.
- Foreign keys with `nullOnDelete`.
- Encrypted columns widened to 512 characters.

---

## 7. Frontend / UI / UX / Accessibility Findings

### 7.1 MEDIUM — `welcome.blade.php` is a 72 KB monolith

**Evidence:**

```text
@/home/fsociety/Cwt_academy/resources/views/welcome.blade.php (72,302 bytes)
```

A single Blade view of this size is unmaintainable and likely contains inline scripts/styles that bypass the CSP nonce system. It also slows Vite build parsing and makes code review difficult.

**Remediation:** Decompose into components/partials and ensure all inline scripts carry `nonce="{{ $cspNonce }}"`.

### 7.2 LOW — Turnstile component is not rendered on public forms

Only `recaptcha-v3` and `math-captcha` are included in `login.blade.php`/`register.blade.php`. If the operator configures `CAPTCHA_DRIVER=turnstile`, public auth forms have no widget, although reCAPTCHA v3 is still present.

**Remediation:** Include `components.turnstile` conditionally based on the active CAPTCHA driver.

### 7.3 LOW — `Material Symbols` font is loaded from Google without `dns-prefetch`/`preconnect`

The layout preloads the stylesheet but does not add `<link rel="preconnect">` for `fonts.gstatic.com`, causing unnecessary DNS/TCP latency.

**Remediation:** Add preconnect hints for `fonts.googleapis.com` and `fonts.gstatic.com`.

### 7.4 LOW — Focus management on modal close is missing

`app.js` opens/closes modals but does not restore focus to the trigger element. This is an accessibility regression.

**Remediation:** Store the trigger element and call `.focus()` on close.

### 7.5 POSITIVE — Frontend strengths

- React 19 + Vite 8 + Tailwind CSS v4.
- Spline scene lazy-loaded with error boundary and bfcache handling.
- Password-strength meter and requirement checklist.
- RTL (Kurdish/Arabic) typography support.

---

## 8. Performance & Scalability Findings

### 8.1 MEDIUM — `ResponseCacheMiddleware` caches without Vary considerations

**Evidence:**

```php
@/home/fsociety/Cwt_academy/app/Http/Middleware/ResponseCacheMiddleware.php:67-69
return 'response:v1:'.hash('xxh3', $request->getPathInfo().'?'.($request->getQueryString() ?? '').':'.app()->getLocale());
```

Only path, query string, and locale are included. A cached HTML page may be served with headers that imply it is identical for all users, including cached CSRF tokens that differ per session. The middleware skips authenticated users, which mitigates the CSRF issue, but public pages could still contain session-flashed messages that leak across users.

**Remediation:** Include a session-flash nonce in the key or avoid caching pages that may contain flash messages.

### 8.2 LOW — `CourseService::rememberWithLock` does not cache paginator metadata

The service caches only IDs and total count, then re-hydrates models. This is efficient but the `LengthAwarePaginator` is rebuilt without the original query parameters, which can break URL generation for pagination links.

**Remediation:** Verify pagination link generation in views and pass the original query string to the paginator.

### 8.3 POSITIVE — Performance strengths

- Stampede-safe cache lock in `CourseService::rememberWithLock`.
- Read-replica middleware concept (though implementation flawed).
- Optimised dashboard counts and `whereExists` for approved-waiting Telegram grants.
- Redis-backed queues, cache, and sessions.

---

## 9. Testing & Quality Assurance Findings

### 9.1 MEDIUM — Static analysis failures in tests

PHPStan reports 8 errors in `HomePageDiagnosticTest` and `RouteBlankPageDiagnosticTest` due to unchecked `file_get_contents()` and `getContent()` returning `false`. These are test-only but block a clean CI gate.

**Remediation:** Add explicit `false` checks or use `assertNotFalse()` in tests.

### 9.2 LOW — No tests for the R2 disk download path

Existing tests fake the `local` disk only. The R2-vs-local mismatch in `AdminPaymentProofDownloadController` is therefore undetected.

**Remediation:** Add a feature test that configures `R2_BUCKET` and asserts the download uses the correct disk.

### 9.3 POSITIVE — Test coverage

- 168 passing feature tests covering public flow, admin approval, tracking, uploads, auth, lockout, caching, storage security, and WebAuthn.
- `TestCase::setUp()` clears config/route caches for isolation.
- Real file fixtures for upload validation.

---

## 10. DevOps / Docker / Deployment Findings

### 10.1 MEDIUM — Docker healthcheck depends on `grep` process visibility

**Evidence:**

```yaml
@/home/fsociety/Cwt_academy/docker-compose.yml:64-69
test: ["CMD-SHELL", "ps aux | grep -v grep | grep -q php-fpm && php -v ..."]
```

Process-grep health checks are fragile and can produce false positives/negatives under container process namespace changes.

**Remediation:** Use `php-fpm -t` or a lightweight HTTP/FCGI ping, or the `healthcheck` built into the image.

### 10.2 LOW — nginx `client_max_body_size` (10M) is higher than PHP `post_max_size` (10M) with file overhead

A multipart upload of exactly 10 MB will exceed `post_max_size` because of boundary metadata. The nginx limit should be slightly larger than PHP’s limit (e.g. 12M) to return a clean 413 instead of a generic 500.

**Remediation:** Set `client_max_body_size 12M;` or lower PHP upload limit to 8M.

### 10.3 LOW — `docker-compose.yml` binds MySQL and Redis to loopback only

```yaml
@/home/fsociety/Cwt_academy/docker-compose.yml:87
- "127.0.0.1:3306:3306"
```

This is good for single-host deployments but prevents remote management. Document that this is intentional.

### 10.4 POSITIVE — Docker/nginx hardening

- Non-root PHP-FPM worker (`www-data`).
- Capability dropping and `no-new-privileges`.
- tmpfs `/tmp` with `noexec,nosuid`.
- nginx blocks all `.php` except `/index.php`, and blocks source artifacts.
- Redis dangerous commands renamed to empty.

---

## 11. Documentation & Configuration Findings

### 11.1 MEDIUM — `.env.example` default admin password is invalid

**Evidence:**

```text
@/home/fsociety/Cwt_academy/.env.example (ADMIN_DEFAULT_PASSWORD=change-me-with-secure-password)
```

```php
@/home/fsociety/Cwt_academy/app/Support/Security/PasswordPolicy.php:85-88
if (! preg_match('/[^A-Za-z0-9]/', $password)) { ... }
if (in_array(strtolower($password), ...)) { ... }
```

`change-me-with-secure-password` lacks an uppercase letter and a digit, so `PasswordPolicy::validate()` rejects it. The production preflight will log a critical issue immediately.

**Remediation:** Update `.env.example` to a clearly-invalid placeholder that satisfies the policy (e.g. `ChangeMeBeforeDeployment123!`) or leave it empty.

### 11.2 LOW — `.env.example` references `APP_LOCALE=ku` without documentation

Kurdish (`ku`) is supported but the README and `.env.example` do not explain RTL implications or which locale code to use.

**Remediation:** Add a comment near `APP_LOCALE` documenting supported values (`en`, `ku`) and RTL behaviour.

### 11.3 POSITIVE — Documentation strengths

- README contains setup, helper scripts, quality gates, and common problems.
- `.env.example` includes a production hardening checklist.
- Controllers include extensive inline security rationale.

---

## 12. Risk Register

| ID | Risk | Severity | Likelihood | Impact | Owner |
| ---- | ------ | ---------- | ------------ | -------- | ------- |
| R1 | Automated abuse of public course-request form | High | High | Reputational / operational | Backend |
| R2 | Admins approve proofs but request never becomes active | High | Medium | Financial / support | Backend |
| R3 | R2-backed proof downloads 404 in production | High | Medium | Operational | Backend |
| R4 | Admin API lockout silently disabled | Medium | High | Security | Backend |
| R5 | Read-replica connection leak causing writes to replica | Medium | Medium | Data integrity | Backend |
| R6 | Honey-token guard CPU exhaustion DoS | Medium | Medium | Availability | Backend |
| R7 | Password policy mismatch breaks registration UX | Medium | Medium | UX | Frontend |
| R8 | PHPStan/Pint failures block CI | Medium | High | Process | QA |
| R9 | `welcome.blade.php` unmaintainable / CSP risk | Medium | Low | Maintenance / security | Frontend |

---

## 13. Remediation Roadmap

### Blockers (must fix before production)

1. Add bot protection to the public course-request form.
2. Align the payment-proof approve endpoint with the full approval workflow (or remove it).
3. Fix the download controller to resolve the correct storage disk (`r2` vs `local`).
4. Fix the admin API lockout URL pattern.
5. Correct the `.env.example` default admin password or leave it empty.
6. Resolve PHPStan and Pint failures.

### High-priority (fix within first sprint)

1. Refactor `ReadReplicaMiddleware` to avoid changing the default connection.
2. Replace sliding-window hash in `HoneyTokenGuard` with safe exact matching.
3. Add optional `amount_iqd` / `transaction_reference` fields to the unified web form.
4. Prevent duplicate pending course requests.
5. Add self-approval conflict check to payment-proof policy.
6. Add explicit nullable handling in `WebAuthnPasskeyController`.

### Medium-priority

1. Split `welcome.blade.php` into components.
2. Improve response-cache cache-key variation.
3. Add `preconnect` hints for Google Fonts.
4. Refine nginx health checks and body-size limits.
5. Add dedicated public rejection note field.

---

## 14. Final Verdict

**Score: 76 / 100 — Conditional No-Go.**

Cwt Academy is **functionally solid** (168 passing tests) and **security-minded**, but it currently ships with three high-impact issues that directly affect production correctness:

1. A public surface open to automated abuse.
2. An API endpoint that approves payments without completing the student-access workflow.
3. A storage download path that breaks when R2 is configured.

These are **not architectural problems**; they are localized, fixable issues. After the Blockers and High-priority items in Section 13 are resolved, the project can be re-audited and should comfortably reach **85–88 / 100** and a production **GO**.

---

## 15. Evidence References

All code paths referenced above are from the audited workspace at `/home/fsociety/Cwt_academy/`. Key files:

- `routes/web.php`, `routes/api.php`
- `app/Http/Controllers/Web/CourseRequestController.php`
- `app/Http/Controllers/Admin/PaymentProofController.php`
- `app/Http/Controllers/Web/AdminPaymentProofDownloadController.php`
- `app/Http/Middleware/AdminAccountLockoutMiddleware.php`
- `app/Http/Middleware/ReadReplicaMiddleware.php`
- `app/Http/Middleware/HoneyTokenGuard.php`
- `app/Http/Middleware/DigitalEntropyMiddleware.php`
- `app/Services/Payments/ManualPaymentService.php`
- `app/Policies/PaymentProofPolicy.php`
- `app/Actions/CourseRequests/ApproveCourseRequestAction.php`
- `app/Support/Security/PasswordPolicy.php`
- `app/Http/Controllers/WebAuthn/WebAuthnPasskeyController.php`
- `docker-compose.yml`, `docker/nginx/nginx.conf`, `docker/php/www.conf`
- `.env.example`
- `resources/views/public/request-form.blade.php`
- `resources/views/auth/register.blade.php`
- `resources/views/welcome.blade.php`
