# PRODUCTION_HARDENING_CHECKLIST.md

**Cwt Academy — 2026-Grade Production Hardening Checklist**

---

## Pre-Deployment Security Gates

| # | Control | Status | Owner |
|---|---------|--------|-------|
| 1 | `APP_ENV=production` | Required | DevOps |
| 2 | `APP_DEBUG=false` | Required | DevOps |
| 3 | `APP_KEY` is 32+ random chars, stored in secrets manager | Required | DevOps |
| 4 | `SESSION_ENCRYPT=true` | Required | DevOps |
| 5 | `SESSION_SECURE_COOKIE=true` | Required | DevOps |
| 6 | `SESSION_SAME_SITE=strict` | Required | DevOps |
| 7 | `FORCE_HTTPS=true` | Required | DevOps |
| 8 | `TRUSTED_PROXIES` set to actual proxy IP range (not `*`) | Required | DevOps |
| 9 | `ADMIN_DEFAULT_PASSWORD` unset after initial seeding | Required | DevOps |
| 10 | `CAPTCHA_DRIVER=turnstile` with valid site/secret keys | Required | DevOps |
| 11 | Database credentials are strong and stored in secrets manager | Required | DevOps |
| 12 | MySQL uses `caching_sha2_password` (not `mysql_native_password`) | Required | DevOps |
| 13 | `config/sanctum.php` exists with hardened settings | Required | Backend |
| 14 | `CSP_REPORT_URI` configured to a log collector | Recommended | DevOps |
| 15 | Rate limiter uses Redis (not database) for consistency | Recommended | DevOps |
| 16 | Queue driver uses Redis/Database (not sync) for reliability | Required | DevOps |
| 17 | Cache driver uses Redis/Memcached (not file) for performance | Recommended | DevOps |
| 18 | Object storage (S3/MinIO) configured for payment proofs | Recommended | DevOps |
| 19 | Server-side encryption at rest enabled on database and storage | Required | DevOps |
| 20 | Backups are encrypted, tested monthly, and stored offsite | Required | DevOps |

---

## Application Hardening

### Authentication
- [ ] Enable MFA for all admin accounts (TOTP or WebAuthn)
- [ ] Implement `rehash` on login if password uses outdated algorithm
- [ ] Add `max_age` password policy (force rotation every 90 days for admins)
- [ ] Disable password reset for admin accounts; require manual admin creation

### Authorization
- [ ] Review all policies quarterly
- [ ] Add `cannot` tests for every policy method
- [ ] Enforce `email_verified_at` before any privileged action

### Session
- [ ] Set `SESSION_LIFETIME=30` (max 30 minutes idle)
- [ ] Enable `SESSION_EXPIRE_ON_CLOSE=true` for admin sessions
- [ ] Implement concurrent session limits (max 2 sessions per admin)

### API
- [ ] Sanctum tokens expire after 4 hours maximum
- [ ] Implement token refresh rotation
- [ ] Scope tokens by ability (`admin:read`, `admin:write`)
- [ ] Require `Origin` header validation for SPA tokens

### File Uploads
- [ ] Store payment proofs in per-request directories
- [ ] Scan uploads with ClamAV before storage
- [ ] Apply server-side encryption (SSE-S3 or AES-256) to stored files
- [ ] Limit max file size to 2MB (reduce from 5MB)
- [ ] Add virus scan result column to `payment_proofs` table

### Audit & Logging
- [ ] Add `request_id` column to `audit_logs`
- [ ] Compute `integrity_hash` per audit row
- [ ] Move audit logs to append-only table or external SIEM
- [ ] Prune audit logs to separate cold storage before deletion
- [ ] Log all admin logins to SIEM in real-time
- [ ] Alert on >10 failed logins per IP per hour

### Database
- [ ] Add `CHECK` constraints for all enum/status columns
- [ ] Add unique constraints where business rules require them
- [ ] Enable binary logging (binlog) for point-in-time recovery
- [ ] Enable `general_log` temporarily for compliance validation only
- [ ] Rotate DB credentials every 90 days

### Frontend
- [ ] Fix `@vite` nonce injection for CSP compatibility
- [ ] Remove all inline `style` attributes; use CSS classes
- [ ] Add `Subresource Integrity (SRI)` hashes to CDN assets
- [ ] Configure `Referrer-Policy: no-referrer` for sensitive pages

### Infrastructure
- [ ] Run application containers as non-root user
- [ ] Use read-only filesystem for PHP containers (`/tmp` and `/var/log` exceptions)
- [ ] Enable AppArmor or seccomp profiles
- [ ] Deploy WAF (Cloudflare, AWS WAF, or ModSecurity) in front of app
- [ ] Enable DDoS protection on origin IP (not just DNS)
- [ ] Configure automated security scanning (Trivy, Snyk) in CI/CD
- [ ] Run `composer audit` and `npm audit` in CI pipeline; block deploy on HIGH+

### Monitoring & Incident Response
- [ ] Integrate Sentry/Datadog for real-time error alerting
- [ ] Monitor failed login rates, CAPTCHA failure rates, and 403/429 spikes
- [ ] Define escalation playbook for:
  - [ ] Suspected admin account compromise
  - [ ] Mass fake course requests (DDoS)
  - [ ] Unusual payment proof volume
  - [ ] CSP violation spikes
- [ ] Run quarterly penetration tests
- [ ] Maintain a 24/7 on-call rotation for security incidents

---

## Compliance Alignment

| Requirement | Implementation |
|-------------|---------------|
| OWASP ASVS 5.0 Level 2 | Apply all HIGH+ fixes in this audit |
| NIST SP 800-63B | Argon2id, 12+ char passwords, HIBP check |
| GDPR Article 32 | Encrypt PII at rest and in transit |
| PCI DSS v4.0 (if applicable) | Isolate payment data, encrypt storage, audit trails |
| ISO 27001:2022 A.8.9 | Immutable audit logs, access reviews |

---

*End of PRODUCTION_HARDENING_CHECKLIST.md*
