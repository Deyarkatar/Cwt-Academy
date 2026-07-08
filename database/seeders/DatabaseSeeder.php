<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            InstructorSeeder::class,
            // CourseLibrarySeeder auto-discovers every *.php file in
            // database/seeders/courses/. To add a new course: drop a new
            // CourseDefinition subclass into that folder. See its README.
            CourseLibrarySeeder::class,
            CourseRequestSeeder::class,
            PaymentProofSeeder::class,
            TelegramAccessGrantSeeder::class,
        ]);
    }
}
