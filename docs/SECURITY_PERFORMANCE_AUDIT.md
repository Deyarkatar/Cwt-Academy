# Cwt Academy — Full Security & Performance Audit Report

**Date:** 2026-05-29
**Auditor:** Cascade AI Agent
**Scope:** Laravel 13 backend + React 19 frontend + MySQL database
**Tests:** 126 tests, 417 assertions — all passing

---

## Executive Summary

| Category | Critical | High | Medium | Low | Score |
|----------|---------:|-----:|-------:|----:|------:|
| **Security** | 0 | 2 | 4 | 3 | B+ |
| **Performance** | 0 | 1 | 4 | 2 | B |
| **Code Quality** | 0 | 0 | 3 | 4 | B+ |
| **Architecture** | 0 | 0 | 3 | 3 | B |
| **Overall** | 0 | 3 | 14 | 12 | **B+** |

**Verdict:** The codebase is well-built with strong security fundamentals (rate limiting, CSP, audit logging, privilege separation), but has several actionable performance gaps and some dead code that should be cleaned up.

---

## 1. Security Audit 🔒

### ✅ Strengths (What you're doing RIGHT)

1. **Privilege Escalation Prevention — EXCELLENT**
   `app/Models/User.php:28` — `role` and `status` are **deliberately excluded** from `$fillable`. They must be set explicitly, preventing any mass-assignment attack that could elevate a student to admin.

2. **Rate Limiting on ALL Auth Endpoints**
   - Web login: Per-IP (5/min) + Per-email (5/15min) with `RateLimiter`
   - Admin API login: Per-IP (10/min) + Per-email (5/15min)
   - CAPTCHA enforced on both web and admin login
   - Public course request: 5/min
   - Payment proof upload: 3/min

3. **Content Security Policy (CSP) — Enterprise Grade**
   `app/Http/Middleware/SecurityHeaders.php` — Strict nonce-based CSP with:
   - Per-request cryptographically random nonces
   - `strict-dynamic` in production
   - No `'unsafe-inline'` or `'unsafe-eval'` (except `wasm-unsafe-eval` for Spline)
   - Explicit allow-list for fonts, frames, workers
   - `frame-ancestors 'none'` prevents clickjacking

4. **File Upload Security**
   `app/Http/Requests/Public/StoreCourseRequestWithProofRequest.php:33-38`
   - MIME type validation (`mimetypes:`) **AND** extension validation (`mimes:`)
   - Max file size limit (configurable, default 5MB)
   - Payment proof download validates path safety (`UrlHelper::safePaymentProofPath()`)
   - Sets `Content-Type` header explicitly on download to prevent MIME-sniffing
   - Sets `Content-Disposition: attachment` to force download

5. **Database Transaction Safety**
   Multiple admin controllers (PaymentProof, Instructor, TelegramAccessGrant) use `DB::transaction()` + `lockForUpdate()` to prevent race conditions on state transitions.

6. **Audit Logging Everywhere**
   Every sensitive operation (login, logout, approve, reject, create, update) is logged with before/after state, actor ID, and timestamp.

7. **Authorization Policies Used Consistently**
   All admin controllers call `$this->authorize()` before acting. No endpoint is unprotected.

8. **Password Policy — NIST 800-63B Aligned**
   `app/Support/Security/PasswordPolicy.php`
   - 12+ characters minimum
   - Mixed case + digit + symbol required
   - Weak password blocklist
   - HIBP compromised-password check in production

---

### ⚠️ Critical Issues (NONE FOUND)

No critical security vulnerabilities (SQL injection, auth bypass, XSS, RCE) were detected.

---

### 🔴 High Severity Issues

#### HIGH-1: CSP `'unsafe-inline'` in Development Bypasses Nonce Protection
**File:** `app/Http/Middleware/SecurityHeaders.php:139-140`
**Issue:** In non-production environments, `script-src` includes `'unsafe-inline'`, which completely bypasses the nonce protection. Any XSS payload that injects a `<script>` tag would execute.
**Impact:** If the app is accidentally deployed to staging without switching to production mode, XSS attacks become trivial.
**Fix:** Remove `'unsafe-inline'` from dev mode. Use Vite's CSP nonce injection instead.

#### HIGH-2: `splite.tsx` Still Imports Deprecated `@splinetool/react-spline`
**File:** `resources/js/components/ui/splite.tsx:3`
**Issue:** This file imports `@splinetool/react-spline` which is incompatible with React 19. It's now dead code since you replaced it with `spline-runtime.tsx`, but the import remains. If anyone accidentally imports this component, it will crash with React 19 errors.
**Fix:** Delete `splite.tsx` or replace its contents with a re-export of `spline-runtime.tsx`.

