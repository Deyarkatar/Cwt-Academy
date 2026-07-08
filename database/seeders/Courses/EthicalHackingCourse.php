<?php

namespace Database\Seeders\Courses;

use App\Enums\CourseLanguage;
use App\Enums\CourseLevel;

final class EthicalHackingCourse extends CourseDefinition
{
    public function definition(): array
    {
        return [
            'slug' => 'ethical-hacking',
            'title' => 'Ethical Hacking',
            'category_slug' => 'cyber-security',
            'short_description' => 'Hands-on ethical hacking and offensive security techniques.',
            'description' => 'Master ethical hacking: reconnaissance, scanning, exploitation, post-exploitation, web app testing, and report writing. Practical labs and real-world scenarios included.',
            'price_iqd' => 200000,
            'level' => CourseLevel::ADVANCED,
            'language' => CourseLanguage::KU,
            'is_featured' => true,
            'telegram_title' => 'Ethical Hacking — Private Channel',
            'telegram_url' => 'https://t.me/+5-SwMtYMCNc4NmRi',
        ];
    }
}
