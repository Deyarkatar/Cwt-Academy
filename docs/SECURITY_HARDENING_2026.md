# Security Hardening Guide — CWT Academy 2026

## 1. HTTPS / TLS
- `ForceHttps` exempts local/private IPs (`127.0.0.1`, `10.x`, `192.168.x`, `::1`).
- Production enforces HTTPS via `FORCE_HTTPS=true`.

## 2. Authentication & Authorization
- `User` implements `MustVerifyEmail`; verification routes added.
- Student routes use `verified` middleware.
- Admin `/dashboard` redirects to `/admin`.
- Web admin download uses `PaymentProof` policy authorization.
- Session regenerated on login/register.

## 3. Database Security
- `role` and `status` excluded from `$fillable`.
- Migration adds indexes on `users`, `courses`, `course_requests`, `payment_proofs`.
- Numeric `CHECK` constraints for `price_iqd` and `amount_iqd`.

## 4. Concurrency
- `PaymentProofController::approve/reject` locks proof row with `lockForUpdate()`.
- Amount-mismatch guard in `approve()`.
- `ManualPaymentService::storeProof()` locks `CourseRequest` row.

## 5. CAPTCHA
- Turnstile: production fail-closed, dev fail-open.
- Math CAPTCHA: 5-min expiry, session cleared after verification.

## 6. Security Headers
- Universal: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `X-XSS-Protection: 0`, CSP.
- Production only: `Permissions-Policy`, COOP/COEP/CORP, HSTS, cache-control on sensitive routes.

## 7. File Uploads
- MIME whitelist + magic bytes validation.
- Extension derived from validated MIME, not user input.
- UUID filenames, private `local` disk storage.
- Path traversal prevention via `UrlHelper::safePath()`.

## 8. Frontend Security
- Inline JS removed from `login.blade.php` and `register.blade.php`.
- Password toggle and strength meter moved to `resources/js/app.js`.
- No unescaped `{!!` output found in views.

## 9. Input Validation
- `amount_iqd`: `min:1,max:10000000`.
- `sender_name`: regex for letters/spaces/hyphens only.
- `proof_file`: proper KB max calculation (`maxMb * 1024`).

## 10. Session & Cookies
- Argon2id configured in production (`memory=65536, time=4, threads=1`).
- `AppServiceProvider` validates production session config at boot.

## Files Changed
- `app/Http/Middleware/ForceHttps.php`
- `app/Http/Middleware/SecurityHeaders.php`
- `app/Models/User.php`
- `app/Providers/AppServiceProvider.php`
- `app/Http/Controllers/Admin/PaymentProofController.php`
- `app/Services/Captcha/TurnstileService.php`
- `app/Services/Captcha/MathCaptchaService.php`
- `app/Http/Requests/Public/StorePaymentProofRequest.php`
- `resources/js/app.js`
- `resources/views/auth/login.blade.php`
- `resources/views/auth/register.blade.php`
- `resources/views/auth/verify-email.blade.php`
- `routes/web.php`
- `database/migrations/2026_05_24_000000_add_production_db_constraints.php`