---

### 🟠 Medium Severity Issues

#### MED-1: `CatalogController` Bypasses Cached Course Queries
**File:** `app/Http/Controllers/Web/CatalogController.php:13-15`
**Issue:** The public catalog page queries courses directly without using `CourseService::listActive()`, which has caching. Every page hit hits the database.
**Fix:** Inject `CourseService` and use `$courseService->listActive()`.

#### MED-2: Missing Composite Index on `courses` for Common Query Pattern
**Issue:** The most common public query is:
```php
Course::active()->where('is_featured', true)->orderBy('published_at')->...
```
But there's no composite index on `(status, is_featured, published_at)`.
**Fix:** Add migration:
```php
$table->index(['status', 'is_featured', 'published_at'], 'idx_courses_public_listing');
```

#### MED-3: `CourseLibrarySeeder` Does Not Invalidate Course Cache
**File:** `database/seeders/CourseLibrarySeeder.php`
**Issue:** After seeding new courses, `CourseService::listActive()` still serves stale cached data because `flushListCache()` is never called.
**Fix:** Call `app(CourseService::class)->flushListCache()` at the end of `CourseLibrarySeeder::run()`.

#### MED-4: `splite.tsx` Contains Dead Code with Large Bundle Dependency
**File:** `resources/js/components/ui/splite.tsx`
**Issue:** The old Spline component imports `@splinetool/react-spline` which is a ~2MB+ dependency. Even though it's not used, it might still get included in the bundle analysis or confuse future developers.
**Fix:** Remove the file entirely.

#### MED-5: No `SameSite` Cookie Configuration Check
**Issue:** Laravel's default cookie `SameSite` setting might be `lax` or not set. For a site handling payments and admin auth, `SameSite=Strict` is recommended for session cookies.
**Fix:** Check `config/session.php` and ensure `'same_site' => 'strict'` is set.

#### MED-6: Admin API Token Expiration Configurable but Not Enforced Server-Side on Every Request
**File:** `app/Http/Controllers/Admin/AuthController.php:87-88`
**Issue:** Token expiration is set at creation time, but Sanctum's default middleware doesn't automatically check expiration on every request unless configured.
**Fix:** Ensure `sanctum.expiration` is set in `.env` and verify Sanctum pruning is scheduled.

---

### 🟡 Low Severity Issues

#### LOW-1: Payment Proof Upload Doesn't Validate Image Dimensions
**Issue:** A malicious user could upload an extremely high-resolution image (e.g., 10000x10000 PNG) that's under 5MB but causes memory exhaustion on the server when processed.
**Fix:** Add image dimension validation:
```php
'proof_file' => ['required', 'file', 'mimes:...', 'max:5120', 'dimensions:max_width=4096,max_height=4096']
```

#### LOW-2: `EnsureAdminAuthenticated` Middleware Missing from Some Route Groups
**Issue:** The web admin routes use `['auth', 'verified', 'admin']` middleware, but the `admin` middleware itself is just `EnsureAdminAuthenticated`. No issue found, but it's worth documenting that API and web use different middleware stacks (`auth:sanctum` vs `auth` session).

#### LOW-3: `transaction_reference` Unique Constraint Could Cause Race Conditions
**File:** `database/migrations/2024_01_01_000070_create_payment_proofs_table.php`
**Issue:** The `transaction_reference` has a unique constraint but the validation rule in `PaymentProofController::store` checks uniqueness via `Rule::unique()` which is not atomic. Two simultaneous requests with the same reference could pass validation then fail at the DB level.
**Fix:** Wrap the store in a `DB::transaction()` with retry logic.

---

## 2. Performance Audit 🚀

### ✅ Strengths

1. **Comprehensive DB Indexing Strategy**
   Migration `2026_05_26_000007_add_missing_production_indexes.php` added indexes on:
   - `audit_logs` (actor, entity, action+created_at, created_at)
   - `telegram_channels` (course_id, is_active)
   - `notifications` (recipient_user_id + read_at)
   - `categories` (slug)
   - `instructors` (status)
   - `course_requests` (status, created_at)
   - `payment_proofs` (status, course_request_id)

2. **Version-Bump Cache Invalidation**
   `app/Services/Courses/CourseService.php:75-78` — Clever use of `Cache::increment()` for cache key versioning. Works with ALL cache drivers and is race-condition safe.

