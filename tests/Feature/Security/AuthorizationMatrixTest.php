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

class AuthorizationMatrixTest extends TestCase
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

    protected function createStudent(): User
    {
        return User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    protected function createCourseRequest(): CourseRequest
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => 100000,
        ]);

        return CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);
    }

    public function test_guest_cannot_access_admin_dashboard_api(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
    }

    public function test_guest_cannot_approve_course_request(): void
    {
        $request = $this->createCourseRequest();
        $this->postJson("/api/admin/course-requests/{$request->id}/approve", [
            'payment_proof_id' => 1,
        ])->assertUnauthorized();
    }

    public function test_guest_cannot_reject_course_request(): void
    {
        $request = $this->createCourseRequest();
        $this->postJson("/api/admin/course-requests/{$request->id}/reject", [
            'rejection_reason' => 'Test',
        ])->assertUnauthorized();
    }

    public function test_student_cannot_approve_course_request(): void
    {
        $student = $this->createStudent();
        $courseRequest = $this->createCourseRequest();
        $proof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);

        $token = $student->createToken('test');

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/approve", [
                'payment_proof_id' => $proof->id,
            ])
            ->assertForbidden();
    }

    public function test_student_cannot_download_payment_proof_api(): void
    {
        $student = $this->createStudent();
        $courseRequest = $this->createCourseRequest();
        $proof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
        ]);

        $token = $student->createToken('test');

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson("/api/admin/payment-proofs/{$proof->id}/download")
            ->assertForbidden();
    }

    public function test_student_cannot_download_payment_proof_web(): void
    {
        $student = $this->createStudent();
        $courseRequest = $this->createCourseRequest();
        $proof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
        ]);

        $response = $this->actingAs($student)
            ->get("/admin/payment-proofs/{$proof->id}/download");

        $response->assertRedirect('/dashboard');
    }

    public function test_guest_cannot_download_payment_proof_web(): void
    {
        $courseRequest = $this->createCourseRequest();
        $proof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
        ]);

        $response = $this->get("/admin/payment-proofs/{$proof->id}/download");

        $response->assertRedirect('/login');
    }

    public function test_admin_can_list_course_requests(): void
    {
        $admin = $this->createAdmin();
        $this->createCourseRequest();

        $token = $admin->createToken('admin', ['admin']);

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/admin/course-requests')
            ->assertOk();
    }

    public function test_student_cannot_list_course_requests(): void
    {
        $student = $this->createStudent();
        $this->createCourseRequest();

        $token = $student->createToken('test');

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/admin/course-requests')
            ->assertForbidden();
    }

    public function test_admin_cannot_approve_own_request(): void
    {
        $admin = $this->createAdmin();
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
            'student_email' => $admin->email,
        ]);
        $proof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);

        $token = $admin->createToken('admin', ['admin']);

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/approve", [
                'payment_proof_id' => $proof->id,
            ])
            ->assertForbidden();
    }
}
