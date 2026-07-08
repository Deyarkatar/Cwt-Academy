<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * @deprecated Superseded by {@see CourseLibrarySeeder}, which auto-discovers
 *             every course file under `database/seeders/courses/`.
 *
 * Kept as a thin shim so existing scripts and CI commands that call this
 * seeder directly still work. Internally it just delegates to the new
 * library seeder.
 */
class CwtAcademyCourseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CourseLibrarySeeder::class);
    }
}
