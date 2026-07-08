# Cwt Academy — Brutal Production Audit

**Date:** 2026-05-29  
**Auditor:** Senior Staff Engineer (External)  
**Scope:** Full-stack Laravel 13 + React 19 + Docker infrastructure  
**Premise:** This audit assumes a sudden spike to 50,000 registered Kurdish users, 5,000 concurrent, with a motivated adversary targeting the platform.

---

# PART 1 — INFRASTRUCTURE BOTTLENECKS (50K Users / 5K Concurrent)

## Executive Verdict

This stack will **not survive** 5,000 concurrent users on the current Docker Compose topology. The first failure will occur within **minutes**, not hours. The architecture is designed for a single-node, low-traffic deployment and lacks horizontal scaling primitives.

---

## 1.1 Failure Order Timeline

| Minute | Failing Component | Trigger | User Impact |
|--------|-------------------|---------|-------------|
| **0-2** | **PHP-FPM Worker Exhaustion** | `pm.max_children = 50` @ `docker/php/www.conf:11` | New requests queue. Response latency jumps from ~80ms to 5-30s. Nginx returns 502 Bad Gateway. |
| **2-5** | **MySQL Connection Saturation** | Laravel opens/closes connections per request. Default `max_connections = 151` in MySQL 8.0. | DB rejects connections. Laravel throws `QueryException`. Application white-screens. |
| **5-10** | **Redis Hot Key Contention** | `Cache::increment('courses.list:version')` is a single key hit on every admin mutation. | Redis CPU spikes to 100% on one core. Cache writes block. Admin panel becomes unresponsive. |
| **10-15** | **Queue Worker Backlog** | `app/Jobs/` is **EMPTY**. Audit logging, cache busting, and notifications are all synchronous. | DB write queue grows unbounded. InnoDB buffer pool pressure. Transaction locks accumulate. |
| **15-30** | **Nginx Upstream Timeouts** | `fastcgi_read_timeout 60s` @ `docker/nginx/nginx.conf:78` | Even if PHP-FPM eventually processes, nginx has already 502'd. Users see broken pages. |
| **30-60** | **Disk I/O Saturation** | Local storage for payment proofs + MySQL data + Redis AOF on same volume. | Write latency spikes. Docker healthchecks fail. Kubernetes (if used) starts pod cycling. |
| **60+** | **MySQL Deadlocks / Lock Waits** | `lockForUpdate()` on `CourseRequest` + `PaymentProof` + `TelegramAccessGrant` in multiple actions. | Admin approval workflows timeout. State machine transitions fail. Financial data inconsistency risk. |

### Estimated Traffic Ceilings (Current Topology)

| Metric | Ceiling | Bottleneck |
|--------|---------|------------|
| Concurrent web users | **~40-50** | PHP-FPM `max_children = 50` |
| Requests/second (sustained) | **~200-300 RPS** | Single nginx worker + no upstream keepalive |
| DB read throughput | **~500 QPS** | Single MySQL container, no read replica |
| Admin actions/minute | **~20-30** | Synchronous audit logging + row locking |
| Payment proof uploads/minute | **~10-15** | File I/O on local disk + synchronous DB writes |

---

## 1.2 Deep Dive by Component

### PHP-FPM Worker Exhaustion

```ini
; docker/php/www.conf
pm = dynamic
pm.max_children = 50           ; CATASTROPHICALLY LOW for 5K concurrent
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500         ; Workers restart every 500 req = churn
```

**The Math:**
- 5,000 concurrent users × average request duration 150ms = **750 workers needed** to serve without queuing.
- With 50 workers, requests queue. At 100 req/s, the queue grows by 50 requests/second.
- Memory per Laravel worker: ~60-120MB (with Eloquent models, blade compilation, service container).
- 50 workers × 80MB = 4GB RAM. The server likely has 8-16GB, so RAM isn't the limit — **process count is**.
- `request_terminate_timeout = 60s` means a single slow DB query blocks a worker for a full minute.

**Fix:**
```ini
pm = ondemand
pm.max_children = 200
pm.process_idle_timeout = 10s
pm.max_requests = 1000
```
And horizontally scale to 3-6 PHP-FPM nodes behind a load balancer.

### MySQL Connection Pooling

Laravel's default MySQL configuration uses **no persistent connections**. Each HTTP request opens a connection, runs queries, closes it.

- `DB::transaction()` + `lockForUpdate()` holds connections longer.
- Admin approval action locks `CourseRequest`, `PaymentProof`, and creates `TelegramAccessGrant` — 3+ tables locked per approval.
- `AuditLog::create()` performs a synchronous INSERT on **every** mutation.
- MySQL default `max_connections = 151`. With 50 PHP-FPM workers, that's fine. With 200 workers + queue workers + scheduler, you hit the limit.

**Fix:**
1. Enable `sticky` connections or ProxySQL in front of MySQL.
2. Increase `max_connections` to 500+.
3. Move audit logging to **async queue** (currently impossible — `app/Jobs/` is empty).
4. Use **read replicas** for `Course::active()` listings, `Category` lookups, and audit log SELECTs.

### Redis Bottlenecks

All three critical subsystems share one Redis instance:
- **Cache**: `courses.list:version` is a hot key.
- **Session**: 5,000 concurrent sessions = 5,000 Redis keys with TTL.
- **Queue**: Failed jobs, throttling counters, brute-force detection keys.

