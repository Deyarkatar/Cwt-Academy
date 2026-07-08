<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Legacy demo course seeder.
 *
 * Superseded by {@see CwtAcademyCourseSeeder}, which seeds the three real
 * courses (IT Security, Ethical Hacking, Programming) with their dedicated
 * Telegram channels. This class is kept as a no-op for backwards compatibility.
 */
class CourseSeeder extends Seeder
{
    public function run(): void
    {
        // Intentionally empty. Use CwtAcademyCourseSeeder.
    }
}
