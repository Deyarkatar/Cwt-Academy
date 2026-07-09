# Course Request / Admin Approval E2E Test Report

## 1. What tests were added

- `@/tests/Feature/CourseRequestStudentAdminFlowTest.php` — 11 full web-flow tests covering the real student → admin → tracking lifecycle.
- `@/database/seeders/TestCourseSeeder.php` — safe testing-only seeder that creates a deterministic published course (`test-course`) for browser/E2E runs.
- `@/database/seeders/TestAdminSeeder.php` — safe testing-only seeder that creates a deterministic admin user (`admin@example.com` / `password`) for browser/E2E runs.
- Minimal `data-testid` attributes added to existing markup (no classes or layout changed):
  - `@/resources/views/components/course-card.blade.php` — `data-testid="course-card"`
  - `@/resources/views/public/request-form.blade.php` — `data-testid="course-request-form"`, `data-testid="payment-proof-input"`
  - `@/resources/views/public/request-success.blade.php` — `data-testid="tracking-code"`
  - `@/resources/views/components/status-timeline.blade.php` — `data-testid="tracking-status"`, `data-testid="telegram-manual-instructions"`
  - `@/resources/views/admin/requests.blade.php` — `data-testid="admin-course-request-row"`, `data-testid="admin-approve-button"`, `data-testid="admin-reject-button"`

## 2. Student flow tested

- `student_can_submit_course_request_with_payment_proof`
  - Active course is created via factories.
  - Web form (`POST /course-requests/store`) is submitted with name, email, phone, city, payment method, amount, transaction reference, and a valid JPEG receipt.
  - Asserts `CourseRequest` is created with `PENDING_REVIEW` status and a valid 16-character tracking code.
  - Asserts `PaymentProof` is created with `PENDING` status and the correct amount.
- `student_can_view_tracking_page_after_submission`
  - `GET /track?code=...` returns 200.
  - Asserts the page shows the course title and tracking code.
  - Asserts the status view data is `PENDING_REVIEW`.
  - Asserts admin-only keys (e.g. `payment_proof_status`) are NOT shown without the matching `email_hash`.
- `duplicate_pending_course_request_returns_existing_tracking_code`
  - Confirms a second submission with the same email and course does not create a second request.
  - The original tracking code remains the only one in the database.
- `payment_proof_amount_mismatch_is_blocked`
  - A submitted proof with a different amount than the course price is rejected.
  - No `CourseRequest`, `PaymentProof`, or `TelegramAccessGrant` is created.

## 3. Admin approve flow tested

- `admin_can_approve_course_request`
  - An authenticated SUPER_ADMIN posts to `POST /admin/course-requests/{id}/approve` with the latest payment proof ID.
  - Asserts `CourseRequest` status becomes `APPROVED`, `PaymentProof` becomes `APPROVED`, and a `TelegramAccessGrant` is created with `PENDING_MANUAL_ADD` status.
  - The student tracking page (`/track?code=...&email_hash=...`) now shows status `APPROVED` and the manual Telegram instructions block.
  - No automatic Telegram bot/webhook/invite link is generated; the manual workflow remains.
- `manual_telegram_workflow_is_preserved`
  - Explicitly checks that the grant is created with `PENDING_MANUAL_ADD` and that the tracking page shows the manual-instructions `data-testid`.
  - Confirms no `AUTO_INVITE_SENT` grant exists.

## 4. Admin reject flow tested

- `admin_can_reject_course_request`
  - Admin posts to `POST /admin/course-requests/{id}/reject` with reason `Payment proof is unclear`.
  - Asserts `CourseRequest` status becomes `REJECTED`, the public rejection note is saved.
  - Asserts no `TelegramAccessGrant` is created.
  - The student tracking page shows status `REJECTED` and the rejection reason, and does NOT show Telegram access instructions.

## 5. Payment proof tested

- `payment_proof_download_authorization`
  - An admin can download the proof file via `GET /admin/payment-proofs/{id}/download` and receives a response with a `Content-Disposition` header.
  - A guest is redirected to `/login`.
  - A student is redirected to `/dashboard`.
- `payment_proof_download_returns_404_for_missing_file`
  - Admin request for a proof row whose file no longer exists returns 404 safely.

## 6. Tracking page tested

- Tracking page returns 200 for a valid code.
- With only the code, the page shows the course title and current status but does not expose admin-only fields.
- With the matching `email_hash`, the page reveals payment proof status and Telegram access details.
- Rejected requests display the public rejection reason.
- Approved requests display the manual Telegram access message.

## 7. Telegram manual flow checked

- The app creates a `TelegramAccessGrant` in `PENDING_MANUAL_ADD` status on approval.
- No Telegram bot, no webhook, and no auto-generated invite link is created.
- The admin can later mark the grant as manually added (covered by existing `AdminWebActionsTest` and `AdminApprovalTest`).
- The tracking page only shows the manual-instructions block while the grant is in that state.