**Hot Key Analysis:**
```php
// app/Services/Courses/CourseService.php:77
Cache::increment('courses.list:version');
```
Every course create/update/archive increments this ONE key. At 5K users with even modest admin activity, this key receives 10-100 increments/second. Redis is single-threaded. This key becomes a CPU bottleneck.

**Session Storage:**
- `SESSION_ENCRYPT=true` means every session value is encrypted/decrypted in PHP, then stored in Redis.
- Session size with Laravel defaults: ~2-5KB per user.
- 5,000 concurrent × 5KB = 25MB of active session data. Redis handles this fine, but it's not segmented.

**Fix:**
1. Separate Redis instances: `cache`, `session`, `queue` on different nodes or DBs.
2. Use Redis Cluster for cache (sharding).
3. Replace cache version key with **cache tags** or **time-based invalidation**.
4. For sessions, consider `database` driver with connection pooling if Redis becomes saturated.

### Queue Backlog Risks

`app/Jobs/` contains **zero files**.

This means:
- Every audit log is a **synchronous DB write**.
- Every cache bust is **synchronous**.
- Every email (verification) is sent **synchronously** during registration.
- There are no background workers for heavy operations.

**The cascade:**
1. User registers → email sent synchronously → 200-500ms SMTP delay → PHP-FPM worker blocked.
2. Admin approves request → audit log INSERT + cache increment + telegram grant INSERT → all in the HTTP request cycle.
3. At 5K concurrent, the DB becomes a synchronous write queue.

**Fix:**
1. Create `app/Jobs/AuditLogJob.php`, `app/Jobs/CacheBustJob.php`, `app/Jobs/SendVerificationEmail.php`.
2. Deploy Laravel Horizon (already referenced in `deploy.yml` but missing from `composer.json` — another bug).
3. Run 10-20 queue workers across multiple nodes.
4. Use `afterResponse()` dispatch for non-critical logs.

### Cache Invalidation Storms

`ResponseCacheMiddleware` caches full HTML responses in Redis:
```php
// app/Http/Middleware/ResponseCacheMiddleware.php:46
Cache::put($key, $response->getContent(), $ttl);
```

**Risk:** A course update triggers `Cache::forget('course.slug:' . $slug)` and `Cache::increment('courses.list:version')`. The old `course.slug:*` entries are forgotten, but:
- The listing cache uses version-bump invalidation — old keys become orphaned in Redis until TTL (5 minutes).
- If an admin updates 10 courses in rapid succession, 10 version increments occur, but the old paginated listing keys (which include the version in their hash) are now stale and will be re-fetched from DB under high load.

**Cache Stampede:**
- 5,000 users hit `/courses` simultaneously after cache invalidation.
- All 5,000 miss cache simultaneously.
- All 5,000 hit MySQL with `Course::active()->with(['category', 'instructor'])`.
- MySQL is overwhelmed. PHP-FPM workers block on DB. Cascade failure.

**Fix:**
1. Use `Cache::remember()` with a short lock (e.g., `Cache::lock('courses.rebuild', 10)`).
2. Implement **cache warming** after invalidation (`CacheWarmCommand` exists but is basic).
3. Increase default TTL from 300s to 3600s.
4. Use ** Edge/CDN caching** for public pages (Cloudflare).

### Horizontal Scaling Blockers

| Blocker | Severity | Why |
|---------|----------|-----|
| Local file storage | **Critical** | Payment proofs on local disk. Multi-node deployment requires shared storage (NFS/EFS) or object storage migration. |
| Single Redis | **Critical** | Session + cache + queue on one node. No failover. Redis dies = all users logged out + cache lost + queues halt. |
| Single MySQL | **High** | No read replica in production env. Write and read contention on same instance. |
| No load balancer config | **High** | `docker-compose.yml` exposes port 80 on one nginx container. No health-based routing. |
| `APP_KEY` dependency | **Medium** | Encrypted columns (student PII) require identical `APP_KEY` across all nodes. Key rotation is nearly impossible without data migration. |

### File Storage Limitations

- `storage/app/private` for payment proofs.
- `payment_proofs/` directory grows linearly with uploads.
- 50K users × average 2 uploads × 500KB = **50GB** of proof images.
- Local backups require copying this volume. No lifecycle rules (e.g., archive old proofs to cold storage).
- `Storage::disk('local')->exists($path)` is a disk I/O call on every admin download.

**Fix:**
1. Migrate to S3 / Cloudflare R2 / MinIO immediately.
2. Enable server-side encryption (SSE-S3 or SSE-KMS).
3. Use pre-signed URLs for downloads instead of proxying through PHP.
4. Implement lifecycle rules: move proofs >1 year old to Glacier.

### Nginx Tuning Gaps

```nginx
# docker/nginx/nginx.conf
upstream php-fpm {
    server php:9000 max_fails=3 fail_timeout=30s;
    # MISSING: keepalive 32;
}
```

- No keepalive connections to PHP-FPM. Every request opens a new FastCGI TCP connection.
- `worker_connections 4096` is fine, but with `multi_accept on` and no `limit_req_zone`, there's no nginx-level rate limiting.
- No `limit_conn` for concurrent connections per IP.
- `client_max_body_size 10M` — an attacker can POST 10MB repeatedly to exhaust bandwidth.

