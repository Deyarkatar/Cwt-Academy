<?php

namespace Database\Seeders;

use App\Enums\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\Instructor;
use Illuminate\Database\Seeder;

class TestCourseSeeder extends Seeder
{
    public function run(): void
    {
        // Only seed test data in the testing environment to avoid touching
        // production or local development data.
        if (! app()->environment('testing')) {
            return;
        }

        $category = Category::firstOrCreate(
            ['slug' => 'test-category'],
            [
                'name' => 'Test Category',
                'description' => 'Category used only for automated tests.',
                'sort_order' => 0,
                'is_active' => true,
            ]
        );

        $instructor = Instructor::firstOrCreate(
            ['email' => 'test.instructor@example.com'],
            [
                'name' => 'Test Instructor',
                'phone' => '+9647500000000',
                'bio' => 'Instructor used only for automated tests.',
                'status' => 'APPROVED',
            ]
        );

        Course::firstOrCreate(
            ['slug' => 'test-course'],
            [
                'category_id' => $category->id,
                'instructor_id' => $instructor->id,
                'title' => 'Test Course for E2E',
                'short_description' => 'A safe test course used by the E2E test suite.',
                'description' => 'This course exists only for automated end-to-end tests.',
                'price_iqd' => 100000,
                'thumbnail' => null,
                'level' => 'BEGINNER',
                'language' => 'KURDISH',
                'status' => CourseStatus::ACTIVE,
                'is_featured' => false,
                'published_at' => now(),
            ]
        );
    }
}
