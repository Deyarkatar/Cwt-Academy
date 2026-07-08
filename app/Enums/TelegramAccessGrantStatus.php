<?php

namespace App\Enums;

enum TelegramAccessGrantStatus: string
{
    case PENDING_MANUAL_ADD = 'PENDING_MANUAL_ADD';
    case MANUALLY_ADDED = 'MANUALLY_ADDED';
    case ACCESS_SENT = 'ACCESS_SENT';
    case REVOKED = 'REVOKED';
    case FAILED = 'FAILED';
}