**Fix:**
```nginx
upstream php-fpm {
    server php:9000 max_fails=3 fail_timeout=30s;
    keepalive 64;  # Reuse FastCGI connections
}

limit_req_zone $binary_remote_addr zone=public:10m rate=10r/s;
limit_req_zone $binary_remote_addr zone=api:10m rate=30r/s;
```

---

## 1.3 Emergency Mitigation Plan (Deploy This Today)

1. **Double PHP-FPM workers** to 100 minimum (`pm.max_children = 100`).
2. **Add MySQL `max_connections = 300`** in `database/docker/init.sql`.
3. **Disable `ResponseCacheMiddleware`** for `/courses/*` and `/` to prevent stampede:
   ```php
   // In ResponseCacheMiddleware::shouldSkip()
   return true; // Emergency bypass
   ```
4. **Comment out `AuditLogger::log()` calls** in hot paths (course listing, tracking lookup) to reduce DB write pressure.
5. **Scale nginx + PHP horizontally**: Run 2-3 copies of the `php` service behind an external load balancer (e.g., AWS ALB).
6. **Add Redis `maxmemory-policy allkeys-lru`** to prevent OOM kills under memory pressure.
7. **Enable Cloudflare** in front of nginx for DDoS protection and static asset caching.

---

## 1.4 Long-term Cloud-Native Redesign Strategy

| Layer | Current | Target |
|-------|---------|--------|
| Compute | Single PHP-FPM container (50 workers) | Laravel Octane (Swoole/RoadRunner) + 3-6 replicas |
| Web Server | Single nginx | AWS ALB / Cloudflare + nginx sidecar |
| Database | Single MySQL 8.0 | AWS RDS MySQL Multi-AZ + 2 read replicas |
| Cache/Session/Queue | Single Redis | Redis Cluster (6 nodes) or ElastiCache Redis Cluster |
| Object Storage | Local disk | S3 / R2 with pre-signed URLs |
| Queue Workers | Single container, 1 process | Laravel Horizon on ECS/EKS with 20 workers |
| CDN | None | Cloudflare with page rules for `/courses/*` |
| CI/CD | SSH `git pull` | GitHub Actions → ECR → ECS Rolling Deploy |
| Observability | Laravel logs | Datadog / New Relic + Sentry + structured JSON logs |
| Secrets | `.env` file | AWS Secrets Manager + Laravel Envoyer |

**Estimated Cost for 50K Users:**
- AWS ALB: ~$25/month
- ECS/Fargate (3 PHP nodes): ~$300/month
- RDS MySQL (db.r6g.xlarge Multi-AZ): ~$400/month
- ElastiCache Redis (cache.r6g.large × 2): ~$200/month
- S3 Standard (50GB): ~$2/month
- Cloudflare Pro: ~$20/month
- **Total: ~$950-1,200/month** — affordable for a monetized platform.

---

# PART 2 — RED-TEAM SECURITY ATTACK SIMULATION

**Attacker Profile:** Advanced persistent threat (APT) with specific interest in Kurdish educational platforms. Goals: financial fraud, data exfiltration, platform degradation, Telegram access abuse.

---

## 2.1 Attack Rankings

### Easiest Attacks (Script Kiddie Level)

| Rank | Attack | Difficulty | Prerequisites |
|------|--------|------------|---------------|
| 1 | **Math CAPTCHA Bypass** | Trivial | None. Read the form, compute `2 + 3`, submit. |
| 2 | **Cache Flooding** | Easy | `curl` + random search strings. Fills Redis with junk. |
| 3 | **Tracking Code Brute Force (Format Oracle)** | Easy | Observe that valid codes are 16 chars `[A-Z0-9]`. Use regex to filter. |
| 4 | **Rate Limit Bypass via X-Forwarded-For** | Easy | Requires `TRUSTED_PROXIES=*` misconfiguration. Very common. |
| 5 | **Payment Proof 0-Day Upload (Polyglot)** | Medium | Craft a file that passes magic bytes + `getimagesize()` but contains embedded JS/PDF exploit. |

### Most Financially Damaging Attacks

| Rank | Attack | Potential Damage | Why |
|------|--------|------------------|-----|
| 1 | **Duplicate Payment Fraud via Broken `unique` on Encrypted Column** | **$10K-50K/month** | `transaction_reference` is encrypted but has a `unique` validation rule. Same plaintext encrypts to different ciphertext. Attackers reuse the same fake receipt number indefinitely. |
| 2 | **Admin Account Takeover via Credential Stuffing** | **Platform control** | No MFA. Math CAPTCHA is weak. Rate limiting is per-IP only. Use proxy rotation. |
| 3 | **Course Request Spam / Inventory Denial** | **Revenue loss** | Create 10,000 fake course requests. Admins drown in manual review. Real customers can't get through. |
| 4 | **Payment Proof Upload DDoS** | **$500-2K infrastructure** | Upload 10MB files repeatedly. Fills disk. No automatic cleanup of orphaned files. |

### Most Realistic Attacks in Production

