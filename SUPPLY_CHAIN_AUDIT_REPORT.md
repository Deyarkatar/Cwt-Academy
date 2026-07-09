# Supply Chain Audit Report — Cwt Academy (2026 Ultra Audit)

## PHP Dependencies (composer.lock)

### Framework

| Package | Version | Status |
|---|---|---|
| laravel/framework | 11.x | ✅ Latest major, actively maintained |
| laravel/sanctum | Latest | ✅ Token auth, maintained |
| laravel/horizon | Latest | ✅ Queue dashboard, maintained |

### Security-Critical Packages

| Package | Purpose | Status |
|---|---|---|
| predis/predis | Redis client | ✅ Pure PHP, no native extensions |
| guzzlehttp/guzzle | HTTP client | ✅ Used for CAPTCHA verification |
| league/flysystem | Filesystem abstraction | ✅ Standard Laravel dependency |

### Audit Checks

1. **No dev dependencies in production** — `composer install --no-dev` should be used in Docker build
2. **Composer pinned** — `composer:2.8.5` pinned in Dockerfile for reproducible builds
3. **composer.lock present** — Lock file committed, ensures deterministic installs
4. **No abandoned packages** — All packages are actively maintained

## NPM Dependencies (package-lock.json)

### Frontend Stack

| Package | Purpose | Status |
|---|---|---|
| vite | Build tool | ✅ Latest |
| @spline-design/react-spline | 3D hero | ✅ Used for homepage robot |
| tailwindcss | CSS framework | ✅ Standard |

### Audit Checks

1. **package-lock.json present** — Lock file committed
2. **No postinstall scripts** — Verified no malicious postinstall hooks
3. **No CDNs in production build** — All assets bundled via Vite

## Docker Images

| Image | Version | Status |
|---|---|---|
| nginx | 1.27-alpine | ✅ Latest stable, Alpine (minimal) |
| php | 8.4-fpm-alpine | ✅ Latest PHP, Alpine (minimal) |
| mysql | 8.0.40 | ✅ Patched version |
| redis | 7.4-alpine | ✅ Latest, Alpine (minimal) |

### Container Security

- **no-new-privileges**: All services have `security_opt: no-new-privileges:true`
- **cap_drop: ALL**: All capabilities dropped, minimal `cap_add` per service
- **tmpfs**: `/tmp` mounted with `noexec,nosuid` on PHP containers
- **Read-only mounts**: Source code mounted read-only on php-fpm container
- **Health checks**: All services have health checks

## Recommendations

1. **Enable Dependabot/Renovate** — Automated dependency updates for both composer and npm
2. **Run `composer audit`** in CI pipeline — Checks for known vulnerabilities
3. **Run `npm audit`** in CI pipeline — Checks for known vulnerabilities
4. **Pin all Docker images by digest** — Currently pinned by tag; digest pinning prevents supply chain attacks on Docker Hub
5. **SBOM generation** — Generate Software Bill of Materials in CI for compliance
