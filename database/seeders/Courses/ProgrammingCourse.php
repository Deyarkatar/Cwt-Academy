<?php

namespace Database\Seeders\Courses;

use App\Enums\CourseLanguage;
use App\Enums\CourseLevel;

final class ProgrammingCourse extends CourseDefinition
{
    public function definition(): array
    {
        return [
            'slug' => 'programming',
            'title' => 'Programming',
            'category_slug' => 'programming',
            'short_description' => 'Programming foundations and modern development workflows.',
            'description' => 'A complete programming course taking you from the basics to building real applications. Covers core programming concepts, algorithms, version control, testing, and deployment.',
            'price_iqd' => 150000,
            'level' => CourseLevel::BEGINNER,
            'language' => CourseLanguage::KU,
            'is_featured' => true,
            'telegram_title' => 'Programming — Private Channel',
            'telegram_url' => 'https://t.me/+hbAArrXyuMVjNTY6',
        ];
    }
}