| Rank | Attack | Likelihood |
|------|--------|------------|
| 1 | **Tracking Code Enumeration + GDPR Data Leak** | **High** | The tracking endpoint returns `course_title`, `status`, and `payment_proof_status` without auth. Enumerate codes to map customer base and course popularity. |
| 2 | **Audit Log Injection via User-Agent** | **High** | `AuditLogger::log` stores raw `Request::userAgent()` without sanitization. If admin audit log viewer renders HTML, stored XSS is possible. |
| 3 | **Self-Approval via Race Condition** | **Medium** | Two admins click "Approve" on the same request simultaneously. `lockForUpdate()` helps, but `ApproveCourseRequestAction` and `PaymentProofController::approve` have slightly different logic paths. |
| 4 | **Session Hijacking via Missing `secure` Cookie Check** | **Medium** | `.env.example` has `SESSION_SECURE_COOKIE=false` by default. Operators may forget to change it. Cookies transmitted over HTTP on public WiFi = trivial theft. |

---

## 2.2 Critical Exploit Walkthroughs

### EXPLOIT-1: The Encrypted Unique Constraint Bypass (CRITICAL)

**File:** `app/Http/Controllers/Api/RequestTrackingController.php:101`  
**File:** `app/Models/PaymentProof.php` (casts)  
**File:** `database/migrations/2026_05_26_000004_scope_transaction_reference_unique.php`

**The Bug:**
```php
// PaymentProof model
casts: [
    'transaction_reference' => 'encrypted',  // Laravel encrypts with random IV
]

// RequestTrackingController validation
'transaction_reference' => ['nullable', 'string', 'max:120', 'unique:payment_proofs,transaction_reference'],
```

**Why it fails:**
1. Laravel's `encrypted` cast uses AES-256-GCM with a **random nonce/IV** on every encryption.
2. The plaintext `"REF-12345"` encrypts to ciphertext `A` on save, and `B` on the next save (different IV).
3. The validation rule `unique:payment_proofs,transaction_reference` generates:
   ```sql
   SELECT * FROM payment_proofs WHERE transaction_reference = 'REF-12345'
   ```
4. The database column contains ciphertext (e.g., `eyJpdiI6...`), not plaintext. The WHERE clause never matches.
5. The database-level `UNIQUE` constraint on `transaction_reference` also only triggers if two rows have the **exact same ciphertext**, which is statistically impossible with random IVs.

**Exploit:**
```bash
# Submit the same fake receipt 1,000 times
for i in {1..1000}; do
curl -X POST "https://cwtacademy.com/api/v1/course-requests/ABCD1234/payment-proof" \
  -F "amount_iqd=50000" \
  -F "transaction_reference=FIB-999999" \
  -F "proof_file=@fake.jpg"
done
```

**Result:** All 1,000 requests pass validation. All 1,000 are stored. The unique constraint is a fiction. Fraudsters can reuse the same receipt number indefinitely.

**Fix:**
```php
// 1. Remove 'encrypted' cast from transaction_reference
// 2. Hash it for storage if privacy is needed:
'transaction_reference' => 'hashed', // or just store plaintext

// 3. If privacy is mandatory, create a separate hash column:
$table->string('transaction_reference_hash', 64)->nullable()->index();
// Store SHA-256 hash, enforce unique on the hash column.
```

---

### EXPLOIT-2: Admin Account Takeover via Credential Stuffing + CAPTCHA Bypass

**File:** `app/Http/Controllers/Admin/AuthController.php:44-55`

**The Bug:** Admin login uses `MathCaptchaService`. Math CAPTCHAs are session-based and trivial to solve programmatically.

**Exploit Steps:**
1. Attacker requests `/admin/login` page. Server generates math problem (e.g., `5 + 3`) and stores answer in session.
2. Attacker's bot reads the HTML form, extracts the math expression, computes `8`.
3. Attacker runs credential stuffing list (10,000 email/password pairs) with `captcha_answer=8`.
4. Rate limiting is per-IP (`10/min`). Attacker uses 100 proxy IPs = 1,000 attempts/minute.
5. No account lockout. No MFA. Eventually, a reused password hits.

**Fix:**
1. Replace math CAPTCHA with **Cloudflare Turnstile** (config exists but defaults to `math`).
2. Implement account lockout: 5 failed attempts = 15-minute lockout.
3. Enforce **MFA for all admin accounts** using TOTP (e.g., `pragmarx/google2fa`).
4. Add **webhook alerts** on failed admin login bursts.

---

### EXPLOIT-3: Rate Limit Bypass via X-Forwarded-For Spoofing

**File:** `bootstrap/app.php:36-43`

**The Bug:**
```php
$middleware->trustProxies(
    at: array_filter(explode(',', (string) env('TRUSTED_PROXIES', ''))),
    headers: Request::HEADER_X_FORWARDED_FOR | ...
);
```

**Scenario A (Too Restrictive):**
- Operator forgets to set `TRUSTED_PROXIES`.
- App is behind Cloudflare.
- Laravel sees Cloudflare's IP for all users.
- Rate limiter counts all of Kurdistan as one IP.
- Legitimate users get 429 after 5 requests.

**Scenario B (Too Permissive):**
- Operator sets `TRUSTED_PROXIES=*` to fix Scenario A.
- Attacker sends `X-Forwarded-For: 1.2.3.4`.
- Laravel rotates `$request->ip()` on every request.
- Rate limits bypassed entirely.

**Fix:**
```php
// In bootstrap/app.php, validate proxy list
$proxies = array_filter(explode(',', (string) env('TRUSTED_PROXIES', '')));
if (empty($proxies) && app()->environment('production')) {
    throw new \RuntimeException('TRUSTED_PROXIES must be configured in production');
}
$middleware->trustProxies(at: $proxies, ...);
```