## 8. Security / authorization tests added

- `unauthorized_user_cannot_approve_or_reject`
  - Guests hitting approve/reject are redirected to `/login`.
  - Authenticated students hitting approve/reject are redirected to `/dashboard`.
  - The request remains in `PENDING_REVIEW`.
- `payment_proof_download_authorization` covers role-based access for proof downloads.
- `manual_telegram_workflow_is_preserved` verifies no unauthorized auto-invite behavior is introduced.

## 9. CAPTCHA handling in tests

- The existing `CaptchaGuard` already provides a safe test bypass: when no CAPTCHA driver is configured (the default in `APP_ENV=testing`), the guard passes through.
- `test_math_captcha_blocks_invalid_answer_when_enabled` proves that when `CAPTCHA_DRIVER=math` is set, an invalid answer blocks the submission and no request is created. Production behavior remains unchanged.

## 10. Browser / E2E screenshots

- Headless Chrome screenshots were captured to confirm the homepage hero was not affected:
  - English homepage (`/`): text on the left, glossy black robot on the right.
  - Kurdish homepage (`/locale/ku`): robot on the left, Kurdish text on the right.
- Both pages render correctly with no broken layout, no fake SVG robot, and no CWT logo used as the hero robot.
- **Playwright is not installed in this project**, so no separate Playwright E2E tests were added. If Playwright is added later, the `data-testid` selectors above are ready for browser automation.

## 11. Commands run and exact results

```bash
php artisan optimize:clear
php artisan test
```

Result:

```
Tests:    243 passed (919 assertions)
Duration: 22.27s
```

```bash
php artisan test tests/Feature/CourseRequestStudentAdminFlowTest.php --testdox
```

Result:

```
Course Request Student Admin Flow (Tests\Feature\CourseRequestStudentAdminFlow)
 ✔ Student can submit course request with payment proof
 ✔ Student can view tracking page after submission
 ✔ Admin can approve course request
 ✔ Admin can reject course request
 ✔ Unauthorized user cannot approve or reject
 ✔ Duplicate pending course request returns existing tracking code
 ✔ Payment proof amount mismatch is blocked
 ✔ Payment proof download authorization
 ✔ Payment proof download returns 404 for missing file
 ✔ Manual telegram workflow is preserved
 ✔ Math captcha blocks invalid answer when enabled

OK (11 tests, 84 assertions)
```

```bash
./vendor/bin/pint --test
```

Result:

```
PASS   270 files
```

```bash
./vendor/bin/phpstan analyse
```

Result:

```
188/188 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%
[OK] No errors
```

```bash
npm run build
```

Result:

```
✓ built in 1.22s
```

## 12. Bug fixed during testing

- The web tracking page (`/track`) was passing the rejected request's public note to the view under the key `rejection_reason`, but the `status-timeline` component expected the key `public_rejection_note`. This meant the rejection reason was present in the view data but never displayed to the student. The key was corrected in `@/app/Http/Controllers/Web/TrackingController.php` so the reason is now visible on the tracking page. This is a one-line data-key fix, not a UI redesign.

## 13. Files changed

- `@/app/Http/Controllers/Web/TrackingController.php` — fixed rejected-note view data key.
- `@/database/seeders/TestCourseSeeder.php` — new.
- `@/database/seeders/TestAdminSeeder.php` — new.
- `@/tests/Feature/CourseRequestStudentAdminFlowTest.php` — new.
- `@/resources/views/components/course-card.blade.php` — added `data-testid="course-card"`.
- `@/resources/views/public/request-form.blade.php` — added `data-testid="course-request-form"` and `data-testid="payment-proof-input"`.
- `@/resources/views/public/request-success.blade.php` — added `data-testid="tracking-code"`.
- `@/resources/views/components/status-timeline.blade.php` — added `data-testid="tracking-status"` and `data-testid="telegram-manual-instructions"`.
- `@/resources/views/admin/requests.blade.php` — added `data-testid="admin-course-request-row"`, `data-testid="admin-approve-button"`, `data-testid="admin-reject-button"`.
- Built Vite assets updated (hashed filenames changed after `npm run build`).

## 14. Remaining gaps

- No Playwright E2E tests were added because the project does not include Playwright. The Laravel feature tests and headless screenshots verify the flow. If Playwright is installed later, the new `data-testid` selectors can be used to implement the browser scenarios described in the request.
- No Telegram bot/webhook/invite automation was added or tested, as per the requirement to keep the Telegram workflow manual.
- The local development database was not touched; all tests use `RefreshDatabase` on the test database.

## 15. Confirmation homepage hero / UI was not redesigned

- No Blade conditions, CSS classes, layout, image src, or robot component logic were changed.
- Only harmless `data-testid` attributes were added to existing elements.
- Headless screenshots for both English and Kurdish homepages match the previously verified correct layouts.
