<?php

namespace Tests\Feature\Security;

use App\Enums\CourseRequestStatus;
use App\Enums\CourseStatus;
use App\Enums\PaymentProofStatus;
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

class BusinessLogicIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_proof_from_different_request_cannot_approve(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $token = $admin->createToken('admin', ['admin']);

        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => 100000,
        ]);

        $requestA = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);
        $requestB = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);

        $proofA = PaymentProof::factory()->create([
            'course_request_id' => $requestA->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson("/api/admin/course-requests/{$requestB->id}/approve", [
                'payment_proof_id' => $proofA->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_already_approved_proof_cannot_be_approved_again(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $token = $admin->createToken('admin', ['admin']);

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

        // First approval
        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/approve", [
                'payment_proof_id' => $proof->id,
            ])->assertOk();

        // Second approval should fail
        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/approve", [
                'payment_proof_id' => $proof->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_rejected_proof_cannot_be_rejected_again(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $token = $admin->createToken('admin', ['admin']);

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

        // First reject via payment proof endpoint
        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson("/api/admin/payment-proofs/{$proof->id}/reject", [
                'rejection_reason' => 'Invalid.',
            ])->assertOk();

        // Second reject should fail
        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson("/api/admin/payment-proofs/{$proof->id}/reject", [
                'rejection_reason' => 'Invalid again.',
            ]);

        $response->assertStatus(422);
    }
}