---

### EXPLOIT-4: Cache Flooding + Memory Exhaustion

**File:** `app/Services/Courses/CourseService.php:23-24`

**The Bug:**
```php
$hashInput = serialize($normalized) . ':' . $perPage . ':' . $page;
$cacheKey = 'courses.list:v' . $this->listVersion() . ':' . md5($hashInput);
```

**Exploit:**
- Search parameter is limited to 100 chars, but an attacker can generate:
  - `search=a`, `search=aa`, `search=aaa` ... up to 100 chars = ~100 unique keys.
  - Combine with `page=1..1000` = 100,000 unique keys.
  - Each key stores a `LengthAwarePaginator` object (serialized). ~50-200KB per key.
  - Total Redis memory consumed: **5-20GB** of junk.
  - Redis OOM = cache eviction storm = all legitimate cache data lost.

**Fix:**
```php
// 1. Limit page numbers
$page = min((int) request('page', 1), 100);

// 2. Normalize search: lowercase, trim, limit to dictionary words
$search = strtolower(trim(substr($normalized['search'] ?? '', 0, 100)));

// 3. Add cache key cardinality limit
$cacheKeyPrefix = 'courses.list:v' . $this->listVersion();
if (Redis::connection()->eval("return redis.call('dbsize')") > 1_000_000) {
    // Purge old listing caches
}
```

---

### EXPLOIT-5: XSS via Public Rejection Note

**File:** `app/Actions/CourseRequests/RejectCourseRequestAction.php:79-85`

**The Bug:**
```php
private function sanitizePublicNote(string $reason): string
{
    $note = strip_tags($reason);
    return substr($note, 0, 500);
}
```

**Why `strip_tags` is insufficient:**
- `strip_tags('<img src=x onerror=alert(1)>')` returns `""` — safe.
- But `strip_tags('<<script>alert(1)</script>')` behavior is unpredictable.
- More importantly, if the admin enters `" onclick="alert(1)` into a field that later renders in an HTML attribute, `strip_tags` doesn't help.
- The note is stored in the DB and presumably displayed in the tracking view. If Blade uses `{{ $note }}` (escaped), it's safe. If it uses `{!! $note !!}` (raw), it's XSS.

**Fix:**
```php
// Use HTMLPurifier or strict text-only
return htmlspecialchars(strip_tags($reason), ENT_QUOTES, 'UTF-8');
```

---

### EXPLOIT-6: IDOR / Unauthorized Tracking Data Access

**File:** `app/Http/Controllers/Api/RequestTrackingController.php:15-73`

**The Bug:** The tracking endpoint requires NO authentication. It returns:
- `course_title`
- `payment_proof_status`
- `telegram_access` status (if approved)
- `public_rejection_note`

**Impact:** An attacker who brute-forces or guesses a tracking code can:
1. Confirm a specific person bought a course (if they know email → code mapping is hard, but social engineering helps).
2. Enumerate all active course requests by probing codes.
3. Determine course popularity by counting valid codes.
4. If a code is leaked (e.g., via screenshot), anyone can view the full payment status.

**Fix:**
```php
// Require email verification for tracking
$courseRequest = CourseRequest::where('public_tracking_code', $trackingCode)
    ->where('student_email', hash('sha256', $request->input('email')))
    ->first();
```
Or add a secondary PIN to the tracking flow.

---

## 2.3 Risk Scores & Hardened Fixes

| Vulnerability | Risk Score (1-10) | Hardened Fix |
|---------------|-------------------|--------------|
| Encrypted unique constraint bypass | **10** | Remove `encrypted` cast on `transaction_reference`. Use hash column for uniqueness. |
| Math CAPTCHA bypass | **8** | Force Turnstile in production. Remove math CAPTCHA option for admin login. |
| Rate limit IP spoofing | **7** | Require explicit `TRUSTED_PROXIES` in prod. Block empty config. |
| Cache flooding | **6** | Limit page numbers. Add Redis memory limit + LRU eviction. |
| Tracking code enumeration | **6** | Add email/PIN gate. Rate limit by tracking code prefix. |
| XSS via rejection note | **5** | Use `htmlspecialchars()`. Audit Blade templates for raw output. |
| Audit log injection | **4** | Sanitize `user_agent` before storage. Render audit logs as text-only. |
| Upload polyglot bypass | **4** | Add ClamAV integration. Limit image dimensions. |
| Session fixation | **3** | Regenerate session ID on role change (if dynamic roles existed). |
| SSRF via Telegram URL | **2** | Validate with `UrlHelper::safeTelegramUrl()` before storage. |

---

## 2.4 Production-Grade Defense Improvements

1. **Web Application Firewall (WAF):** Deploy Cloudflare Pro or AWS WAF. Block common attack patterns (SQLi, XSS, LFI).
2. **CAPTCHA Hardening:** Remove `math` driver entirely. Only allow `turnstile` or `null` (for local dev).
3. **MFA for Admins:** TOTP mandatory for `SUPER_ADMIN` and `FINANCE_MANAGER`.
4. **Network Segmentation:** Admin panel should be behind VPN or IP whitelist (`AllowAdminIP` middleware).
5. **File Upload Sandboxing:** Store uploads in a read-only volume. Scan with ClamAV before marking as available.
6. **Honeytokens:** Plant fake course requests with known tracking codes. Alert if they are accessed from unexpected IPs.
7. **DB Encryption at Rest:** Enable MySQL TDE or AWS RDS encryption. Currently relies on application-level encryption only.

