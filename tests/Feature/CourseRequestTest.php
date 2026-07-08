<?php

namespace Tests\Feature;

use App\Enums\CourseRequestStatus;
use App\Enums\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Models\Instructor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_request_can_be_created_for_active_course(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => 100000,
        ]);

        $response = $this->postJson('/api/v1/course-requests', [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'student@example.com',
            'student_phone' => '+9647501234567',
            'student_city' => 'Erbil',
        ]);

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'COURSE_REQUEST_CREATED')
            ->assertJsonPath('data.status', CourseRequestStatus::PENDING_PAYMENT->value)
            ->assertJsonPath('data.payment_instructions.amount_iqd', 100000);

        $this->assertDatabaseHas('course_requests', [
            'course_id' => $course->id,
            'student_city' => 'Erbil',
        ]);

        $createdRequest = CourseRequest::query()->where('course_id', $course->id)->first();
        $this->assertNotNull($createdRequest);
        $this->assertEquals('student@example.com', $createdRequest->student_email);
    }

    public function test_course_request_cannot_be_created_for_archived_course(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ARCHIVED,
        ]);

        $response = $this->postJson('/api/v1/course-requests', [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'student@example.com',
            'student_phone' => '+9647501234567',
            'student_city' => 'Erbil',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['course_id']);
    }

    public function test_student_can_track_request_by_tracking_code(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
        ]);

        $request = CourseRequest::factory()->create([
            'course_id' => $course->id,
        ]);

        $response = $this->getJson("/api/v1/course-requests/{$request->public_tracking_code}");

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.tracking_code', $request->public_tracking_code)
            ->assertJsonPath('data.status', $request->status->value);
    }

    public function test_tracking_code_does_not_leak_other_student_data(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
        ]);

        $request1 = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'student_email' => 'student1@example.com',
        ]);

        $response = $this->getJson("/api/v1/course-requests/{$request1->public_tracking_code}");

        $response->assertOk()
            ->assertJsonPath('data.tracking_code', $request1->public_tracking_code);

        /** @var array<string, mixed> $data */
        $data = $response->json('data') ?? [];
        $this->assertTrue(! isset($data['admin_note']));
    }
}
