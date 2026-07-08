# RACE_CONDITION_ANALYSIS.md

**Cwt Academy — Concurrency, Locking & Race Condition Deep Dive**

---

## Methodology

This analysis traces every write-heavy flow through the application, identifying:
- Missing transaction boundaries
- Read-before-write (check-then-act) patterns
- Lock scope and granularity
- Phantom reads and lost updates
- Deadlock possibilities

---

## 1. Course Request Approval Flow

### 1.1 Flow Trace

```
API: POST /api/admin/course-requests/{id}/approve
  -> CourseRequestController::approve
    -> ApproveCourseRequestRequest validation
    -> CourseRequest::findOrFail($id)        [READ 1]
    -> PaymentProof::findOrFail($proofId)     [READ 2]
    -> ApproveCourseRequestAction::execute
      -> DB::transaction
        -> CourseRequest::lockForUpdate()->firstOrFail  [READ 3, LOCKED]
        -> PaymentProof::lockForUpdate()->firstOrFail   [READ 4, LOCKED]
        -> status checks
        -> UPDATE proof
        -> UPDATE request
        -> INSERT TelegramAccessGrant
        -> INSERT AuditLog (x3)
```

### 1.2 Assessment: MOSTLY SAFE

- **Lock Order:** CourseRequest first, then PaymentProof. This is consistent across all approval/rejection flows.
- **State Machine:** Enforced inside the transaction on locked rows.
- **Amount Check:** `$lockedProof->amount_iqd !== $lockedRequest->course->price_iqd` is checked after locking. Safe.
- **TelegramAccessGrant Creation:** Uses `TelegramAccessGrant::create()` inside the transaction. The DB unique constraint on `course_request_id` (added in migration `2026_05_22_000002`) prevents duplicate grants if a second transaction tries to create one concurrently.

### 1.3 Residual Risk: Phantom Grant (LOW)

If two admins approve the same request simultaneously (both read before either locks), one will fail with a unique constraint violation on `telegram_access_grants.course_request_id`. The exception is not caught explicitly; it will bubble up as a 500 error.

**Fix:** Wrap the grant creation in a try/catch for `QueryException` with code 23000, and return a graceful error like "Request was already approved by another admin."

---

## 2. Payment Proof Submission Flow

### 2.1 Flow Trace

```
API: POST /api/v1/course-requests/{code}/payment-proof
  -> RequestTrackingController::storePaymentProof
    -> CourseRequest::where('public_tracking_code', $code)->first()  [READ 1]
    -> canSubmitPaymentProof() check                                  [CHECK 1]
    -> ManualPaymentService::storeProof
      -> DB::transaction
        -> CourseRequest::lockForUpdate()->firstOrFail               [READ 2, LOCKED]
        -> canSubmitPaymentProof() check on locked row               [CHECK 2]
        -> validate amount, file, magic bytes
        -> store file
        -> INSERT PaymentProof
        -> UPDATE request status = PENDING_REVIEW
```

### 2.2 Assessment: DOUBLE-SUBMISSION BUG (HIGH)

**Vulnerability:** `canSubmitPaymentProof()` returns `true` for both `PENDING_PAYMENT` and `PENDING_REVIEW`. If two concurrent requests submit proofs:

1. **Thread A** locks the request (status = `PENDING_PAYMENT`), passes `canSubmitPaymentProof()`, inserts proof, updates status to `PENDING_REVIEW`, commits.
2. **Thread B** locks the request (status = `PENDING_REVIEW` because Thread A committed), passes `canSubmitPaymentProof()` (which allows `PENDING_REVIEW`), inserts a SECOND proof, commits.

**Result:** The request now has **two payment proofs** (or more). While the admin approval flow (`ApproveCourseRequestAction`) checks that the proof belongs to the request and is pending, it doesn't prevent multiple pending proofs from existing. The admin could inadvertently approve an old proof while a newer one is also pending.

**Fix:** Change `canSubmitPaymentProof()` to **only** allow `PENDING_PAYMENT`, or add a DB unique constraint on `(course_request_id, status)` where `status = 'PENDING'` for payment proofs.

---

## 3. Tracking Code Generation

### 3.1 Flow Trace

```
CourseRequest::boot()
  -> static::creating
    -> if empty(public_tracking_code)
      -> do { generate 16-char code } while (exists() && attempts < 10)
```

### 3.2 Assessment: COLLISION RACE (MEDIUM)

