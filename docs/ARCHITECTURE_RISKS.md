# ARCHITECTURE_RISKS.md

**Cwt Academy — Architecture, Infrastructure & Design Risk Assessment**

---

## 1. Monolithic Design Risks

### 1.1 Shared Database for All Concerns

- **Risk:** All modules (auth, courses, payments, audit logs) share a single MySQL database.
- **Impact:** A slow query or lock contention in the course catalog affects payment proof processing. A compromised payment module exposes audit logs.
- **Mitigation:** Consider read replicas for public course listings. Isolate audit logs to a separate schema or append-only storage (e.g., ClickHouse, S3 with Athena).

### 1.2 No Service Boundaries

- **Risk:** Controllers directly call Models and Actions. No DDD boundaries or event-driven decoupling.
- **Impact:** A bug in `CourseService` can cascade to the dashboard, API, and admin panel simultaneously.
- **Mitigation:** Introduce domain events (e.g., `CourseRequestApproved`) and queue workers for side effects (cache busting, audit logging, Telegram notifications).

---

## 2. Cache Architecture Risks

### 2.1 Cache Flooding via Search Filters (HIGH)

- **Risk:** `CourseService::listActive` creates cache keys using `md5(serialize($filters) . ':' . $perPage . ':' . $page)`. An attacker can submit many unique filter combinations (long search strings, varying page numbers) to fill the cache store.
- **Impact:** Memory exhaustion on Redis/Memcached, or disk space exhaustion on database/file cache. Cache evictions of legitimate data.
- **Mitigation:**
  - Limit search string length (e.g., 100 chars).
  - Normalize/sanitize `$filters` before serialization (strip empty values, sort keys).
  - Add a cache-key prefix whitelist; reject unknown filter keys.
  - Use cache tags (if supported) or TTL-based expiration with a max cache size.

### 2.2 Stale Cache on Course Creation (MEDIUM)

- **Risk:** `CourseController::store` does NOT call `flushListCache()`. New courses may not appear in listings for up to 5 minutes (the cache TTL).
- **Impact:** Students cannot see newly published courses immediately. Admins may think creation failed.
- **Mitigation:** Add `app(CourseService::class)->flushListCache()` in the store method.

---

## 3. Session & Authentication Architecture

### 3.1 Dual Auth Stack Complexity

- **Risk:** The application maintains both session-based (web) and token-based (Sanctum API) authentication stacks simultaneously.
- **Impact:** Session fixation in the web stack could affect API tokens if not properly isolated. Logout on web revokes ALL Sanctum tokens (`$user->tokens()->delete()`), which may unexpectedly log out mobile apps or SPA sessions.
- **Mitigation:** Clearly document token lifecycle. Consider separating web admin sessions from API tokens by token name/ability scoping.

### 3.2 No Multi-Factor Authentication (MFA)

- **Risk:** No TOTP, WebAuthn, or email OTP for admin accounts.
- **Impact:** A single leaked password grants full admin access.
- **Mitigation:** Integrate `pragmarx/google2fa-laravel` or similar for admin MFA enforcement.

---

## 4. File Storage Architecture

### 4.1 Local Disk for Payment Proofs (MEDIUM)

- **Risk:** Payment proofs are stored on the local filesystem (`storage/app/payment_proofs`).
- **Impact:**
  - Scaling requires shared storage (NFS, EFS) or migration to object storage.
  - No encryption at rest.
  - Backups must include `storage/` which mixes public assets with sensitive files.
- **Mitigation:** Migrate to S3/MinIO with server-side encryption. Store only the object key in the database.

### 4.2 Missing Virus Scanning

- **Risk:** Uploaded payment proofs are validated by magic bytes and MIME type but never scanned for malware.
- **Impact:** A malicious PDF with embedded JavaScript or an exploit for a PDF renderer could be stored and later downloaded by admins.
- **Mitigation:** Integrate ClamAV (`xenolope/sanitiser` or `sokolnikov/clamav`) in the upload pipeline.

---

## 5. Database Architecture Risks

### 5.1 Missing DB-Level Constraints (MEDIUM)

- **Risk:** Several application-level invariants are not enforced by the database schema:
  - No `CHECK` constraint on `course_requests.status` values.
  - No `CHECK` constraint on `payment_proofs.status` values.
  - No unique constraint on `(course_request_id, status)` for payment proofs to prevent multiple pending proofs.
  - No `NOT NULL` on `course_requests.student_name` / `student_email` (despite validation rules).
- **Impact:** A bug or manual DB update can violate business rules, causing data corruption.
- **Mitigation:** Add enum check constraints and additional unique indexes.

### 5.2 Foreign Key Cascades (LOW)

- **Risk:** `course_requests` has `cascadeOnDelete` on `course_id`. Deleting a course deletes all associated requests, payment proofs, and Telegram access grants.
- **Impact:** Accidental course deletion = irreversible data loss for student records.
- **Mitigation:** Change `cascadeOnDelete` to `restrictOnDelete()` for critical parent tables, forcing explicit archival before deletion.

### 5.3 `learning_points` Missing Column (LOW)

- **Risk:** `course-detail.blade.php` references `$course->learning_points` as an array, but the `courses` table has no such column.
- **Impact:** Runtime errors or empty sections in the course detail page.
- **Mitigation:** Add a `learning_points` JSON column to `courses`, or remove the reference.

---

## 6. Queue & Background Job Risks

### 6.1 Synchronous Audit Logging

- **Risk:** `AuditLogger::log()` performs a synchronous `INSERT` on every significant action.
- **Impact:** Under high load, audit logging becomes a bottleneck. If the DB is slow, user-facing requests slow down.
- **Mitigation:** Queue audit log insertions using Laravel queues and a dedicated `AuditLogJob`.

### 6.2 No Dead Letter Queue

- **Risk:** If queue workers fail (e.g., Redis down), jobs are lost if using the `database` driver without retry configuration.
- **Impact:** Audit logs or notifications may be silently dropped.
- **Mitigation:** Configure `retry_after` and `tries` for all queue jobs. Monitor failed jobs table.

---

## 7. Observability Gaps

### 7.1 Missing Structured Logging

- **Risk:** Application logs use Laravel's default channels. There is no structured JSON logging for SIEM ingestion.
- **Impact:** Incident response is slow because grepping plain-text logs is inefficient.
- **Mitigation:** Add a `json` log channel and ensure all security events (login, logout, failed CAPTCHA, rate-limit hits) log structured data.

### 7.2 No Application Performance Monitoring (APM)

- **Risk:** Slow query logging exists but no APM tool (e.g., Sentry, New Relic, Datadog) is integrated.
- **Impact:** Performance degradation and security anomalies (e.g., sudden spike in login attempts) are not correlated with code paths.
- **Mitigation:** Integrate Sentry or equivalent for error tracking and performance monitoring.

---

## 8. Deployment & Infrastructure Risks

### 8.1 Single-Node Database

- **Risk:** `docker-compose.yml` defines a single MySQL container with no replication or failover.
- **Impact:** Container failure = complete data unavailability.
- **Mitigation:** Use managed database service (RDS, Cloud SQL) or configure MySQL Group Replication.

### 8.2 No Readiness/Liveness Probe for App

- **Risk:** Only the MySQL container has a Docker `healthcheck`. The PHP application container is absent from `docker-compose.yml` entirely.
- **Impact:** Kubernetes or Docker Swarm cannot auto-heal the application.
- **Mitigation:** Add a PHP-FPM/Nginx container to `docker-compose.yml` with a health check hitting `/up`.

---

*End of ARCHITECTURE_RISKS.md*
