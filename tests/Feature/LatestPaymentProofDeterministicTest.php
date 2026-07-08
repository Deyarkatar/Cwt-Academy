<?php

namespace Tests\Feature;

use App\Enums\CourseRequestStatus;
use App\Enums\CourseStatus;
use App\Enums\PaymentProofStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Models\Instructor;
use App\Models\PaymentProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LatestPaymentProofDeterministicTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_payment_proof_returns_most_recent_deterministically(): void
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
            'status' => CourseRequestStatus::PENDING_PAYMENT,
        ]);

        $proof1 = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 50000,
            'status' => PaymentProofStatus::PENDING,
        ]);

        $proof2 = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);

        $latest = $courseRequest->fresh()?->latestPaymentProof;

        $this->assertNotNull($latest);
        $this->assertSame($proof2->id, $latest->id);
    }
}