---

# PART 3 — PRODUCTION READINESS (10,000 Paying Users)

## 3.1 Production Launch Checklist

### Infrastructure
- [ ] **Multi-AZ MySQL** (RDS or equivalent) with automated backups
- [ ] **Read replica** configured and `ReadReplicaMiddleware` activated
- [ ] **Redis Cluster** or ElastiCache with Multi-AZ failover
- [ ] **Object storage** (S3/R2) for payment proofs; local disk deprecated
- [ ] **CDN** (Cloudflare) for static assets and public page caching
- [ ] **Load balancer** (ALB/NLB) with health checks targeting `/up`
- [ ] **PHP-FPM tuning** for 100+ workers or migration to Laravel Octane
- [ ] **Queue workers** (Horizon or Supervisor) with ≥10 processes
- [ ] **Log aggregation** (CloudWatch / Datadog / Loki)

### Security
- [ ] `APP_ENV=production`, `APP_DEBUG=false` enforced
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=strict`
- [ ] `FORCE_HTTPS=true`
- [ ] `TRUSTED_PROXIES` set to exact Cloudflare IP ranges
- [ ] Cloudflare Turnstile active on all auth + payment flows
- [ ] MFA enforced for admin roles
- [ ] `ADMIN_DEFAULT_PASSWORD` removed from `.env`
- [ ] `composer audit` passes in CI
- [ ] Penetration test completed by third party

### Observability
- [ ] Sentry DSN configured with PII scrubbing
- [ ] Structured JSON logging for all security events
- [ ] Slow query alerting (>500ms)
- [ ] Error rate alerting (>0.1% of requests)
- [ ] Disk space alerting (>80% usage)
- [ ] Queue depth alerting (>1,000 pending jobs)

### Backups & DR
- [ ] MySQL automated snapshots (daily) + point-in-time recovery
- [ ] `mysqldump` to S3 (hourly for critical tables)
- [ ] Payment proof files replicated to second S3 bucket in different region
- [ ] Documented RTO (Recovery Time Objective): < 1 hour
- [ ] Documented RPO (Recovery Point Objective): < 15 minutes
- [ ] Quarterly DR drill performed

### CI/CD & Deployment
- [ ] Zero-downtime deployment (blue/green or rolling)
- [ ] Database migrations run BEFORE app deployment
- [ ] Automated rollback script (revert container + DB migrate:rollback)
- [ ] Secrets injected at runtime (AWS Secrets Manager), not baked into image
- [ ] Container image scanned for CVEs (Trivy / Snyk)

---

## 3.2 Missing Infrastructure

| Component | Status | Priority |
|-----------|--------|----------|
| Queue jobs (`app/Jobs/`) | **MISSING ENTIRELY** | P0 |
| Laravel Horizon | Referenced in deploy but not in `composer.json` | P0 |
| Read replica (env vars empty) | Config exists, not wired | P1 |
| S3/R2 filesystem disk | Config exists, not default | P1 |
| CDN / `ASSET_URL` | Empty in env | P1 |
| APM (Sentry only partially configured) | DSN usually empty | P1 |
| Log rotation | Logs to stdout only | P2 |
| DB encryption at rest | Application-level only | P2 |
| API documentation (OpenAPI) | None | P3 |

---

## 3.3 Highest Operational Risks

1. **Data Loss:** Single MySQL container in Docker. No replication. One `docker volume rm mysql_data` = total platform destruction.
2. **Financial Fraud:** The encrypted unique constraint bug allows unlimited duplicate payment proofs.
3. **Compliance Violation:** PII (student name, email, phone) is encrypted in DB but stored in audit logs as plaintext arrays (`old_values` / `new_values`). If audit logs are not purged aggressively, you retain PII indefinitely.
4. **Reputation Damage:** 5,000 concurrent users hitting 502 errors will be posted on social media. Kurdish tech community is tight-knit.
5. **Insider Threat:** Any developer with server SSH access can run `php artisan admin:create --role=super_admin` and gain full control. No audit of command-line admin creation.

---

## 3.4 Exact DevOps Architecture Recommendations

```
┌─────────────────────────────────────────────────────────────────┐
│                         Cloudflare Edge                          │
│  (DDoS protection, WAF, static asset cache, page rules)         │
└─────────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────────┐
│                      AWS Application Load Balancer               │
│         (Health checks → /up, SSL termination, sticky)          │
└─────────────────────────────────────────────────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
┌───────▼────────┐  ┌────────▼────────┐  ┌──────▼────────┐
│  PHP-FPM Node 1 │  │  PHP-FPM Node 2  │  │ PHP-FPM Node 3│
│  (Octane/Swoole)│  │  (Octane/Swoole) │  │ (Octane/Swoole)│
└───────┬────────┘  └────────┬────────┘  └──────┬────────┘
        │                     │                     │
        └─────────────────────┼─────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
┌───────▼────────┐  ┌────────▼────────┐  ┌──────▼────────┐
│  Redis Cache   │  │  Redis Session   │  │  Redis Queue  │
│   (Cluster)    │  │   (Cluster)      │  │  (Cluster)    │
└────────────────┘  └──────────────────┘  └───────────────┘
                              │
