# BUSINESS_LOGIC_ABUSE.md

**Cwt Academy — Business Logic, Financial & Workflow Abuse Assessment**

---

## 1. Payment Proof Abuse

### 1.1 Unlimited Proof Resubmission (HIGH)

- **Severity:** HIGH | **CVSS:** 5.3 | **CWE:** CWE-840
- **Location:** `app/Services/Payments/ManualPaymentService.php:23-96`
- **Issue:** Students can submit an unlimited number of payment proofs for the same course request (limited only by the `throttle:3,1` middleware). Each proof creates a new file on disk and a new database row.
- **Attack:** A botnet submits 3 proofs per minute per IP, filling the disk with junk files and overwhelming the admin review queue.
- **Fix:** Add a `max_proofs_per_request` check in `storeProof`, or change `canSubmitPaymentProof()` to only allow submission when status is `PENDING_PAYMENT` (not `PENDING_REVIEW`).

### 1.2 Amount Mismatch After Submission (MEDIUM)

- **Severity:** MEDIUM | **CVSS:** 4.3 | **CWE:** CWE-840
- **Location:** `app/Http/Controllers/Web/PaymentProofController.php:28-48`
- **Issue:** Students can submit a payment proof with any amount between 1 and 10,000,000 IQD, regardless of the actual course price. The amount mismatch is only caught during admin approval.
- **Impact:** Students may submit proofs for incorrect amounts, wasting admin time. No direct financial loss because the admin must explicitly approve.
- **Fix:** Enforce `$validated['amount_iqd'] === $courseRequest->course->price_iqd` at submission time.

### 1.3 Transaction Reference Collision (MEDIUM)

- **Severity:** MEDIUM | **CVSS:** 4.3 | **CWE:** CWE-363
- **Location:** `app/Http/Controllers/Web/PaymentProofController.php:31`
- **Issue:** The `transaction_reference` has a unique constraint, but a NULL reference is allowed. Two students could both submit NULL references without collision. However, if one student submits a reference like "12345", no other student can ever submit "12345" again, even if it's a legitimate payment from a different bank/branch.
- **Impact:** Denial of service for legitimate payers using common reference numbers.
- **Fix:** Make `transaction_reference` unique per `(course_request_id, transaction_reference)` instead of globally unique, or remove uniqueness and rely on admin review.

---

## 2. Course Request Abuse

### 2.1 Guest Request Spam (MEDIUM)

- **Severity:** MEDIUM | **CVSS:** 5.3 | **CWE:** CWE-770
- **Location:** `routes/api.php:31`, `routes/web.php:119`
- **Issue:** No authentication is required to create a course request. Rate limiting is `throttle:5,1` (API) and `throttle:5,1` (web). An attacker can rotate IPs and create thousands of fake requests, filling the database and admin dashboard.
- **Impact:** Admin dashboard becomes unusable. Real requests are lost in noise.
- **Fix:** Add IP-based CAPTCHA (Turnstile) on the request endpoint. Lower the rate limit to `throttle:3,1` per IP + implement proof-of-work or phone verification for guest requests.

### 2.2 Email Harvesting via Tracking (LOW)

- **Severity:** LOW | **CVSS:** 3.1 | **CWE:** CWE-204
- **Location:** `app/Http/Controllers/Api/RequestTrackingController.php:15-73`
- **Issue:** The tracking API returns the `course_title` and `payment_proof_status` for any valid tracking code. While 16-character codes are hard to guess, there is no rate limiting on failed code attempts beyond the generic endpoint throttle.
- **Impact:** If the tracking code space is ever reduced or a bug weakens randomness, attackers could enumerate requests and learn which emails have active courses.
- **Fix:** Add a delay (exponential backoff) on failed tracking code lookups.

---

## 3. Approval Workflow Abuse

### 3.1 Rejection Note Injection (MEDIUM)

- **Severity:** MEDIUM | **CVSS:** 5.4 | **CWE:** CWE-79
- **Location:** `app/Actions/CourseRequests/RejectCourseRequestAction.php:77-84`
- **Issue:** The `public_rejection_note` is derived from the admin's `rejection_reason` via `strip_tags()`. A malicious admin (compromised account) can inject social-engineering text or Unicode homoglyphs that appear official.
- **Impact:** Students trust the rejection note as official communication. Could be used for phishing (e.g., "Please resubmit payment to this alternate account").
- **Fix:** Apply a whitelist of allowed characters for rejection notes. Do not allow URLs or email addresses in public notes.

