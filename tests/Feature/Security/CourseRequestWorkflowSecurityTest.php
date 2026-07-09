<?php

namespace Tests\Feature\Security;

use App\Enums\CourseRequestStatus;
use App\Enums\CourseStatus;
use App\Enums\PaymentProofStatus;
use App\Enums\TelegramAccessGrantStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Models\Instructor;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseRequestWorkflowSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    protected function createCourseRequestWithProof(): array
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => 100000,
        ]);

        $courseRequest = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);
        $proof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);

        return [$courseRequest, $proof];
    }

    public function test_approved_request_cannot_be_re_approved(): void
    {
        $admin = $this->createAdmin();
        [$courseRequest, $proof] = $this->createCourseRequestWithProof();

        $token = $admin->createToken('admin', ['admin']);

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/approve", [
                'payment_proof_id' => $proof->id,
            ])->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/approve", [
                'payment_proof_id' => $proof->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_rejected_request_cannot_be_approved(): void
    {
        $admin = $this->createAdmin();
        [$courseRequest, $proof] = $this->createCourseRequestWithProof();

        $token = $admin->createToken('admin', ['admin']);

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/reject", [
                'rejection_reason' => 'Invalid proof.',
            ])->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/approve", [
                'payment_proof_id' => $proof->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_approval_creates_pending_manual_telegram_grant(): void
    {
        $admin = $this->createAdmin();
        [$courseRequest, $proof] = $this->createCourseRequestWithProof();

        $token = $admin->createToken('admin', ['admin']);

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/approve", [
                'payment_proof_id' => $proof->id,
            ])->assertOk();

        $this->assertDatabaseHas('telegram_access_grants', [
            'course_request_id' => $courseRequest->id,
            'status' => TelegramAccessGrantStatus::PENDING_MANUAL_ADD->value,
        ]);
    }

    public function test_rejection_does_not_create_telegram_grant(): void
    {
        $admin = $this->createAdmin();
        [$courseRequest] = $this->createCourseRequestWithProof();

        $token = $admin->createToken('admin', ['admin']);

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/reject", [
                'rejection_reason' => 'Invalid proof.',
            ])->assertOk();

        $this->assertDatabaseMissing('telegram_access_grants', [
            'course_request_id' => $courseRequest->id,
        ]);
    }

    public function test_tracking_code_is_16_chars_uppercase_alphanumeric(): void
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
        ]);

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{16}$/', $courseRequest->public_tracking_code);
    }

    public function test_amount_mismatch_prevents_approval(): void
    {
        $admin = $this->createAdmin();
        [$courseRequest, $proof] = $this->createCourseRequestWithProof();

        // Change proof amount to mismatch
        $proof->update(['amount_iqd' => 50000]);

        $token = $admin->createToken('admin', ['admin']);

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/approve", [
                'payment_proof_id' => $proof->id,
            ])
            ->assertUnprocessable();
    }
}
