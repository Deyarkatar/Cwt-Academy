# API Documentation

## Response Format

### Success
```json
{
  "ok": true,
  "data": {}
}
```

### Failure
```json
{
  "ok": false,
  "message": "Human readable message",
  "errors": {}
}
```

## Public API

### GET /api/courses
List active courses with pagination.

**Query Parameters:**
- `category` - Filter by category slug
- `level` - Filter by level (BEGINNER, INTERMEDIATE, ADVANCED, ALL_LEVELS)
- `language` - Filter by language (KU, AR, EN)
- `search` - Search in title and description

**Response:**
```json
{
  "ok": true,
  "data": {
    "data": [...],
    "links": {...},
    "meta": {...}
  }
}
```

### GET /api/courses/{slug}
Get active course details.

**Response:**
```json
{
  "ok": true,
  "data": {
    "course": {...},
    "delivery_method": "private_telegram_channel",
    "delivery_explanation": "Course content is delivered through a private Telegram channel after payment approval."
  }
}
```

### POST /api/course-requests
Create a new course request.

**Body:**
```json
{
  "course_id": 1,
  "student_name": "John Doe",
  "student_email": "john@example.com",
  "student_phone": "+9647501234567",
  "student_note": "Optional note"
}
```

**Response:**
```json
{
  "ok": true,
  "message": "COURSE_REQUEST_CREATED",
  "data": {
    "tracking_code": "ABCD1234EFGH5678",
    "status": "PENDING_PAYMENT",
    "payment_instructions": {
      "amount_iqd": 150000,
      "method": "MANUAL",
      "note": "Please pay the amount and submit proof using your tracking code."
    }
  }
}
```

### GET /api/course-requests/{tracking_code}
Check request status.

**Response:**
```json
{
  "ok": true,
  "data": {
    "tracking_code": "ABCD1234EFGH5678",
    "status": "APPROVED",
    "course_title": "Laravel Backend Development",
    "course_price_iqd": 150000,
    "payment_proof_status": "APPROVED",
    "telegram_access": {
      "status": "MANUALLY_ADDED",
      "message": "Your Telegram access has been granted. Click the link below to request to join the channel.",
      "url": "https://t.me/+your_channel_link"
    }
  }
}
```

**Note:** The `telegram_access.url` is only shown when access is granted and a channel is configured. Students must click the link and send a join request, which staff manually approves in Telegram.

### POST /api/course-requests/{tracking_code}/payment-proof
Submit payment proof.

**Body:** (multipart/form-data)
- `amount_iqd` (integer, required)
- `sender_name` (string, optional)
- `transaction_reference` (string, optional, unique)
- `proof_file` (file, required: jpg, png, webp, pdf, max 5MB)

**Response:**
```json
{
  "ok": true,
  "message": "PAYMENT_PROOF_PENDING_REVIEW",
  "data": {
    "proof_id": 1,
    "status": "PENDING"
  }
}
```

## Admin API

All admin endpoints require `Authorization: Bearer {token}` header.

### Auth
- `POST /api/admin/login` - Admin login
- `POST /api/admin/logout` - Admin logout
- `GET /api/admin/me` - Get current admin

### Dashboard
- `GET /api/admin/dashboard` - Dashboard statistics

### Courses
- `GET /api/admin/courses` - List all courses
- `POST /api/admin/courses` - Create course
- `GET /api/admin/courses/{id}` - Get course
- `PUT /api/admin/courses/{id}` - Update course
- `POST /api/admin/courses/{id}/archive` - Archive course

### Categories
- `GET /api/admin/categories` - List categories
- `POST /api/admin/categories` - Create category
- `PUT /api/admin/categories/{id}` - Update category

### Instructors
- `GET /api/admin/instructors` - List instructors
- `POST /api/admin/instructors` - Create instructor
- `PUT /api/admin/instructors/{id}` - Update instructor
- `POST /api/admin/instructors/{id}/approve` - Approve instructor
- `POST /api/admin/instructors/{id}/reject` - Reject instructor

### Course Requests
- `GET /api/admin/course-requests` - List requests
- `GET /api/admin/course-requests/{id}` - Get request
- `POST /api/admin/course-requests/{id}/approve` - Approve request
  - Body: `{ "payment_proof_id": 1 }`
- `POST /api/admin/course-requests/{id}/reject` - Reject request
  - Body: `{ "rejection_reason": "..." }`

### Payment Proofs
- `GET /api/admin/payment-proofs` - List proofs
- `GET /api/admin/payment-proofs/{id}` - Get proof
- `GET /api/admin/payment-proofs/{id}/download` - Download proof file
- `POST /api/admin/payment-proofs/{id}/approve` - Approve proof
- `POST /api/admin/payment-proofs/{id}/reject` - Reject proof
  - Body: `{ "rejection_reason": "..." }`

### Telegram Channels
- `GET /api/admin/telegram-channels` - List channels
- `POST /api/admin/telegram-channels` - Create channel mapping
- `GET /api/admin/telegram-channels/{id}` - Get channel
- `PUT /api/admin/telegram-channels/{id}` - Update channel
- `POST /api/admin/telegram-channels/{id}/deactivate` - Deactivate channel
  - Body: `{ "reason": "..." }`

### Telegram Access Grants
- `GET /api/admin/telegram-access-grants` - List grants
- `GET /api/admin/telegram-access-grants/{id}` - Get grant
- `POST /api/admin/telegram-access-grants/{id}/mark-added` - Mark student as manually added to Telegram
  - Body: `{ "admin_note": "...", "manual_access_reference": "..." }` (both optional)
- `POST /api/admin/telegram-access-grants/{id}/mark-revoked` - Revoke access
  - Body: `{ "revoked_reason": "..." }` (required)

### Audit Logs
- `GET /api/admin/audit-logs` - List audit logs
  - Query: `action`, `entity_type`, `actor_id`

---

## Manual Telegram Access Flow - Source of Truth

**This API uses a manual Telegram join-request workflow:**

1. Student submits course request + payment proof
2. Admin approves payment
3. Student dashboard/tracking shows Telegram channel join link
4. Student opens link and sends join request in Telegram
5. Staff manually accepts request in Telegram
6. Admin marks access as granted via `/api/admin/telegram-access-grants/{id}/mark-added`

**No automated features:**
- ❌ No auto-generated one-time invite links
- ❌ No Telegram bot integration
- ❌ No webhook tracking
- ❌ No automatic revocation

The website tracks approval/access status only.
