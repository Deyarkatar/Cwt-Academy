<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case ADMIN = 'ADMIN';
    case FINANCE_MANAGER = 'FINANCE_MANAGER';
    case STUDENT = 'STUDENT';
}