┌─────────────────────────────────────────────────────────────────┐
│              AWS RDS MySQL 8.0 Multi-AZ                         │
│  ┌──────────────┐              ┌──────────────┐                │
│  │   Writer     │─────────────▶│  Read Rep 1  │                │
│  │  (Primary)   │              │  (SELECT only)│                │
│  └──────────────┘              └──────────────┘                │
│         │                                                      │
│  ┌──────▼──────┐                                              │
│  │ Read Rep 2  │                                              │
│  │ (Analytics) │                                              │
│  └─────────────┘                                              │
└─────────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────────┐
│              S3 / Cloudflare R2 (Object Storage)                │
│         payment_proofs/  │  backups/  │  assets/                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 3.5 Realistic Production Readiness Score

| Category | Score | Notes |
|----------|-------|-------|
| **Code Quality** | 7/10 | Clean structure, good tests, but no queues |
| **Security Posture** | 6/10 | Good CSP, rate limiting, but critical bugs found |
| **Scalability** | 3/10 | Single-node design, will fail under load |
| **Observability** | 4/10 | Health checks exist, no APM, no structured logs |
| **Reliability** | 3/10 | No HA, no DR, single points of failure everywhere |
| **Operational Maturity** | 4/10 | Docker exists, but deployment is SSH-based |
| **Overall** | **4.5/10** | **NOT READY for 10,000 paying users.** |

**Minimum viable target: 7.5/10.**

---

# PART 4 — SENIOR STAFF ENGINEER CODE QUALITY AUDIT

## 4.1 Biggest Architectural Mistakes

### MISTAKE-1: The Synchronous Architecture Lie

The `ARCHITECTURE_BOTTLENECKS.md` document claims *"Non-critical logs dispatched `afterResponse()`"*. This is **false**. `AuditLogger::log()` performs a direct `AuditLog::create()` — a synchronous database INSERT on every login, approval, rejection, and state change.

**Impact at scale:** Every admin click generates a DB write. Under high concurrency, this is a distributed denial-of-service against your own database.

**Root cause:** `app/Jobs/` is completely empty. No background job infrastructure exists.

### MISTAKE-2: Encrypted Columns with Unique Constraints

As detailed in EXPLOIT-1, encrypting a column that needs uniqueness is a fundamental design error. Laravel's `encrypted` cast is incompatible with database-level constraints and application-level `Rule::unique()`.

This suggests the developer understands encryption (good) but doesn't understand how Laravel's encryption works under the hood (dangerous).

### MISTAKE-3: Dual Authentication Stacks Without Isolation

| Stack | Auth Mechanism | Logout Behavior |
|-------|---------------|-----------------|
| Web (Students) | Session (Redis) | `$user->tokens()->delete()` + session invalidate |
| API (Admins) | Sanctum Token | Current token only |

**Problem:** `AuthWebController::logout` deletes **ALL** Sanctum tokens (`$user->tokens()->delete()`). If a student is also an admin (edge case), or if tokens are ever used for student mobile apps, this nukes everything.

**Root cause:** No token scoping by name or ability. No separation between "web session tokens" and "API admin tokens".

### MISTAKE-4: Hot Key Cache Invalidation

The `courses.list:version` key is incremented on every mutation. This is a "clever" solution that works at small scale and fails at large scale.

**Better approach:**
```php
// Use time-bucketed cache keys (no central counter)
$cacheKey = 'courses.list:' . now()->format('YmdH'); // Hourly buckets
// Invalidate by writing to a "latest version" timestamp
$lastUpdated = Cache::get('courses.last_updated', now()->subYear());
```

### MISTAKE-5: CatalogController Bypasses Service Layer

`CatalogController::index()` queries Eloquent directly instead of using `CourseService::listActive()`. This means:
- No caching on the most-hit public page.
- N+1 risk (mitigated by `with()` but still a pattern violation).
- Inconsistent pagination (12 per page vs 15 in service).

This is a "broken window" — one bypass invites more.

---

## 4.2 Technical Debt Ranking

| Rank | Debt Item | Effort | Impact |
|------|-----------|--------|--------|
| 1 | **Empty Jobs directory + sync audit logging** | 2 weeks | Blocks scaling. High DB write pressure. |
| 2 | **Encrypted `transaction_reference`** | 2 days | Allows unlimited payment fraud. |
| 3 | **Local file storage for proofs** | 1 week | Blocks horizontal scaling. Disk risk. |
| 4 | **No repository layer / direct Eloquent** | 2 weeks | Hard to test. Tight coupling. |
| 5 | **Math CAPTCHA** | 3 days | Trivially bypassed. Weak bot protection. |
| 6 | **Hot key cache invalidation** | 1 week | Redis CPU bottleneck at scale. |
| 7 | **Inconsistent frontend (Blade + React hybrid)** | 3 weeks | Confusing auth model. Two build systems. |
| 8 | `splite.tsx` dead code | 10 minutes | Bundle bloat. Developer confusion. |
| 9 | No OpenAPI docs | 1 week | API consumer friction. |
| 10 | `AdminAuthTest` only covers happy path | 3 days | Missing edge case coverage. |

---

## 4.3 Refactor Roadmap (6 Months)

