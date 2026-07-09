# Deployment Hardening Report — Cwt Academy (2026 Ultra Audit)

## Nginx Hardening

| Control | Status |
|---|---|
| `server_tokens off` | ✅ Hides nginx version |
| `client_body_timeout 15s` | ✅ Slowloris protection |
| `client_header_timeout 15s` | ✅ Slowloris protection |
| `send_timeout 30s` | ✅ Slow client protection |
| `reset_timedout_connection on` | ✅ Resets timed-out connections |
| `client_max_body_size 10M` | ✅ Upload size limit |
| Rate limiting zones (public, api, auth) | ✅ Per-endpoint rate limiting |
| `limit_conn` per IP | ✅ Connection limiting |
| HTTP method filtering | ✅ Blocks TRACE/TRACK/CONNECT |
| PHP file lockdown | ✅ Only `index.php` reaches FPM |
| Dotfile access blocked | ✅ `.env`, `.git` inaccessible |
| Config file access blocked | ✅ `composer.json`, `artisan` inaccessible |
| `fastcgi_hide_header X-Powered-By` | ✅ PHP version hidden |

## PHP-FPM Hardening

| Control | Status |
|---|---|
| `disable_functions` | ✅ exec, passthru, shell_exec, system, proc_open, popen, pcntl_exec, pcntl_fork, dl, pfsockopen |
| `allow_url_fopen = off` | ✅ Blocks fopen-based SSRF |
| `allow_url_include = off` | ✅ Blocks remote file inclusion |
| `display_errors = off` | ✅ No error leakage |
| `expose_php = off` | ✅ PHP version hidden |
| `cgi.fix_pathinfo = 0` | ✅ Prevents path_info smuggling |
| `session.use_strict_mode = 1` | ✅ Prevents session fixation |
| `session.cookie_httponly = 1` | ✅ JS cannot access cookies |
| `session.cookie_samesite = Strict` | ✅ CSRF protection |
| `session.use_only_cookies = 1` | ✅ No URL-based sessions |
| `url_rewriter.tags =` (empty) | ✅ Prevents SSRF/response-splitting |
| `max_input_time = 30` | ✅ Slow POST DoS protection |
| `pm = ondemand` | ✅ Dynamic process management |
| `pm.max_requests = 1000` | ✅ Prevents memory leaks |

## Docker Container Hardening

| Service | no-new-privileges | cap_drop | tmpfs | Read-only mounts |
|---|---|---|---|---|
| nginx | ✅ | ✅ ALL | ❌ | ✅ nginx.conf, public |
| php | ✅ | ✅ ALL | ✅ /tmp:noexec,nosuid | ✅ app, config, routes, etc. |
| mysql | ✅ | ✅ ALL | ❌ | ❌ (data volume) |
| redis | ✅ | ✅ ALL | ❌ | ❌ (data volume) |
| queue-worker | ✅ | ✅ ALL | ✅ /tmp:noexec,nosuid | ❌ Full mount (CLI needs) |
| scheduler | ✅ | ✅ ALL | ✅ /tmp:noexec,nosuid | ❌ Full mount (CLI needs) |
| horizon | ✅ | ✅ ALL | ✅ /tmp:noexec,nosuid | ❌ Full mount (CLI needs) |

## Redis Hardening

| Control | Status |
|---|---|
| Password required | ✅ `${REDIS_PASSWORD:?...}` fails if not set |
| `protected-mode yes` | ✅ Only localhost binds |
| `CONFIG` command renamed to `""` | ✅ Disabled |
| `SHUTDOWN` command renamed to `""` | ✅ Disabled |
| `MODULE` command renamed to `""` | ✅ Disabled |
| `REPLICAOF`/`SLAVEOF` renamed to `""` | ✅ Disabled |
| `appendonly yes` | ✅ AOF persistence |
| `maxmemory 512mb` + `allkeys-lru` | ✅ Memory limit |
| Bound to `127.0.0.1` | ✅ Not exposed externally |

## MySQL Hardening

| Control | Status |
|---|---|
| Root password required | ✅ `${MYSQL_ROOT_PASSWORD:?...}` |
| User password required | ✅ `${MYSQL_PASSWORD:?...}` |
| Bound to `127.0.0.1` | ✅ Not exposed externally |
| `PDO::ATTR_EMULATE_PREPARES = false` | ✅ Real prepared statements |
| `Mysql::ATTR_MULTI_STATEMENTS = false` | ✅ No stacked queries |
| MySQL strict mode | ✅ Enabled |

## Network Security

| Control | Status |
|---|---|
| Internal bridge network | ✅ `cwt_network` isolates services |
| Only nginx exposes ports | ✅ 80/443 only |
| MySQL/Redis bound to localhost | ✅ `127.0.0.1:port` |

## Remaining Recommendations

1. **TLS certificates** — Configure HTTPS in nginx (currently only listens on 80). Use Let's Encrypt or Cloudflare for TLS termination.
2. **WAF** — Enable Cloudflare WAF or ModSecurity for additional attack prevention.
3. **Image digest pinning** — Pin Docker images by SHA256 digest, not just tag.
4. **Queue-worker/scheduler/horizon volume mounts** — These CLI containers mount full source as writable. Consider granular mounts for production, though this requires careful testing of artisan command paths.
5. **Database backups** — Set up automated MySQL backups with encryption and off-site storage.
6. **Log aggregation** — Ship nginx/php-fpm logs to a centralized log service (e.g., Cloudwatch, ELK).
