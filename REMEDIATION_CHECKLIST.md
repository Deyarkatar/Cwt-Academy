# Remediation Checklist — Cwt Academy 30-Task Plan

This is the working checklist for the production-remediation engagement. Each item maps the blocker/defect to the affected file, the planned fix, the required test(s), and the verification command.

## Phase 1 — Baseline

| Task | Status | Affected file(s) | Planned fix | Tests needed | Verification |
| ---- | ------ | ---------------- | ----------- | ------------ | ------------ |
| 1. Repository baseline scan | Done | All | Read-only review of routes, controllers, middleware, services, actions, policies, models, migrations, tests, views, frontend, Docker, configs | N/A | N/A |
| 2. Baseline verification | Done | N/A | Run all verification commands and capture output | N/A | See `BASELINE_VERIFICATION.txt` |
| 3. Remediation checklist | Done | `REMEDIATION_CHECKLIST.md` | Create this checklist | N/A | N/A |

## Phase 2 — Public Course Request Bot Protection

| Task | Status | Affected file(s) | Planned fix | Tests needed | Verification |
| ---- | ------ | ---------------- | ----------- | ------------ | ------------ |
| 4. Inspect CAPTCHA infra | Done | `TurnstileService.php`, `MathCaptchaService.php`, `VerifyTurnstile.php`, `components/turnstile.blade.php`, `components/math-captcha.blade.php` | Confirm reusable driver-based CAPTCHA | N/A | Read |
| 5. Server-side bot protection | Done | `routes/web.php`, `StoreCourseRequestWithProofRequest.php`, `CourseRequestController.php` | Add CAPTCHA validation to public POST; keep throttle; fail safe | New feature tests | `php artisan test` |
| 6. Render CAPTCHA in form | Done | `resources/views/public/request-form.blade.php` | Include turnstile/math component based on driver | Visual / rendering test | `php artisan test` |
| 7. Add bot-protection tests | Done | `tests/Feature/PublicRequestFlowTest.php` or new test | Valid/missing/invalid CAPTCHA, throttle still works | New test cases | `php artisan test` |

## Phase 3 — Payment Proof Approval Workflow

| Task | Status | Affected file(s) | Planned fix | Tests needed | Verification |
| ---- | ------ | ---------------- | ----------- | ------------ | ------------ |
| 8. Inspect approval flows | Done | `Admin/PaymentProofController.php`, `ApproveCourseRequestAction.php`, routes | Compare workflows | N/A | Read |
| 9. Fix payment-proof approve endpoint | Done | `Admin/PaymentProofController.php` | Delegate to `ApproveCourseRequestAction` via parent request | Update `AdminApprovalTest` | `php artisan test` |
| 10. Add approval endpoint tests | Done | `tests/Feature/AdminApprovalTest.php` | Proof approval creates grant, rejects self-approval, handles duplicate/amount mismatch | New test cases | `php artisan test` |

## Phase 4 — Payment Proof Storage Disk Fix

| Task | Status | Affected file(s) | Planned fix | Tests needed | Verification |
| ---- | ------ | ---------------- | ----------- | ------------ | ------------ |
| 11. Inspect storage logic | Done | `ManualPaymentService.php`, `AdminPaymentProofDownloadController.php`, `filesystems.php`, model | Understand disk selection and proof fields | N/A | Read |
| 12. Centralize disk resolution | Done | `ManualPaymentService.php`, new helper/service | Minimal safe approach: centralize disk name resolution | N/A | `php artisan test` |
| 13. Fix download controller | Done | `AdminPaymentProofDownloadController.php` | Use correct disk; keep path/auth checks | Update storage tests | `php artisan test` |
| 14. Add storage tests | Done | `tests/Feature/StorageSecurityTest.php` | R2-configured path, missing file 404, path traversal, unauthorized | New test cases | `php artisan test` |

## Phase 5 — Admin API Lockout Fix