### Month 1: Critical Fixes
1. Remove `encrypted` cast from `transaction_reference`. Add `transaction_reference_hash` for uniqueness.
2. Delete `splite.tsx`. Fix Spline loading strategy.
3. Fix `CatalogController` to use `CourseService`.
4. Add `composer audit` to CI blocklist (fail on HIGH vulnerabilities).

### Month 2: Background Jobs
1. Implement `AuditLogJob`, `SendEmailJob`, `CacheBustJob`.
2. Add Laravel Horizon to `composer.json`.
3. Deploy 5 queue workers.
4. Switch audit logging to async.

### Month 3: Storage & Scaling
1. Migrate payment proofs to S3/R2.
2. Enable pre-signed URLs for downloads.
3. Add ClamAV scanning in upload pipeline.
4. Implement CDN for static assets.

### Month 4: Database Optimization
1. Add composite index `(status, is_featured, published_at)`.
2. Enable read replica for public routes.
3. Partition `audit_logs` by month (or move to ClickHouse/S3).
4. Implement query result caching for categories.

### Month 5: Security Hardening
1. Replace math CAPTCHA with Turnstile (mandatory in prod).
2. Add MFA for admin accounts.
3. Implement admin IP whitelist middleware.
4. Add `TRUSTED_PROXIES` strict validation.

### Month 6: Architecture Evolution
1. Introduce domain events (`CourseRequestApproved` event → listeners for cache, email, audit).
2. Add repository interfaces (`CourseRepositoryInterface`).
3. Standardize frontend: choose Blade+Inertia OR API+SPA.
4. Generate OpenAPI spec from routes.

---

## 4.4 Enterprise Engineering Score

| Criterion | Score (1-10) | Commentary |
|-----------|--------------|------------|
| **Code Organization** | 7 | Actions, Services, DTOs, Policies are well separated. Controllers are thin. |
| **Test Coverage** | 6 | 126 tests, but many are happy-path. Missing negative tests for race conditions. |
| **Documentation** | 5 | Good README, but no inline API docs. Architecture docs exist but contain false claims. |
| **Observability** | 4 | Health checks exist. No metrics, no tracing, no structured logs. |
| **Security Awareness** | 6 | CSP, rate limiting, audit logs are present. But critical bugs (encrypted unique, CAPTCHA) show gaps. |
| **Scalability Design** | 3 | Single-node thinking. No queues. No read replicas active. |
| **Operational Excellence** | 4 | Docker exists. Deployment is SSH-based. No rollback strategy. |
| **Overall** | **5.0/10** | **Junior-to-Mid level execution.** Solid for a startup MVP. Not enterprise-grade. |

---

## 4.5 Could This Codebase Survive a 10-Engineer Team?

**Verdict: Maybe, but with significant pain.**

**What works:**
- The directory structure is predictable (`Actions/`, `Services/`, `Policies/`).
- Form Request validation is consistent.
- DTOs are immutable.
- Tests exist and pass.

**What would break:**
1. **Merge conflicts on Models:** Multiple engineers touching `Course.php` or `CourseRequest.php` for different features would conflict constantly. No repository interface to abstract changes.
2. **Queue envy:** One team needs notifications, another needs exports, another needs reports. They'd all block on the "create jobs infrastructure" ticket.
3. **Auth confusion:** Frontend team wants SPA API. Backend team maintains session auth. The dual stack causes arguments.
4. **Database contention:** The `lockForUpdate()` pattern is correct but heavy. At 10 engineers, someone will forget it in a new action, causing race condition bugs.
5. **Test suite slowdown:** Tests use `RefreshDatabase` with MySQL. 126 tests is fine. 1,000 tests will take 10+ minutes. No parallel test infrastructure.

**Required for 10-engineer survival:**
- Event-driven architecture (decouples teams).
- Repository interfaces (reduces model conflicts).
- API-first standardization (one auth model).
- Parallel test execution with SQLite in-memory option.
- Feature flags for safe deployments.

---

# APPENDIX — Files Requiring Immediate Action

| File | Issue | Severity | Action |
|------|-------|----------|--------|
| `app/Models/PaymentProof.php` | `transaction_reference` encrypted + unique constraint | **CRITICAL** | Remove encrypted cast; add hash column |
| `app/Jobs/` | Directory is empty | **CRITICAL** | Add `AuditLogJob`, email jobs, cache jobs |
| `docker/php/www.conf` | `pm.max_children = 50` | **CRITICAL** | Increase to 200 or switch to Octane |
| `app/Http/Controllers/Admin/AuthController.php` | Math CAPTCHA for admin login | **HIGH** | Replace with Turnstile; add MFA |
| `app/Services/Courses/CourseService.php:77` | Hot key `courses.list:version` | **HIGH** | Use time-bucketed keys or cache tags |
| `app/Http/Controllers/Web/CatalogController.php` | Bypasses cache service | **HIGH** | Inject `CourseService` |
| `docker-compose.yml` | No healthcheck on PHP container | **MEDIUM** | Add `/up` healthcheck |
| `deploy.yml` | References `horizon:terminate` without Horizon | **MEDIUM** | Add `laravel/horizon` to composer or remove |
| `resources/js/components/ui/splite.tsx` | Dead code, React 19 incompatible | **LOW** | Delete |
| `app/Console/Commands/PruneAuditLogs.php` | Deletes audit logs (no archive) | **LOW** | Archive to S3 before delete |

---

*End of Brutal Production Audit.*