The generation loop is **not inside a transaction** and uses `self::where('public_tracking_code', ...)->exists()` as an existence check. In high concurrency:

1. **Thread A** generates code `X123...`.
2. **Thread B** generates the same code `X123...` (16 chars from 36 possibilities = 2.8e24 combinations, collision probability is astronomically low but non-zero).
3. Both threads check `exists()` simultaneously → both return `false`.
4. Both attempt `INSERT`. One succeeds, the other gets a unique constraint violation (500 error).

**Fix:** The retry loop should catch the `QueryException` and regenerate, or use a DB sequence/prefixed UUID instead of random strings.

---

## 4. Course Update Flow

### 4.1 Flow Trace

```
API: PUT /api/admin/courses/{id}
  -> CourseController::update
    -> Course::findOrFail($id)                    [READ 1]
    -> UpdateCourseAction::execute
      -> DB::transaction
        -> Course::lockForUpdate()->firstOrFail  [READ 2, LOCKED]
        -> fill($data)
        -> save()
        -> Cache::forget(...)
        -> Cache::increment('courses.list:version')
```

### 4.2 Assessment: LOST UPDATE ON SLUG (MEDIUM)

If two admins update the same course simultaneously:
- Thread A changes slug from `old-slug` to `new-slug-a`.
- Thread B changes slug from `old-slug` to `new-slug-b`.
- Both lock the row, but Thread A commits first. Thread B overwrites with its slug.
- Thread B's cache invalidation forgets `new-slug-b` but not `new-slug-a`, leaving a stale cache entry.

**Fix:** The cache invalidation logic is already present but incomplete. After save, it should invalidate both old and new slugs. Currently it does this (lines 29-32 in `UpdateCourseAction`), but if concurrent updates happen, the second one may miss the first's new slug.

**Better Fix:** Use optimistic locking (`version` column) or accept that last-write-wins is the intended semantics.

---

## 5. Telegram Access Grant State Transitions

### 5.1 Web Flow: markAdded

```
POST /admin/telegram-access-grants/{id}/mark-added
  -> TelegramAccessActionController::markAdded
    -> DB::transaction
      -> lockForUpdate()->firstOrFail
      -> if status != PENDING_MANUAL_ADD → RuntimeException
      -> UPDATE status = MANUALLY_ADDED
```

### 5.2 Assessment: SAFE

The status check and update are inside a transaction with row locking. No race condition.

---

## 6. Cache Version Bumping

### 6.1 Flow Trace

```
ArchiveCourseAction::execute
  -> Cache::forget('course.slug:' . $course->slug)
  -> Cache::increment('courses.list:version')
```

### 6.2 Assessment: SAFE

`Cache::increment` is atomic on all cache drivers that support it (Redis, Memcached, Database). The version bump is safe.

---

## 7. Rate Limiter Race Condition (LOW)

### 7.1 Issue

`RateLimiter::tooManyAttempts($key, $limit)` followed by `RateLimiter::hit($key)` is not atomic in Laravel. In a burst of concurrent requests:
1. All threads read `attempts = 4` (limit = 5).
2. All threads pass `tooManyAttempts`.
3. All threads call `hit()`, resulting in `attempts = 4 + N`.

This is a known Laravel limitation and generally acceptable for rate limiting (it errs on the side of allowing slightly more requests, not blocking legitimate ones).

---

## 8. Instructor Approval/Rejection

### 8.1 Flow Trace

```
POST /api/admin/instructors/{id}/approve
  -> DB::transaction
    -> lockForUpdate()->firstOrFail
    -> if status == APPROVED → ValidationException
    -> UPDATE status = APPROVED
```

### 8.2 Assessment: SAFE

Row locking + status check inside transaction prevents double-approval.

---

## Summary Table

| Flow | Locking | Transaction | Race Risk | Severity |
|------|---------|-------------|-----------|----------|
| Course Request Approval | Yes | Yes | Phantom grant (uncaught 500) | Low |
| Payment Proof Submit | Yes | Yes | **Double submission** | **High** |
| Tracking Code Gen | No | No | Collision → 500 | Medium |
| Course Update | Yes | Yes | Stale cache | Medium |
| Telegram Grant Mark | Yes | Yes | None | Safe |
| Instructor Approval | Yes | Yes | None | Safe |
| Rate Limiting | N/A | No | Burst overflow | Low |

---

*End of RACE_CONDITION_ANALYSIS.md*
