<?php

namespace Tests\Feature;

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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageSecurityTest extends TestCase
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

    protected function createFinanceManager(): User
    {
        return User::factory()->create([
            'role' => UserRole::FINANCE_MANAGER,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    protected function createStudent(): User
    {
        return User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    protected function seedProof(): PaymentProof
    {
        Storage::fake('local');

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

        $file = $this->paymentProofFile();

        $proof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
            'proof_file_path' => $file->storeAs('payment_proofs', 'proof_test.jpg', 'local'),
            'proof_mime' => 'image/jpeg',
        ]);

        return $proof;
    }

    public function test_admin_can_download_payment_proof(): void
    {
        $admin = $this->createAdmin();
        $proof = $this->seedProof();

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/admin/payment-proofs/{$proof->id}/download");

        $response->assertOk();
    }

    public function test_finance_manager_can_download_payment_proof(): void
    {
        $manager = $this->createFinanceManager();
        $proof = $this->seedProof();

        $token = $manager->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/admin/payment-proofs/{$proof->id}/download");

        $response->assertOk();
    }

    public function test_student_cannot_download_payment_proof(): void
    {
        $student = $this->createStudent();
        $proof = $this->seedProof();

        $token = $student->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/admin/payment-proofs/{$proof->id}/download");

        $response->assertForbidden();
    }

    public function test_guest_cannot_download_payment_proof(): void
    {
        $proof = $this->seedProof();

        $response = $this->get("/api/admin/payment-proofs/{$proof->id}/download");

        $response->assertUnauthorized();
    }

    public function test_payment_proofs_are_stored_on_local_disk(): void
    {
        Storage::fake('local');

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

        $file = $this->paymentProofFile();

        $this->postJson("/api/v1/course-requests/{$courseRequest->public_tracking_code}/payment-proof", [
            'amount_iqd' => 100000,
            'proof_file' => $file,
        ]);

        $proof = PaymentProof::first();
        $this->assertNotNull($proof);
        $this->assertNotNull($proof->proof_file_path);
        Storage::disk('local')->assertExists($proof->proof_file_path);
    }

    public function test_public_cannot_access_storage_directory(): void
    {
        $response = $this->get('/storage/payment_proofs/test.jpg');

        $response->assertStatus(403);
    }

    public function test_admin_can_download_payment_proof_from_r2_disk(): void
    {
        Storage::fake('r2');
        config()->set('filesystems.disks.r2.bucket', 'cwt-payment-proofs');

        $admin = $this->createAdmin();
        $proof = $this->seedR2Proof();

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/admin/payment-proofs/{$proof->id}/download");

        $response->assertOk();
        $this->assertNotNull($proof->proof_file_path);
        Storage::disk('r2')->assertExists($proof->proof_file_path);
    }

    public function test_web_admin_can_download_payment_proof_from_r2_disk(): void
    {
        Storage::fake('r2');
        config()->set('filesystems.disks.r2.bucket', 'cwt-payment-proofs');

        $admin = $this->createAdmin();
        $proof = $this->seedR2Proof();

        $response = $this->actingAs($admin)
            ->get(route('admin.payment-proofs.download', $proof->id));

        $response->assertOk();
    }

    public function test_missing_r2_file_returns_404(): void
    {
        Storage::fake('r2');
        config()->set('filesystems.disks.r2.bucket', 'cwt-payment-proofs');

        $admin = $this->createAdmin();
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

        $proof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
            'proof_file_path' => 'payment_proofs/missing-on-r2.jpg',
            'proof_mime' => 'image/jpeg',
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/admin/payment-proofs/{$proof->id}/download");

        $response->assertNotFound();
    }

    public function test_path_traversal_attempt_fails(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin();
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

        $proof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
            'proof_file_path' => '../../../etc/passwd',
            'proof_mime' => 'image/jpeg',
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/admin/payment-proofs/{$proof->id}/download");

        $response->assertNotFound();
    }

    protected function seedR2Proof(): PaymentProof
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

        $file = $this->paymentProofFile();

        $path = $file->storeAs('payment_proofs', 'proof_r2.jpg', 'r2');

        return PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
            'proof_file_path' => $path,
            'proof_mime' => 'image/jpeg',
        ]);
    }
}
