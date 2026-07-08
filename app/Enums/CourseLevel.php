<?php

namespace App\Enums;

enum CourseLevel: string
{
    case BEGINNER = 'BEGINNER';
    case INTERMEDIATE = 'INTERMEDIATE';
    case ADVANCED = 'ADVANCED';
    case ALL_LEVELS = 'ALL_LEVELS';
}
