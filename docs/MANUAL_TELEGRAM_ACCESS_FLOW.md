# Manual Telegram Join-Request Access Flow

**Source of Truth: This document describes the actual implemented workflow.**

Cwt Academy delivers course content through private Telegram groups/channels. The system uses a **manual join-request workflow** with no Telegram bot, no auto-generated invite links, and no webhook-based tracking.

## How It Works

1. **Student submits request**: Student browses courses and submits a request with payment proof.
2. **Admin approves payment**: Admin/finance reviews and approves the payment.
3. **Student sees link**: Upon approval, the student dashboard/tracking page shows the Telegram join/request link for that course.
4. **Student requests to join**: Student clicks the Telegram link and sends a join request inside Telegram.
5. **Staff manually accepts**: Our team manually approves the join request in Telegram.
6. **Optional tracking**: Admin marks the access as "granted" in the admin panel for record-keeping.
7. **Student tracks status**: Student can track their request status via the public tracking page.

## Admin Actions

- **Mark as Added**: After manually adding the student to Telegram, update the access record.
- **Mark as Revoked**: If access needs to be removed, mark it as revoked with a reason.
- **Add Notes**: Store private admin notes and references (e.g., "Added to Channel A").

## Student-Facing Messages

The tracking page displays different messages based on access status:

- **Pending manual add**: "Your payment was approved. Our team will review your Telegram join request."
- **Access granted**: "Your Telegram access has been granted. Click the link to request to join the channel."
- **Revoked**: "Your Telegram access has been revoked. Contact support for more information."

When access is granted (`MANUALLY_ADDED` or `ACCESS_SENT`), the tracking page also displays the Telegram channel URL if configured.

## No Bot Required

- No `TELEGRAM_BOT_TOKEN` needed.
- No webhook setup.
- No automatic invite link generation.
- No join/leave tracking via bot.

The website only tracks approval and manual access status.

---

## Status Flow

```
Payment Approved
    ↓
PENDING_MANUAL_ADD (student sees Telegram link)
    ↓
Student clicks link → Sends join request in Telegram
    ↓
Staff approves in Telegram
    ↓
MANUALLY_ADDED (admin marks as granted)
    ↓
[Optional: ACCESS_SENT if additional notification sent]
    ↓
[If needed: REVOKED (staff removes from Telegram + marks revoked)]
```

## Technical Implementation

- **Status Enum**: `TelegramAccessGrantStatus` with values:
  - `PENDING_MANUAL_ADD` - Waiting for student to request and staff to approve
  - `MANUALLY_ADDED` - Staff has accepted the join request
  - `ACCESS_SENT` - Additional access confirmation sent (optional)
  - `REVOKED` - Access has been revoked
  - `FAILED` - Technical failure (rare)

- **Database**: `telegram_access_grants` table tracks status, timestamps, and admin notes
- **API Endpoints**:
  - `POST /api/admin/telegram-access-grants/{id}/mark-added` - Mark as granted
  - `POST /api/admin/telegram-access-grants/{id}/mark-revoked` - Mark as revoked
