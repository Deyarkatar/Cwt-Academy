<?php

namespace Tests\Feature\Security;

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

class FileDownloadAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function createProof(): PaymentProof
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

        return PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'proof_file_path' => 'payment_proofs/test-proof.jpg',
        ]);
    }

    public function test_guest_cannot_download_payment_proof_web(): void
    {
        $proof = $this->createProof();
        $this->get("/admin/payment-proofs/{$proof->id}/download")->assertRedirect('/login');
    }

    public function test_student_cannot_download_payment_proof_web(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $proof = $this->createProof();

        $this->actingAs($student)
            ->get("/admin/payment-proofs/{$proof->id}/download")
            ->assertRedirect('/dashboard');
    }

    public function test_guest_cannot_download_payment_proof_api(): void
    {
        $proof = $this->createProof();
        $this->getJson("/api/admin/payment-proofs/{$proof->id}/download")->assertUnauthorized();
    }

    public function test_student_cannot_download_payment_proof_api(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $proof = $this->createProof();
        $token = $student->createToken('test');

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson("/api/admin/payment-proofs/{$proof->id}/download")
            ->assertForbidden();
    }

    public function test_nonexistent_proof_returns_404(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $token = $admin->createToken('admin', ['admin']);

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson('/api/admin/payment-proofs/99999/download')
            ->assertNotFound();
    }
}
