# Admin Guide

## Login

1. Send a POST request to `/api/admin/login` with your email and password
2. Use the returned Bearer token in the `Authorization` header for all subsequent requests

Example:
```bash
curl -X POST http://localhost:8000/api/admin/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@cwtacademy.local","password":"your-password"}'
```

## Courses

### Creating a Course
1. POST to `/api/admin/courses`
2. Required fields: `title`, `slug`, `short_description`, `description`, `price_iqd`, `level`, `language`, `status`
3. Optional: `category_id`, `instructor_id`, `thumbnail`, `is_featured`, `published_at`

### Archiving a Course
1. POST to `/api/admin/courses/{id}/archive`
2. Archived courses are hidden from the public API

## Requests

### Viewing Requests
1. GET `/api/admin/course-requests`
2. Filter by status using `?status=PENDING_REVIEW`

### Approving a Request
1. Ensure a payment proof exists for the request
2. POST to `/api/admin/course-requests/{id}/approve`
3. Body: `{"payment_proof_id": 1}`
4. The student can now see the Telegram channel link in their dashboard to request access

### Rejecting a Request
1. POST to `/api/admin/course-requests/{id}/reject`
2. Body: `{"rejection_reason": "..."}`
3. The student will see the rejection reason when tracking their request

## Payment Approval

### Viewing Payment Proofs
1. GET `/api/admin/payment-proofs`
2. Filter by status using `?status=PENDING`

### Downloading Proof Files
1. GET `/api/admin/payment-proofs/{id}/download`
2. Returns the uploaded file for manual verification

### Approving a Payment
1. POST to `/api/admin/payment-proofs/{id}/approve`
2. The amount must match the course price

### Rejecting a Payment
1. POST to `/api/admin/payment-proofs/{id}/reject`
2. Body: `{"rejection_reason": "..."}`

## Telegram Channel Setup (Manual Join-Request Workflow)

### Creating a Channel Mapping
1. Create a Telegram channel and configure it to allow join requests
2. POST to `/api/admin/telegram-channels`
3. Body:
```json
{
  "course_id": 1,
  "telegram_url": "https://t.me/+your_channel_invite_link",
  "title": "My Course Channel",
  "private_channel_name": "Course Channel Internal Name",
  "is_active": true
}
```

### Getting the Telegram URL
- Open your Telegram channel settings
- Create an invite link (can be permanent or with approval required)
- Copy the `https://t.me/+...` link
- Use that link in the `telegram_url` field above

### Deactivating a Channel
1. POST to `/api/admin/telegram-channels/{id}/deactivate`
2. Body: `{"reason": "Course ended"}`

## Manual Access Management

### Marking Student as Added to Telegram
1. After manually accepting the student's join request in Telegram
2. Find the grant in `/api/admin/telegram-access-grants`
3. POST to `/api/admin/telegram-access-grants/{id}/mark-added`
4. Optional body: `{"admin_note": "Added to channel on 2024-01-15", "manual_access_reference": "user123"}`
5. The student can see their access status on the tracking page

### Revoking Access
1. POST to `/api/admin/telegram-access-grants/{id}/mark-revoked`
2. Body: `{"revoked_reason": "Refund requested"}`
3. Manually remove the student from the Telegram channel
4. The course request status and tracking page will reflect the revocation
