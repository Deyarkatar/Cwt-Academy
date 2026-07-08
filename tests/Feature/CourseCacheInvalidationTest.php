<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Courses\ArchiveCourseAction;
use App\Actions\Courses\UpdateCourseAction;
use App\Enums\CourseStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\User;
use App\Services\Courses\CourseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Regression tests for course cache invalidation.
 */
class CourseCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_course_update_busts_list_cache(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
        ]);
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'title' => 'Original Title',
        ]);

        $service = app(CourseService::class);

        $itemBefore = $service->listActive()->first();
        $this->assertInstanceOf(Course::class, $itemBefore);
        $this->assertEquals('Original Title', $itemBefore->title);

        $action = app(UpdateCourseAction::class);
        $action->execute($course, ['title' => 'Updated Title'], $admin->id);

        $itemAfter = $service->listActive()->first();
        $this->assertInstanceOf(Course::class, $itemAfter);
        $this->assertEquals('Updated Title', $itemAfter->title);
    }

    public function test_course_update_busts_slug_cache(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
        ]);
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'slug' => 'original-course',
            'title' => 'Original Course',
        ]);

        $service = app(CourseService::class);
        $this->assertEquals('Original Course', $service->getActiveBySlug('original-course')?->title);

        $action = app(UpdateCourseAction::class);
        $action->execute($course, ['title' => 'Updated Course'], $admin->id);

        $this->assertEquals('Updated Course', $service->getActiveBySlug('original-course')?->title);
    }

    public function test_course_archive_busts_slug_and_list_cache(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
        ]);
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'slug' => 'archived-course',
            'title' => 'Archived Course',
        ]);

        $service = app(CourseService::class);
        $this->assertNotNull($service->getActiveBySlug('archived-course'));
        $this->assertCount(1, $service->listActive()->items());

        $action = app(ArchiveCourseAction::class);
        $action->execute($course, $admin->id);

        $this->assertNull($service->getActiveBySlug('archived-course'));
        $this->assertCount(0, $service->listActive()->items());
    }

    public function test_featured_home_cache_is_bumped_by_version_update(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'title' => 'Featured Course',
            'is_featured' => true,
        ]);

        $service = app(CourseService::class);
        $home1 = $service->featuredForHome(3);
        $this->assertInstanceOf(Collection::class, $home1);
        $this->assertEquals('Featured Course', $home1->first()?->title);

        $service->flushListCache();

        $home2 = $service->featuredForHome(3);
        $this->assertEquals('Featured Course', $home2->first()?->title);
        $this->assertNotSame($home1, $home2);
    }
}
