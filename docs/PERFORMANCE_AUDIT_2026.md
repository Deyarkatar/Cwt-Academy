# Cwt Academy — Performance Audit 2026

**Date:** 2026-05-24
**Scope:** Backend queries, caching, frontend assets, database indexing, queue/job design

---

## Query Performance

| Area | Finding | Severity | Status |
|------|---------|----------|--------|
| Course listing | `CourseService::listActive` uses `->with(['category', 'instructor'])` + scopes | OK | PASS |
| Course detail | `CourseService::getBySlug` uses `->with(['category', 'instructor', 'telegramChannel'])` | OK | PASS |
| Admin dashboard stats | Multiple `count()` queries without caching | Medium | FIXED — added cache tags |
| Audit log index | Full-text search on `old_values`/`new_values` (JSON) | Medium | ACCEPTED — use dedicated search index in v2 |
| Payment proof index | `where('status', 'PENDING')` with `orderBy('created_at')` | OK | PASS (index present) |
| Student dashboard | Queries by `user_id` OR `student_email` | OK | PASS (composite index exists) |
| N+1 in welcome | Not applicable (static view) | OK | PASS |

## Caching

| Key | Strategy | TTL | Invalidation | Verdict |
|-----|----------|-----|--------------|---------|
| `courses.list` | Filter + paginate | 10 min | Manual bust on create/update/archive | PASS |
| `course.slug:{slug}` | Single record | 10 min | Manual bust on update | PASS |
| Admin stats | None | — | — | IMPROVED |
| Session | Database | 120 min | Laravel managed | PASS |
| Cache serialization | `serializable_classes: false` | — | — | PASS |

## Database Indexing

| Table | Index | Purpose | Status |
|-------|-------|---------|--------|
| `users` | `idx_users_role` | Admin lookup | ADDED |
| `users` | `idx_users_status` | Active filter | ADDED |
| `users` | `idx_users_last_login` | Audit / analytics | ADDED |
| `courses` | `idx_courses_status` | Public listing | ADDED |
| `courses` | `idx_courses_category` | Category filter | ADDED |
| `courses` | `idx_courses_featured` | Featured flag | ADDED |
| `courses` | `idx_courses_listing` | Composite public query | ADDED |
| `course_requests` | `idx_course_requests_status` | Dashboard filter | ADDED |
| `course_requests` | `idx_course_requests_tracking` | Lookup by code | ADDED |
| `course_requests` | `idx_course_requests_status_course` | Composite filter | ADDED |
| `payment_proofs` | `idx_payment_proofs_status_request` | Admin review queue | ADDED |
| `telegram_access_grants` | `course_request_id` UNIQUE | Duplicate prevention | ADDED |

## Frontend Performance

| Metric | Finding | Status |
|--------|---------|--------|
| Bundle size | Vite + Tailwind v4 tree-shaking | PASS |
| Font loading | Google Fonts with `display=swap` | PASS |
| Image optimization | No explicit WebP conversion | NOTE |
| JavaScript | Vanilla JS + minimal React (spline only) | PASS |
| CSS | Tailwind v4 with `@layer` | PASS |
| Critical CSS | Not inlined | ACCEPTED |

## Scalability Notes

- **Queue:** Changed from `sync` to `database` for production. Use Redis for high-throughput.
- **Cache:** Changed from `file` to `database`. Use Redis for multi-node deployments.
- **Session:** Changed from `file` to `database`. Use Redis for sticky-session alternatives.
- **File uploads:** Stored on `local` private disk. For production >1 node, migrate to S3/MinIO.
- **Database:** MySQL 8.0 with native password plugin. For production, enable SSL (`MYSQL_ATTR_SSL_CA`).

## Bottlenecks Identified

1. **Admin dashboard counts** — Fixed by adding caching layer around stats.
2. **Audit log search** — Recommend dedicated Elasticsearch/OpenSearch for full-text audit search.
3. **Payment proof file serving** — Local disk I/O. For high volume, use S3 with signed URLs.
4. **Course image thumbnails** — No CDN or image resizing. Add `spatie/laravel-image-optimizer` + CDN.

---

*End of Performance Audit Report*
