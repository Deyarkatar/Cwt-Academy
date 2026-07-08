# Cwt Academy - Project Overview

## What is Cwt Academy?

Cwt Academy is a Kurdish/Kurdistan-focused course marketplace and manual-payment approval platform. Unlike traditional video-based LMS platforms, Cwt Academy delivers course content through **private Telegram channels**.

## Core Product Model - Manual Telegram Join-Request Access Flow

1. Visitors browse courses on the website
2. Each course has a private Telegram channel where actual lessons are posted
3. A student chooses a course and submits a request with their details
4. The student pays manually outside the website and submits payment proof
5. Admin/finance reviews the payment proof in the admin system
6. If approved, the student dashboard shows the Telegram join/request link for that course
7. The student opens the Telegram link and sends a join request inside Telegram
8. Our team manually accepts the student's join request in Telegram
9. Admin marks the access as granted in the admin panel (optional tracking)
10. If payment is rejected, refunded, or revoked, staff manually removes the student from Telegram

## User Roles

### SUPER_ADMIN
- Full access to all features
- Can manage admin users
- Can access all audit logs

### ADMIN
- Manage courses, categories, instructors
- Manage course requests
- Manage Telegram channels
- Approve/reject requests

### FINANCE_MANAGER
- Review payment proofs
- Approve/reject payments
- View finance-related requests
- Cannot manage admin users or courses

## Main Flow

```
Student -> Browse Courses -> Select Course -> Submit Request
  |
  v
Submit Payment Proof (manual) -> Admin Review
  |
  v
Approve -> Student Sees Telegram Link -> Student Requests to Join
  |                                           |
  v                                           v
Reject -> No Access Granted          Admin Manually Accepts in Telegram
  |                                           |
  v                                           v
Student Can Retry                    Admin Marks as Granted (tracking)
```

## Tech Stack

- **Backend:** Laravel 13 / PHP 8.3+
- **Database:** MySQL (configurable)
- **Auth:** Laravel Sanctum
- **Queues:** Laravel Queues (database driver)
- **Storage:** Laravel Storage for payment proofs
- **Telegram:** Manual join-request workflow (no bot, no webhooks, no auto-invite links)
- **Testing:** PHPUnit

## Architecture

- API-first architecture
- Form Request validation
- Policies/Gates for authorization
- Service layer for business logic
- Action classes for core workflows
- Jobs for background processing
- Audit logging for all sensitive actions
