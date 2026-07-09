<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CourseRequestStatus;
use App\Models\Course;
use App\Models\CourseRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_without_email_hash_returns_limited_data(): void
    {
        $course = Course::factory()->create();
        CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::APPROVED,
            'public_tracking_code' => 'ABCD1234EFGH5678',
            'student_email' => 'test@example.com',
        ]);

        $response = $this->getJson('/api/v1/course-requests/ABCD1234EFGH5678');

        $response->assertOk();
        $response->assertJsonPath('data.tracking_code', 'ABCD1234EFGH5678');
        $response->assertJsonMissingPath('data.payment_proof_status');
        $response->assertJsonMissingPath('data.telegram_access');
    }

    public function test_tracking_with_valid_email_hash_returns_full_data(): void
    {
        $course = Course::factory()->create();
        CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::APPROVED,
            'public_tracking_code' => 'ABCD1234EFGH5678',
            'student_email' => 'test@example.com',
        ]);

        $hash = hash('sha256', 'test@example.com');
        $response = $this->getJson("/api/v1/course-requests/ABCD1234EFGH5678?email_hash={$hash}");

        $response->assertOk();
        $response->assertJsonPath('data.tracking_code', 'ABCD1234EFGH5678');
    }

    public function test_tracking_with_invalid_email_hash_returns_404(): void
    {
        $course = Course::factory()->create();
        CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::APPROVED,
            'public_tracking_code' => 'ABCD1234EFGH5678',
            'student_email' => 'test@example.com',
        ]);

        $wrongHash = hash('sha256', 'wrong@example.com');
        $response = $this->getJson("/api/v1/course-requests/ABCD1234EFGH5678?email_hash={$wrongHash}");

        $response->assertNotFound();
    }

    public function test_tracking_with_invalid_code_format_returns_404(): void
    {
        $response = $this->getJson('/api/v1/course-requests/invalid-code');

        $response->assertNotFound();
    }

    public function test_tracking_rate_limit_blocks_excessive_requests(): void
    {
        $course = Course::factory()->create();
        CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::APPROVED,
            'public_tracking_code' => 'ABCD1234EFGH5678',
        ]);

        for ($i = 0; $i < 21; $i++) {
            $this->getJson('/api/v1/course-requests/ABCD1234EFGH5678');
        }

        $response = $this->getJson('/api/v1/course-requests/ABCD1234EFGH5678');
        $response->assertStatus(429);
    }

    public function test_web_tracking_without_email_hash_returns_limited_data(): void
    {
        $course = Course::factory()->create();
        CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::APPROVED,
            'public_tracking_code' => 'ABCD1234EFGH5678',
        ]);

        $response = $this->get('/track?code=ABCD1234EFGH5678');

        $response->assertOk();
    }

    public function test_web_tracking_with_invalid_code_format_does_not_leak_data(): void
    {
        $response = $this->get('/track?code=invalid-short-code');

        $response->assertOk();
        $response->assertViewHas('requestData', null);
    }
}
