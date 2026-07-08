# Cwt Academy — 30-Task Production Remediation Report

**Date:** 2026-07-08
**Scope:** Fix all production blockers and high-priority defects identified in the 2026 audit.
**Verification status:** All automated checks passing.

## Executive Summary

All 30 tasks in the remediation plan have been completed. The work focused on:

- Securing the public course-request flow with server-side CAPTCHA validation.
- Hardening the payment-proof approval workflow and storage handling.
- Fixing authentication/lockout route mismatches and response behavior.
- Aligning password policy messaging between frontend and backend.
- Resolving all PHPStan and Pint failures.
- Adding business-logic protections (self-approval, duplicate-request prevention, free-course handling).
- Removing unsafe performance paths (read-replica connection switching, honey-token sliding hash).

## Verification Results

```text
PHPStan:  OK (no errors)
Pint:     PASS (264 files)
PHPUnit:  OK (197 tests, 642 assertions)
```

## Key Changes

### Phase 2 — Public Course Request Bot Protection

- `app/Services/Captcha/CaptchaGuard.php` — New driver-agnostic guard that verifies Turnstile and Math CAPTCHA tokens.
- `app/Http/Controllers/Web/CourseRequestController.php` — Validates CAPTCHA before processing the unified form.
- `resources/views/public/request-form.blade.php` — Renders Turnstile + Math CAPTCHA widgets and error messages.
- `tests/Feature/PublicCourseRequestCaptchaTest.php` — Full coverage of success/failure/no-driver scenarios.

### Phase 3 — Payment-Proof Approval Workflow

- `app/Http/Controllers/Admin/PaymentProofController.php` — `approve()` now delegates to `ApproveCourseRequestAction`, completing the full workflow (status update, Telegram access grant, audit log).
- `tests/Feature/AdminApprovalTest.php` — Added permission, workflow, duplicate-approval, and self-approval tests.

### Phase 4 — Storage Disk Fix

- `app/Services/Payments/ManualPaymentService.php` — Added `storageDisk()` helper to centralize R2 vs local selection.
- `app/Http/Controllers/Web/AdminPaymentProofDownloadController.php` — Uses centralized disk resolution.
- `tests/Feature/StorageSecurityTest.php` — Added R2 upload/download, missing-file 404, and path-traversal tests.

### Phase 5 — Admin API Lockout

- `app/Http/Middleware/AdminAccountLockoutMiddleware.php` — Fixed route pattern to `/api/admin/login`; API returns JSON 423, web returns redirect+flash.
- `tests/Feature/AdminAccountLockoutTest.php` — Added repeated-failure and web-redirect regression tests.

### Phase 6 — Env and Password Policy

- `.env.example` — Replaced weak default admin password with compliant placeholder `ChangeMeBeforeDeployment123!`.
- `lang/en/auth.php`, `lang/ku/auth.php`, `resources/js/app.js` — Updated password requirement text and strength meter from 8 to 12 characters.

### Phase 7 — Static Analysis and Style

- Fixed all PHPStan errors (nullable user, mixed session/Mockery values, string|false response content, false store paths).
- Ran Pint across the codebase; all 264 files now pass style checks.

### Phase 8 — Security and Business Logic

- `app/Policies/PaymentProofPolicy.php` — Added self-approval guard using the parent course-request student email.
- `app/Models/CourseRequest.php` + `database/migrations/2026_07_08_000000_add_email_hash_to_course_requests.php` — Added `student_email_hash` for duplicate detection and backfilled existing rows.
- `app/Actions/CourseRequests/CreateCourseRequestAction.php` — Returns existing pending request instead of creating duplicates.
- `app/Services/Payments/ManualPaymentService.php` — Logs `PAYMENT_PROOF_SUBMITTED` audit event.
- `app/Http/Controllers/Web/CourseRequestController.php` + `app/Http/Controllers/Api/CourseRequestController.php` — Skips proof upload for free courses (`price_iqd = 0`) and transitions to `PENDING_REVIEW`.
- `app/Http/Requests/Public/StoreCourseRequestWithProofRequest.php` — Added optional `amount_iqd` and `transaction_reference` validation.

### Phase 9 — Performance / Reliability

- `bootstrap/app.php` — Removed `ReadReplicaMiddleware` from the web middleware group to prevent accidental writes to the read replica.
- `app/Http/Middleware/HoneyTokenGuard.php` — Replaced CPU-heavy sliding-window SHA-256 loop with bounded exact/substring checks.
- `tests/Feature/HoneyTokenGuardTest.php` — Added functional and large-body CPU-exhaustion regression tests.

### Phase 10 — UI / UX

- `resources/views/public/request-form.blade.php` — Added optional amount/transaction-reference fields, conditionally marked payment proof required for paid courses, and showed "Free" for zero-priced courses.
- `lang/en/request.php`, `lang/ku/request.php` — Added `free_course` translation.

### Phase 11 — Documentation

- `REMEDIATION_CHECKLIST.md` — Updated all tasks to `Done`.
- `PRODUCTION_30_TASK_REMEDIATION_REPORT.md` — This report.

## Verification Commands

Run the following commands to confirm the state of the project:

```bash
# Static analysis
./vendor/bin/phpstan analyse --memory-limit=2G

# Code style
./vendor/bin/pint --test

# Test suite
./vendor/bin/phpunit
```

## Notes

- No Telegram bot automation was introduced; the manual Telegram access workflow is preserved.
- No security controls were weakened.
- All changes are scoped to the affected code paths and include regression tests where applicable.
