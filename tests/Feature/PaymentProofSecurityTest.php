<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CourseRequestStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentProofSecurityTest extends TestCase
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

    protected function seedPendingRequest(): CourseRequest
    {
        $course = Course::factory()->create();

        return CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_PAYMENT,
            'student_email' => 'student@example.com',
            'public_tracking_code' => 'TEST1234CODE5678',
        ]);
    }

    public function test_payment_proof_upload_without_email_hash_is_rejected(): void
    {
        Storage::fake('local');
        $this->seedPendingRequest();

        $file = $this->paymentProofFile();

        $response = $this->postJson('/api/v1/course-requests/TEST1234CODE5678/payment-proof', [
            'amount_iqd' => 100000,
            'proof_file' => $file,
        ]);

        $response->assertNotFound();
    }

    public function test_payment_proof_upload_with_wrong_email_hash_is_rejected(): void
    {
        Storage::fake('local');
        $this->seedPendingRequest();

        $file = $this->paymentProofFile();
        $wrongHash = hash('sha256', 'wrong@example.com');

        $response = $this->postJson('/api/v1/course-requests/TEST1234CODE5678/payment-proof', [
            'amount_iqd' => 100000,
            'email_hash' => $wrongHash,
            'proof_file' => $file,
        ]);

        $response->assertNotFound();
    }

    public function test_payment_proof_upload_with_correct_email_hash_succeeds(): void
    {
        Storage::fake('local');
        $request = $this->seedPendingRequest();

        $file = $this->paymentProofFile();
        $correctHash = hash('sha256', strtolower(trim('student@example.com')));

        $response = $this->postJson('/api/v1/course-requests/TEST1234CODE5678/payment-proof', [
            'amount_iqd' => 100000,
            'email_hash' => $correctHash,
            'proof_file' => $file,
        ]);

        $response->assertCreated();
    }

    public function test_payment_proof_upload_for_nonexistent_tracking_code_returns_404(): void
    {
        Storage::fake('local');
        $file = $this->paymentProofFile();

        $response = $this->postJson('/api/v1/course-requests/NONEXIST12345678/payment-proof', [
            'amount_iqd' => 100000,
            'email_hash' => hash('sha256', 'test@example.com'),
            'proof_file' => $file,
        ]);

        $response->assertNotFound();
    }

    public function test_student_cannot_download_payment_proof(): void
    {
        Storage::fake('local');
        $student = $this->createStudent();
        $proof = PaymentProof::factory()->create([
            'proof_file_path' => 'payment_proofs/test.jpg',
            'proof_mime' => 'image/jpeg',
        ]);

        $token = $student->createToken('test', ['admin'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/admin/payment-proofs/{$proof->id}/download");

        $response->assertForbidden();
    }

    public function test_guest_cannot_download_payment_proof(): void
    {
        Storage::fake('local');
        $proof = PaymentProof::factory()->create([
            'proof_file_path' => 'payment_proofs/test.jpg',
            'proof_mime' => 'image/jpeg',
        ]);

        $response = $this->getJson("/api/admin/payment-proofs/{$proof->id}/download");

        $response->assertUnauthorized();
    }

    public function test_admin_can_download_payment_proof(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();

        $file = $this->paymentProofFile();
        $path = $file->storeAs('payment_proofs', 'proof_test.jpg', 'local');

        $proof = PaymentProof::factory()->create([
            'proof_file_path' => $path,
            'proof_mime' => 'image/jpeg',
        ]);

        $token = $admin->createToken('admin', ['admin'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/admin/payment-proofs/{$proof->id}/download");

        $response->assertOk();
    }

    public function test_path_traversal_in_proof_file_path_returns_404(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();

        $proof = PaymentProof::factory()->create([
            'proof_file_path' => '../../../etc/passwd',
            'proof_mime' => 'image/jpeg',
        ]);

        $token = $admin->createToken('admin', ['admin'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/admin/payment-proofs/{$proof->id}/download");

        $response->assertNotFound();
    }

    public function test_virus_scan_status_is_set_on_proof_creation(): void
    {
        Storage::fake('local');
        $request = $this->seedPendingRequest();
        $file = $this->paymentProofFile();
        $correctHash = hash('sha256', strtolower(trim('student@example.com')));

        $response = $this->postJson('/api/v1/course-requests/TEST1234CODE5678/payment-proof', [
            'amount_iqd' => 100000,
            'email_hash' => $correctHash,
            'proof_file' => $file,
        ]);

        $response->assertCreated();
        $proof = PaymentProof::first();
        $this->assertNotNull($proof);
        $this->assertSame('pending', $proof->virus_scan_status);
    }
}
