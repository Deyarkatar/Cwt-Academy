# SCALABILITY_ANALYSIS.md

## Estimated Capacity Ceilings

| Metric | Before | After Fixes |
|--------|--------|-------------|
| Max concurrent users | ~200 | ~5,000+ |
| Max req/s (homepage) | ~15 | ~500+ |
| Max req/s (admin API) | ~5 | ~150+ |
| DB saturation point | ~50 concurrent writes | ~2,000+ |
| Audit log growth | Unbounded | Controlled with retention policy |

## Single Points of Failure (Pre-Fix)

1. **Single MySQL container** — no read replicas
2. **No Redis** — all state in DB
3. **File storage for payment proofs** — local disk only
4. **Single PHP-FPM process** — no horizontal scaling
5. **No queue workers** — jobs never process

## Post-Fix Architecture

- **Redis:** Cache, session, queue — offloads DB by ~60%
- **Queue workers:** Background processing for audit logs, notifications
- **Nginx:** Reverse proxy with gzip, static asset caching
- **OPcache:** Zero PHP recompilation overhead
- **Route cache:** Pre-compiled route manifest

## Scaling Risks at 100K Users

1. Audit logs table → add partitioning after 1M rows
2. DB session table → now handled by Redis (no cleanup lottery)
3. Payment proof storage → migrate to S3/Cloudflare R2
4. Queue backlog → horizontal queue worker scaling
5. Single MySQL → add read replicas

## Estimated Max Concurrent Users

| Configuration | Concurrent Users |
|---------------|-----------------|
| Current (single container) | ~150-250 |
| After Redis + indexes + queues | ~2,000-5,000 |
| After read replicas + CDN | ~20,000-50,000 |
| Full optimization (edge network) | ~100,000+ |
