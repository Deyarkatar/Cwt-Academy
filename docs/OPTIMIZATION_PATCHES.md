# OPTIMIZATION_PATCHES.md

## Patch A: Database Indexes (APPLIED)
- **Migration:** `2026_05_26_000007_add_missing_production_indexes.php`
- Adds 11 indexes across 7 tables

## Patch B: Redis Architecture (APPLIED)
- `.env.example`: Default drivers changed to redis
- `config/cache.php`, `config/session.php`, `config/queue.php`: Defaults updated
- `docker-compose.yml`: Redis service added

## Patch C: Route Refactoring (APPLIED)
- Created 12 new controllers
- `routes/web.php`: 0 closures
- `routes/api.php`: 0 closures

## Patch D: Dashboard Query Optimization (APPLIED)
- `DashboardController`: Single aggregate counts + SQL filtering
- `AdminDashboardController`: Cached stats + whereExists join

## Patch E: CourseService Cache (APPLIED)
- Web routes use controllers with eager loading

## Patch F: Docker Production Stack (APPLIED)
- Services: nginx, php-fpm, redis, mysql, queue-worker, scheduler
- Files: `docker/php/Dockerfile`, `opcache.ini`, `php.ini`, `www.conf`
- Files: `docker/nginx/nginx.conf`

## Patch G: Frontend Optimization (APPLIED)
- Consolidated Google Fonts with preload
- Lazy-loaded Spline with IntersectionObserver
- Added `loading="lazy"` and `decoding="async"` to images

## Patch H: Health Check (APPLIED)
- Replaced file I/O with `is_writable()` check

## Patch I: OPcache (APPLIED)
- `docker/php/opcache.ini`: Production config with JIT

## Patch J: PaymentProof N+1 (APPLIED)
- Added `->with('course')` to locked request query
