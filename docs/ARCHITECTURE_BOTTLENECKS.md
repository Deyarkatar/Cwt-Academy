# ARCHITECTURE_BOTTLENECKS.md

## Bottlenecks Identified and Fixed

### 1. Synchronous Audit Logging
- **Before:** Every mutation blocked on DB write
- **After:** Non-critical logs dispatched `afterResponse()`
- **File:** `app/Services/Audit/AuditLogger.php`

### 2. Database-as-Cache
- **Before:** `CACHE_STORE=database`, `SESSION_DRIVER=database`
- **After:** All defaults changed to `redis`
- **Files:** `.env.example`, `config/cache.php`, `config/session.php`, `config/queue.php`

### 3. Route Closures
- **Before:** 24 closures prevented route caching
- **After:** Zero closures, all routes use controllers
- **Files:** `routes/web.php`, `routes/api.php`

### 4. Monolithic Docker
- **Before:** Single MySQL container only
- **After:** Full stack — nginx, php-fpm, redis, queue-worker, scheduler
- **File:** `docker-compose.yml`

### 5. No CDN for Assets
- **Before:** All static content served by PHP
- **After:** Nginx serves static assets with 1-year cache headers
- **File:** `docker/nginx/nginx.conf`

### 6. No Queue Processing
- **Before:** `QUEUE_CONNECTION=database` with no workers
- **After:** Dedicated `queue-worker` container + scheduler
- **File:** `docker-compose.yml`

### 7. No Read Replicas
- **Status:** Still single MySQL instance
- **Recommendation:** Add read replica for SELECT queries

### 8. Local File Storage
- **Status:** Payment proofs still on local disk
- **Recommendation:** Migrate to S3/Cloudflare R2 with Laravel filesystem
