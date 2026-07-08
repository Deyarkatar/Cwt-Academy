<?php

namespace Database\Seeders\Courses;

use App\Enums\CourseLanguage;
use App\Enums\CourseLevel;

final class ItSecurityCourse extends CourseDefinition
{
    public function definition(): array
    {
        return [
            'slug' => 'it-security',
            'title' => 'IT Security',
            'category_slug' => 'cyber-security',
            'short_description' => 'Comprehensive IT Security course covering systems, networks, and defensive controls.',
            'description' => 'Learn the fundamentals and applied practices of IT Security: hardening systems, securing networks, identity and access management, monitoring, and incident response — taught in Kurdish and English.',
            'price_iqd' => 150000,
            'level' => CourseLevel::INTERMEDIATE,
            'language' => CourseLanguage::KU,
            'is_featured' => true,
            'telegram_title' => 'IT Security — Private Channel',
            'telegram_url' => 'https://t.me/+-eN6uK3ouW0yNDcy',
        ];
    }
}