### 3.2 Admin Self-Approval Risk (LOW)

- **Severity:** LOW | **CVSS:** 3.1 | **CWE:** CWE-862
- **Location:** `app/Policies/CourseRequestPolicy.php:20-23`
- **Issue:** An admin with `FINANCE_MANAGER` role can approve any payment proof, including their own course request (if they created one).
- **Impact:** Insider threat: an employee could enroll in courses for free.
- **Fix:** Add a policy check preventing approval of requests where `courseRequest->student_email === $user->email`.

---

## 4. Telegram Access Abuse

### 4.1 Revocation Bypass via Concurrent State Change (LOW)

- **Severity:** LOW | **CVSS:** 3.1 | **CWE:** CWE-362
- **Location:** `app/Http/Controllers/Admin/Web/TelegramAccessActionController.php:63-108`
- **Issue:** The revoke action checks `if ($lockedGrant->status === TelegramAccessGrantStatus::REVOKED)` before revoking. If two admins attempt to revoke simultaneously, both pass the check, both update to REVOKED. No harm, but redundant audit logs are created.
- **Impact:** Duplicate audit entries. No security impact.
- **Fix:** Acceptable risk; idempotent operation.

### 4.2 Telegram URL Social Engineering (LOW)

- **Severity:** LOW | **CVSS:** 3.1 | **CWE:** CWE-918
- **Location:** `app/Support/Security/UrlHelper.php:84-101`
- **Issue:** `safeTelegramUrl` only validates the host. A malicious admin could set `telegram_url` to `https://t.me/attacker_phishing_channel` and students would see it as legitimate.
- **Impact:** Phishing: students join an attacker-controlled Telegram channel thinking it's the course.
- **Fix:** Validate the Telegram URL path against a regex for course-specific channels (e.g., `/cwt_` prefix).

---

## 5. Financial Logic Abuse

### 5.1 Price Manipulation via Race (LOW)

- **Severity:** LOW | **CVSS:** 3.1 | **CWE:** CWE-362
- **Location:** `app/Actions/Courses/UpdateCourseAction.php`
- **Issue:** If an admin changes a course price while a student is submitting a payment proof for the old price, the amount check during approval will fail.
- **Impact:** Student frustration, support tickets. No direct financial loss because the admin must manually approve.
- **Fix:** Lock the course price at request creation time by storing `course_price_iqd` on `course_requests` table.

### 5.2 Free Course Registration (LOW)

- **Severity:** LOW | **CVSS:** 3.1 | **CWE:** CWE-840
- **Location:** `app/Http/Controllers/Api/CourseRequestController.php`
- **Issue:** The API endpoint for creating a course request does not require a payment proof. A student can create a request, receive a tracking code, and repeatedly check status without ever paying.
- **Impact:** No direct loss, but pollutes the database and wastes admin review time if the student later submits a proof.
- **Fix:** This is by design for a manual-payment workflow. Consider requiring a minimum deposit or automated payment for instant enrollment.

---

## 6. Audit Log Abuse

### 6.1 Audit Log Flooding (MEDIUM)

- **Severity:** MEDIUM | **CVSS:** 4.3 | **CWE:** CWE-770
- **Location:** `app/Services/Audit/AuditLogger.php`
- **Issue:** Every login attempt (even failed) creates an audit log row. A distributed brute-force attack could generate millions of audit rows, exhausting disk space.
- **Impact:** Database bloat, slow queries, potential disk exhaustion.
- **Fix:** Implement sampling for failed login audit logs (e.g., log every 10th failure from the same IP), or move failed login audits to a separate, shorter-retention table.

### 6.2 Audit Log Tampering by DB Admin (HIGH)

- **Severity:** HIGH | **CVSS:** 6.5 | **CWE:** CWE-778
- **Location:** `app/Models/AuditLog.php`
- **Issue:** There is no cryptographic integrity protection (hash chain, Merkle tree, or HMAC) on audit log rows. Anyone with database access can modify or delete history.
- **Impact:** Compliance violations, undetectable insider fraud.
- **Fix:** Add an `integrity_hash` column computed as `HMAC_SHA256(serialized_row, secret_key)` on insert. Verify hash on read. Use append-only storage (WORM) for production.

---

*End of BUSINESS_LOGIC_ABUSE.md*
