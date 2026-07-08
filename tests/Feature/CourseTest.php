<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\Instructor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_course_list_shows_only_active_courses(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();

        Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'title' => 'Active Course',
        ]);

        Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::DRAFT,
            'title' => 'Draft Course',
        ]);

        Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ARCHIVED,
            'title' => 'Archived Course',
        ]);

        $response = $this->getJson('/api/v1/courses');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Active Course');
    }

    public function test_public_course_detail_returns_delivery_explanation(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'slug' => 'test-course',
            'title' => 'Test Course',
        ]);

        $response = $this->getJson('/api/v1/courses/test-course');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.course.title', 'Test Course')
            ->assertJsonPath('data.delivery_method', null)
            ->assertJsonPath('data.delivery_explanation', 'Contact support for course access details.');
    }
}
