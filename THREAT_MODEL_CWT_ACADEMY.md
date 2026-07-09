# Threat Model — Cwt Academy (2026 Ultra Audit)

## Methodology

STRIDE threat modeling applied to each trust boundary and data flow.

## Trust Boundaries

1. **Internet → Nginx** — untrusted traffic hits reverse proxy
2. **Nginx → PHP-FPM** — filtered requests reach application
3. **Public user → API** — unauthenticated/low-trust API access
4. **Student → Web** — authenticated session, low privilege
5. **Admin → API/Web** — Sanctum token or session, high privilege
6. **Application → MySQL** — database access
7. **Application → Redis** — cache/queue/session access
8. **Application → Storage** — file system for uploads

## Threats by Category

### Spoofing

| Threat | Mitigation | Status |
|---|---|---|
| Credential brute-force | Rate limiting (IP + email), AccountLockoutService with exponential backoff, BruteForceDetectionMiddleware (20 attempts → 1hr block) | ✅ Mitigated |
| Session fixation | Session regeneration on login (`$request->session()->regenerate()`) | ✅ Mitigated |
| Token theft | Sanctum tokens with `admin` ability only, 240min expiration, HTTPS-only cookies | ✅ Mitigated |
| IP spoofing via X-Forwarded-For | TrustedProxyValidator rejects empty/wildcard proxies in production | ✅ Mitigated |

### Tampering

| Threat | Mitigation | Status |
|---|---|---|
| Mass assignment privilege escalation | `User` model excludes `role`/`status` from `$fillable` | ✅ Mitigated |
| Audit log tampering | `AuditLog` model throws on update/delete, `UPDATED_AT = null` | ✅ Mitigated |
| Payment proof file tampering | MIME + magic byte validation, path traversal prevention | ✅ Mitigated |
| Status filter injection | Enum validation on `CourseRequestController::index` and `PaymentProofController::index` | ✅ Mitigated (this audit) |
| CSRF | `VerifyCsrfToken` on web routes, SameSite=strict cookies | ✅ Mitigated |

### Repudiation

| Threat | Mitigation | Status |
|---|---|---|
| Denying login attempt | All login attempts (success + failure) logged in AuditLog with IP + user agent | ✅ Mitigated |
| Denying payment proof action | Submit/approve/reject/download all logged in AuditLog | ✅ Mitigated |
| Denying admin action | All admin actions logged with actor_id, entity, old/new values | ✅ Mitigated |

### Information Disclosure

| Threat | Mitigation | Status |
|---|---|---|
| Tracking code enumeration | Rate limiting (20/IP, 30/prefix per min), 16-char random codes | ✅ Mitigated |
| Private data via tracking API | Email hash required for extended data (payment status, Telegram access, rejection notes) | ✅ Mitigated |
| Payment proof file access | Authorization via Policy, path validation, local disk (not public) | ✅ Mitigated |
| Stack trace leakage | `APP_DEBUG=false`, `display_errors=Off`, exception handler masks 500 errors | ✅ Mitigated |
| Server version disclosure | `server_tokens off`, `X-Powered-By` stripped, `expose_php=Off` | ✅ Mitigated |
| .env file access | Nginx blocks dotfiles, PHP-FPM only processes `index.php` | ✅ Mitigated |
| Honey token detection | HoneyTokenGuard checks request headers/body for leaked fake secrets | ✅ Mitigated |

### Denial of Service

| Threat | Mitigation | Status |
|---|---|---|
| Login DoS | Rate limiting + lockout + brute force detection | ✅ Mitigated |
| API DoS | Nginx rate limiting (50r/s API), Laravel throttle middleware | ✅ Mitigated |
| Slowloris | Nginx `client_body_timeout 15s`, `client_header_timeout 15s`, `reset_timedout_connection` | ✅ Mitigated |
| Large file upload DoS | 10MB limit in PHP + nginx | ✅ Mitigated |
| Tracking enumeration DoS | TrackingRateLimitMiddleware (20/IP/min) | ✅ Mitigated |

### Elevation of Privilege

| Threat | Mitigation | Status |
|---|---|---|
| Student → Admin | `EnsureAdminAuthenticated` checks `isAdmin()` + token `admin` ability | ✅ Mitigated |
| Admin → Super Admin | Role-based policy checks for `canManageAdmins()` | ✅ Mitigated |
| Self-approval | Policy checks `user->email !== courseRequest->student_email` | ✅ Mitigated |
| Webshell via upload | Nginx blocks all `.php` except `index.php`, MIME + magic byte validation | ✅ Mitigated |
| Command injection | PHP-FPM `disable_functions` blocks exec/system/proc_open/etc. | ✅ Mitigated |

## Remaining Risks (Accepted)

1. **Queue-worker/scheduler/horizon containers** mount full source as writable — required for CLI operations (storage, cache). Lower risk since these are internal containers not exposed to the internet.
2. **No virus scanning implementation** — `virus_scan_status` is set to `pending` but no actual scanner (e.g., ClamAV) is integrated. Files are not served inline (forced download), reducing but not eliminating risk.
3. **HIBP compromised password check** only runs in production (requires outbound HTTP). Dev/test environments don't check.
4. **No WAF** — Cloudflare WAF not configured in the codebase. Should be enabled at the infrastructure layer.
5. **No rate limiting on admin API CRUD operations** beyond the global API throttle. Individual endpoint rate limiting could be added.
