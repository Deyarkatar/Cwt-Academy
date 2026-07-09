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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InputValidationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_proof_index_rejects_invalid_status_filter(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $token = $admin->createToken('admin', ['admin']);

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/admin/payment-proofs?status=INVALID');

        $response->assertStatus(422);
    }

    public function test_payment_proof_index_accepts_valid_status_filter(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $token = $admin->createToken('admin', ['admin']);

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/admin/payment-proofs?status='.PaymentProofStatus::PENDING->value);

        $response->assertOk();
    }

    public function test_course_request_index_rejects_invalid_status_filter(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $token = $admin->createToken('admin', ['admin']);

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/admin/course-requests?status=HACKED');

        $response->assertStatus(422);
    }

    public function test_course_request_index_accepts_valid_status_filter(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $token = $admin->createToken('admin', ['admin']);

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/admin/course-requests?status=PENDING_REVIEW');

        $response->assertOk();
    }

    public function test_registration_validates_email_format(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test',
            'email' => 'not-an-email',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'captcha_answer' => 'skip',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_registration_requires_strong_password(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test',
            'email' => 'weakpass@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'captcha_answer' => 'skip',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_rejection_reason_max_length_enforced(): void
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
        ]);
        $courseRequest = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/reject", [
                'rejection_reason' => str_repeat('A', 600),
            ]);

        $response->assertStatus(422);
    }
}