| Task | Status | Affected file(s) | Planned fix | Tests needed | Verification |
| ---- | ------ | ---------------- | ----------- | ------------ | ------------ |
| 15. Fix route-pattern mismatch | Done | `AdminAccountLockoutMiddleware.php` | Match `/api/admin/login`; keep `/login` | Update lockout tests | `php artisan test` |
| 16. Improve response behavior | Done | `AdminAccountLockoutMiddleware.php` | Return JSON for API, redirect+flash for web | Update tests | `php artisan test` |
| 17. Add lockout regression tests | Done | `tests/Feature/AdminAccountLockoutTest.php` | Failed API login triggers lockout, expiry, web not broken | New test cases | `php artisan test` |

## Phase 6 — Env and Password Policy Consistency

| Task | Status | Affected file(s) | Planned fix | Tests needed | Verification |
| ---- | ------ | ---------------- | ----------- | ------------ | ------------ |
| 18. Fix `.env.example` admin password | Done | `.env.example` | Replace with compliant placeholder or empty value + comment | N/A | `php artisan test` (preflight) |
| 19. Fix frontend/backend password mismatch | Done | `resources/views/auth/register.blade.php` | Update UI to 12-character minimum | N/A | Visual / test if present |

## Phase 7 — Static Analysis and Style

| Task | Status | Affected file(s) | Planned fix | Tests needed | Verification |
| ---- | ------ | ---------------- | ----------- | ------------ | ------------ |
| 20. Fix PHPStan errors | Done | `WebAuthnPasskeyController.php`, `HomePageDiagnosticTest.php`, `RouteBlankPageDiagnosticTest.php` | Add null assertions / false checks | N/A | `./vendor/bin/phpstan analyse` |
| 21. Fix Pint style failures | Done | `WebAuthnLoginController.php`, `RouteBlankPageDiagnosticTest.php` | Run Pint and accept fixes | N/A | `./vendor/bin/pint --test` |

## Phase 8 — High-Priority Security and Business Logic

| Task | Status | Affected file(s) | Planned fix | Tests needed | Verification |
| ---- | ------ | ---------------- | ----------- | ------------ | ------------ |
| 22. Self-approval protection for payment proof | Done | `PaymentProofPolicy.php`, maybe controller | Add email mismatch check | New tests | `php artisan test` |
| 23. Prevent duplicate pending course requests | Done | `CreateCourseRequestAction.php` or controller | Check existing pending request for same email+course | New tests | `php artisan test` |
| 24. Fix unified public form audit trail | Done | `StoreCourseRequestWithProofRequest.php`, `CourseRequestController.php`, view | Add optional transaction_reference; amount defaults to course price | New tests | `php artisan test` |
| 25. Handle free courses consistently | Done | `StoreCourseRequest.php`, `CourseRequestController.php` | Enforce min:1 price or skip proof for free courses | New tests | `php artisan test` |

## Phase 9 — Performance / Reliability

| Task | Status | Affected file(s) | Planned fix | Tests needed | Verification |
| ---- | ------ | ---------------- | ----------- | ------------ | ------------ |
| 26. Refactor ReadReplicaMiddleware | Done | `ReadReplicaMiddleware.php` | Do not switch default connection; disable behavior safely | New tests or remove | `php artisan test` |
| 27. Fix HoneyTokenGuard CPU risk | Done | `HoneyTokenGuard.php` | Replace sliding hash with exact/bounded matching | New tests | `php artisan test` |

## Phase 10 — Frontend / UI / UX

| Task | Status | Affected file(s) | Planned fix | Tests needed | Verification |
| ---- | ------ | ---------------- | ----------- | ------------ | ------------ |
| 28. Improve critical UI/UX mismatches | Done | `request-form.blade.php`, `register.blade.php` | Password text, CAPTCHA, transaction reference field, manual Telegram instructions | N/A | `npm run build`, visual |

## Phase 11 — Documentation and Final Verification

| Task | Status | Affected file(s) | Planned fix | Tests needed | Verification |
| ---- | ------ | ---------------- | ----------- | ------------ | ------------ |
| 29. Documentation synchronization | Done | `README.md`, `ADMIN_GUIDE.md`, `API_DOCUMENTATION.md`, `DEPLOYMENT.md` | Update docs to match new behavior | N/A | Read |
| 30. Final verification and report | Done | `PRODUCTION_30_TASK_REMEDIATION_REPORT.md` | Run all commands and write final report | N/A | All verification commands |
