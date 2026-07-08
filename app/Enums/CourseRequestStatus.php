<?php

namespace App\Enums;

enum CourseRequestStatus: string
{
    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case PENDING_REVIEW = 'PENDING_REVIEW';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case EXPIRED = 'EXPIRED';
    case REVOKED = 'REVOKED';
}
