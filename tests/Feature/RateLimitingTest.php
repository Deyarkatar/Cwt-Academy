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

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_request_store_has_rate_limit_middleware(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
        ]);

        // First 5 requests should succeed
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/course-requests/store', [
                'course_id' => $course->id,
                'student_name' => 'Test Student',
                'student_email' => "student{$i}@example.com",
            ]);
            $response->assertRedirect();
        }

        // 6th request should be rate limited
        $response = $this->post('/course-requests/store', [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'student6@example.com',
        ]);

        $response->assertStatus(429);
    }

    public function test_payment_proof_upload_has_rate_limit_middleware(): void
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
            'status' => CourseRequestStatus::PENDING_PAYMENT,
        ]);

        $file = $this->paymentProofFile('application/pdf', 'proof.pdf');

        // First 3 uploads should succeed
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson("/request-success/{$request->public_tracking_code}/payment-proof", [
                'amount_iqd' => 100000,
                'proof_file' => $file,
            ]);
            // Each upload changes status to PENDING_REVIEW, so reset for next loop
            $request->update(['status' => CourseRequestStatus::PENDING_PAYMENT]);
        }

        // 4th upload should be rate limited
        $request->update(['status' => CourseRequestStatus::PENDING_PAYMENT]);
        $response = $this->postJson("/request-success/{$request->public_tracking_code}/payment-proof", [
            'amount_iqd' => 100000,
            'proof_file' => $file,
        ]);

        $response->assertStatus(429);
    }

    public function test_track_lookup_has_rate_limit_middleware(): void
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
            'status' => CourseRequestStatus::PENDING_PAYMENT,
        ]);

        // First 10 lookups should succeed
        for ($i = 0; $i < 10; $i++) {
            $response = $this->get("/track?code={$request->public_tracking_code}");
            $response->assertOk();
        }

        // 11th lookup should be rate limited
        $response = $this->get("/track?code={$request->public_tracking_code}");
        $response->assertStatus(429);
    }
}
