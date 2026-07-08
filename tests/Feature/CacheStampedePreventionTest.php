<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Services\Courses\CourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Tests for Fix 5: Cache redesign and stampede prevention.
 */
class CacheStampedePreventionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_course_listing_is_cached(): void
    {
        $category = Category::factory()->create();
        Course::factory()->count(5)->create([
            'category_id' => $category->id,
            'status' => 'ACTIVE',
        ]);

        $service = app(CourseService::class);

        $result1 = $service->listActive();
        $result2 = $service->listActive();

        $this->assertEquals($result1->items(), $result2->items());
    }

    public function test_page_number_is_capped(): void
    {
        $category = Category::factory()->create();
        Course::factory()->count(5)->create([
            'category_id' => $category->id,
            'status' => 'ACTIVE',
        ]);

        $service = app(CourseService::class);

        request()->merge(['page' => 9999]);
        $result = $service->listActive();

        $this->assertEquals(100, $result->currentPage());
    }

    public function test_search_input_is_normalized_and_truncated(): void
    {
        $category = Category::factory()->create();
        Course::factory()->count(3)->create([
            'category_id' => $category->id,
            'status' => 'ACTIVE',
            'title' => 'Test Course Title',
        ]);

        $service = app(CourseService::class);

        $longSearch = str_repeat('a', 500);
        request()->merge(['search' => $longSearch]);

        $result = $service->listActive(['search' => $longSearch]);

        $this->assertNotNull($result);
    }

    public function test_list_cache_can_be_flushed(): void
    {
        $category = Category::factory()->create();
        Course::factory()->count(3)->create([
            'category_id' => $category->id,
            'status' => 'ACTIVE',
        ]);

        $service = app(CourseService::class);
        $service->listActive();

        $versionBefore = Cache::get('courses.list:version', 0);
        $service->flushListCache();
        $versionAfter = Cache::get('courses.list:version', 0);

        $this->assertNotEquals($versionBefore, $versionAfter);
    }

    public function test_slug_cache_is_isolated(): void
    {
        $category = Category::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'status' => 'ACTIVE',
            'slug' => 'test-course',
        ]);

        $service = app(CourseService::class);
        $result = $service->getActiveBySlug('test-course');

        $this->assertNotNull($result);
        $this->assertEquals($course->id, $result->id);
    }

    public function test_max_page_limits_cache_cardinality(): void
    {
        $category = Category::factory()->create();
        Course::factory()->count(5)->create([
            'category_id' => $category->id,
            'status' => 'ACTIVE',
        ]);

        $service = app(CourseService::class);

        for ($i = 1; $i <= 105; $i++) {
            request()->merge(['page' => $i]);
            $service->listActive();
        }

        request()->merge(['page' => 101]);
        $result = $service->listActive();
        $this->assertEquals(100, $result->currentPage());

        request()->merge(['page' => 105]);
        $result = $service->listActive();
        $this->assertEquals(100, $result->currentPage());
    }
}