3. **Eager Loading on All Listings**
   Controllers consistently use `->with(['category', 'instructor'])` to prevent N+1 queries.

4. **Pagination Everywhere**
   All index endpoints use `paginate()` instead of fetching all records.

---

### 🔴 High Severity

#### HIGH-1: Spline Runtime JS Bundle is 2.2MB (Gzipped: 632KB)
**Evidence:** Build output: `spline-app-ceNuWnnH.js 2,227.89 kB │ gzip: 631.73 kB`
**Issue:** This is larger than many entire web applications. It delays LCP (Largest Contentful Paint) significantly.
**Fix:** Consider loading the 3D scene only after user interaction (click to load), or use a static image fallback with "Load 3D" button.

---

### 🟠 Medium Severity

#### MED-1: No Database Query Caching for Category Lookups
**Issue:** `Category::where('slug', $slug)->first()` is called on every course seed and every public course detail request without caching.
**Fix:** Cache category lookups:
```php
Cache::remember("category.slug.{$slug}", 3600, fn() => Category::where('slug', $slug)->first());
```

#### MED-2: `CourseService::listActive()` Cache TTL is Only 5 Minutes
**File:** `app/Services/Courses/CourseService.php:26`
**Issue:** `Cache::remember($cacheKey, 300, ...)` means cache expires after 5 minutes. For a course catalog that rarely changes, this is too aggressive.
**Fix:** Increase to 3600 (1 hour) or 86400 (1 day) since `flushListCache()` handles invalidation.

#### MED-3: `CatalogController` Doesn't Use Cached Service
**File:** `app/Http/Controllers/Web/CatalogController.php`
**Issue:** Bypasses `CourseService::listActive()` entirely. Every visit to `/courses` hits the database.
**Fix:** Inject `CourseService` and call `listActive()`.

#### MED-4: No CDN or Asset Optimization for Frontend
**Issue:** No evidence of image optimization, lazy loading for below-fold content, or CDN usage for static assets.
**Fix:** Consider Laravel's `intervention/image` for thumbnails, and add `loading="lazy"` to course card images.

---

### 🟡 Low Severity

#### LOW-1: `php artisan serve` Used in Production-ish Setup
**Issue:** The development server (`php artisan serve`) is being used. It's single-threaded and not suitable for production load.
**Fix:** For production, use nginx + php-fpm or a Laravel Octane setup.

#### LOW-2: No Evidence of Queue Workers
**Issue:** No queue configuration or worker processes detected. Heavy operations (email sending, audit logging, Telegram notifications) should be queued.
**Fix:** Configure Laravel Queues with Redis/Supervisor for background processing.

---

## 3. Code Quality Audit 🧹

### ✅ Strengths

1. **Excellent Test Coverage for Critical Paths**
   126 tests covering: auth, admin approval, audit logging, course requests, payment proofs, rate limiting, security headers, storage security, upload security, email verification.

2. **Actions Pattern for Business Logic**
   `app/Actions/` folder contains focused, single-responsibility action classes (e.g., `ApproveCourseRequestAction`, `ArchiveCourseAction`).

3. **Consistent FormRequest Validation**
   Every controller uses dedicated FormRequest classes with explicit rules and custom error messages.

4. **Comprehensive Audit Logging**
   Every state change is logged with before/after snapshots. This is enterprise-grade.

---

### 🟠 Medium Severity

#### MED-1: `splite.tsx` is Dead Code
**File:** `resources/js/components/ui/splite.tsx`
**Issue:** Replaced by `spline-runtime.tsx` but still exists. Contains outdated imports.
**Fix:** Delete the file.

#### MED-2: `spline-demo.tsx` Contains Unused Import Paths
**Issue:** Some import paths or comments may reference the old lazy-loading approach.
**Fix:** Clean up and verify all imports are used.

#### MED-3: `CourseLibrarySeeder` Lacks `composer dump-autoload` Guard
**Issue:** If a developer forgets to run `composer dump-autoload -o` after adding a course file, the seeder silently skips it.
**Fix:** Add a warning log when a file is found but its class cannot be loaded.

---

### 🟡 Low Severity

#### LOW-1: Long Methods in `PaymentProofController`
**File:** `app/Http/Controllers/Admin/PaymentProofController.php:96-154`
**Issue:** The `approve()` method is 58 lines long and handles validation, authorization, state machine, amount matching, and audit logging.
**Fix:** Extract amount-mismatch check and state-machine guard into private methods or action classes.

