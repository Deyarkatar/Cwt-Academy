<?php

namespace Tests\Feature\Security;

use App\Enums\CourseRequestStatus;
use App\Enums\CourseStatus;
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

class IdorProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_code_does_not_expose_private_data_without_email_hash(): void
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
            'student_email' => 'private@example.com',
            'student_phone' => '07501234567',
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);

        $response = $this->getJson("/api/v1/course-requests/{$courseRequest->public_tracking_code}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayNotHasKey('public_rejection_note', $data);
        $this->assertArrayNotHasKey('payment_proof_status', $data);
        $this->assertArrayNotHasKey('telegram_access', $data);
    }

    public function test_wrong_email_hash_does_not_reveal_private_data(): void
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
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);

        $wrongHash = hash('sha256', 'wrong@example.com');

        $response = $this->getJson("/api/v1/course-requests/{$courseRequest->public_tracking_code}?email_hash={$wrongHash}");

        $response->assertNotFound();
    }

    public function test_payment_proof_download_requires_admin_role(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

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
        $proof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
        ]);

        $token = $student->createToken('test');

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson("/api/admin/payment-proofs/{$proof->id}/download")
            ->assertForbidden();
    }

    public function test_course_request_show_requires_admin_role(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

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

        $token = $student->createToken('test');

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson("/api/admin/course-requests/{$courseRequest->id}")
            ->assertForbidden();
    }

    public function test_mass_assignment_cannot_set_role_or_status(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        // Attempt mass assignment via registration
        $response = $this->post('/register', [
            'name' => 'Hacker',
            'email' => 'hacker-privilege@cwtacademy.local',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => UserRole::SUPER_ADMIN->value,
            'status' => UserStatus::ACTIVE->value,
            'captcha_answer' => 'skip',
        ]);

        $user = User::where('email', 'hacker-privilege@cwtacademy.local')->first();
        $this->assertNotNull($user);
        $this->assertEquals(UserRole::STUDENT->value, $user->role->value);
    }
}
