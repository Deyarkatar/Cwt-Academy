<?php

namespace Tests\Feature\Security;

use App\Enums\CourseRequestStatus;
use App\Enums\CourseStatus;
use App\Enums\TelegramAccessGrantStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Models\Instructor;
use App\Models\TelegramAccessGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingAccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_tracking_code_format_returns_404(): void
    {
        $response = $this->getJson('/api/v1/course-requests/SHORT');

        $response->assertNotFound();
    }

    public function test_nonexistent_tracking_code_returns_404(): void
    {
        $response = $this->getJson('/api/v1/course-requests/NOTFOUND1234567');

        $response->assertNotFound();
    }

    public function test_tracking_without_email_hash_shows_limited_data(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'title' => 'Test Course Title',
        ]);

        $courseRequest = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);

        $response = $this->getJson("/api/v1/course-requests/{$courseRequest->public_tracking_code}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals($courseRequest->public_tracking_code, $data['tracking_code']);
        $this->assertEquals('PENDING_REVIEW', $data['status']);
        $this->assertEquals('Test Course Title', $data['course_title']);
        $this->assertArrayNotHasKey('payment_proof_status', $data);
        $this->assertArrayNotHasKey('telegram_access', $data);
    }

    public function test_tracking_with_email_hash_shows_extended_data(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
        ]);

        $courseRequest = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_REVIEW,
            'student_email' => 'track@example.com',
        ]);

        $hash = hash('sha256', 'track@example.com');

        $response = $this->getJson("/api/v1/course-requests/{$courseRequest->public_tracking_code}?email_hash={$hash}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('payment_proof_status', $data);
    }

    public function test_tracking_with_wrong_email_hash_returns_404(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
        ]);

        $courseRequest = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'student_email' => 'real@example.com',
        ]);

        $wrongHash = hash('sha256', 'wrong@example.com');

        $response = $this->getJson("/api/v1/course-requests/{$courseRequest->public_tracking_code}?email_hash={$wrongHash}");

        $response->assertNotFound();
    }

    public function test_approved_request_shows_telegram_access_status(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
        ]);

        $courseRequest = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::APPROVED,
            'student_email' => 'approved@example.com',
        ]);

        TelegramAccessGrant::create([
            'course_request_id' => $courseRequest->id,
            'course_id' => $course->id,
            'student_name' => $courseRequest->student_name,
            'student_email' => $courseRequest->student_email,
            'student_phone' => $courseRequest->student_phone,
            'status' => TelegramAccessGrantStatus::PENDING_MANUAL_ADD,
        ]);

        $hash = hash('sha256', 'approved@example.com');

        $response = $this->getJson("/api/v1/course-requests/{$courseRequest->public_tracking_code}?email_hash={$hash}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('telegram_access', $data);
        $this->assertEquals('PENDING_MANUAL_ADD', $data['telegram_access']['status']);
    }

    public function test_rejected_request_shows_rejection_note_with_hash(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
        ]);

        $courseRequest = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::REJECTED,
            'student_email' => 'rejected@example.com',
            'public_rejection_note' => 'Payment proof was insufficient.',
        ]);

        $hash = hash('sha256', 'rejected@example.com');

        $response = $this->getJson("/api/v1/course-requests/{$courseRequest->public_tracking_code}?email_hash={$hash}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('public_rejection_note', $data);
        $this->assertEquals('Payment proof was insufficient.', $data['public_rejection_note']);
    }
}