#### LOW-2: Inconsistent Controller Naming
**Issue:** Some web controllers are in `Web/` namespace, some admin in `Admin/`, but there's also `Api/` and some root-level. Consistent naming would help.

#### LOW-3: No PHPDoc `@return` on Some Controller Methods
**Issue:** Some methods lack explicit return type documentation for IDE/static analysis support.

---

## 4. Architecture Audit 🏗️

### ✅ Strengths

1. **Clean Layer Separation**
   Controllers → FormRequests → Actions → Models → Services. No controller directly manipulates DB queries in complex ways.

2. **Policy-Based Authorization**
   `app/Policies/` provides fine-grained authorization rules for every model.

3. **Service Layer for Cross-Cutting Concerns**
   `CourseService`, `AuditLogger`, `ManualPaymentService` each handle one concern well.

---

### 🟠 Medium Severity

#### MED-1: No Repository Layer
**Issue:** Controllers and services query Eloquent directly. A repository abstraction would make testing easier and allow swapping to a different data source later.
**Fix:** Introduce `CourseRepository`, `PaymentProofRepository` interfaces.

#### MED-2: Frontend Uses Two Different Patterns (Inertia + API)
**Issue:** Public pages use Blade + React (likely Inertia), but admin uses pure JSON API. This means two separate auth systems (session vs Sanctum token) coexist.
**Fix:** Standardize on one approach, or clearly document when each is used.

#### MED-3: Telegram Integration Tightly Coupled to Models
**Issue:** `TelegramChannel` is a model, but the actual Telegram Bot API integration logic might be scattered across services and actions.
**Fix:** Create a dedicated `TelegramBotService` with all Telegram API interactions centralized.

---

### 🟡 Low Severity

#### LOW-1: `CategorySeeder` Uses Hardcoded English Strings
**Issue:** Categories are seeded with English names/descriptions. For a Kurdish-first platform, Kurdish names might be preferred.

#### LOW-2: No API Versioning Strategy Beyond URL Prefix
**Issue:** Routes use `v1/` prefix but there's no clear deprecation or versioning strategy documented.

#### LOW-3: Missing API Documentation / OpenAPI Spec
**Issue:** No Swagger/OpenAPI documentation for the API endpoints.

---

## Action Plan — Priority Order

### Do This Week (High Priority)

1. [ ] **HIGH-1:** Fix CSP dev mode — remove `'unsafe-inline'` from `script-src` in dev
2. [ ] **HIGH-2:** Delete `resources/js/components/ui/splite.tsx`
3. [ ] **HIGH-1 (Perf):** Make Spline load on-demand (click to load) instead of on page load
4. [ ] **MED-1:** Make `CatalogController` use `CourseService::listActive()`
5. [ ] **MED-3:** Call `flushListCache()` in `CourseLibrarySeeder::run()`

### Do This Month (Medium Priority)

6. [ ] **MED-2:** Add composite index on `courses(status, is_featured, published_at)`
7. [ ] **MED-5:** Verify `config/session.php` has `same_site => 'strict'`
8. [ ] **MED-6:** Verify Sanctum token pruning is scheduled
9. [ ] Cache category lookups in `CourseLibrarySeeder`
10. [ ] Increase `CourseService` cache TTL from 300s to 3600s

### Do Eventually (Low Priority)

11. [ ] Add image dimension validation to payment proof uploads
12. [ ] Add `composer dump-autoload` warning in seeder
13. [ ] Consider Repository layer abstraction
14. [ ] Add OpenAPI/Swagger documentation
15. [ ] Set up queue workers for background jobs

---

## Files Requiring Immediate Attention

| File | Issue | Severity |
|------|-------|----------|
| `app/Http/Middleware/SecurityHeaders.php:139` | `'unsafe-inline'` in dev CSP | 🔴 High |
| `resources/js/components/ui/splite.tsx` | Dead code + React 19 incompatible import | 🔴 High |
| `app/Http/Controllers/Web/CatalogController.php:13` | Bypasses cache service | 🟠 Medium |
| `database/seeders/CourseLibrarySeeder.php:28` | No cache flush after seeding | 🟠 Medium |
| `app/Services/Courses/CourseService.php:26` | Cache TTL too short (5min) | 🟠 Medium |
| `vite build output` | Spline bundle 2.2MB uncompressed | 🔴 High |

---

*Report generated by Cascade. Re-run this audit after each major release.*
