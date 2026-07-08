# DATABASE_PERFORMANCE.md

## Index Optimization

### Migration: `2026_05_26_000007_add_missing_production_indexes.php`

| Table | Index | Columns | Purpose |
|-------|-------|---------|---------|
| audit_logs | idx_audit_logs_actor | actor_id + actor_type | Admin audit log filtering by actor |
| audit_logs | idx_audit_logs_entity | entity_type + entity_id | Entity change history lookups |
| audit_logs | idx_audit_logs_action_created | action + created_at | Action-based time-range queries |
| audit_logs | idx_audit_logs_created_at | created_at | Time-based pruning and pagination |
| telegram_channels | idx_telegram_channels_course | course_id | Course detail page channel lookup |
| telegram_channels | idx_telegram_channels_active | is_active | Active channel listings |
| notifications | idx_notifications_recipient_read | recipient_user_id + read_at | Dashboard notification queries |
| categories | idx_categories_slug | slug | Course filtering by category slug |
| instructors | idx_instructors_status | status | Instructor listing and filtering |
| course_requests | idx_course_requests_status_v2 | status | Status-based dashboard counts |
| course_requests | idx_course_requests_created_v2 | created_at | Recent requests sorting |
| payment_proofs | idx_payment_proofs_status_v2 | status | Pending proof admin counts |
| payment_proofs | idx_payment_proofs_request_v2 | course_request_id | Request-proof relationship lookups |

## Query Anti-Patterns Fixed

| Pattern | Location | Fix |
|---------|----------|-----|
| `whereHas` subquery | `AdminDashboardController` | Replaced with `whereExists` indexed join |
| Collection `filter()` after paginate | `DashboardController` | Filter in SQL before pagination |
| 3-query dashboard counts | `DashboardController` | Two indexed `COUNT()` queries |
| N+1 on payment proof approval | `PaymentProofController` | Added `->with('course')` |
| Missing eager load on tracking | `TrackingController` | Added `course.telegramChannel`, `latestPaymentProof`, `telegramAccessGrant` |

## Schema Recommendations

1. **Audit Log Partitioning:** For tables exceeding 1M rows, consider monthly partitioning on `created_at`
2. **Student Name/Email Encryption:** Columns widened to 512 chars for encryption overhead — monitor row size impact
3. **Transaction Reference Unique Index:** Scoped uniqueness by `course_request_id` instead of global (migration already applied)
