<?php

namespace Tests\Feature\Security;

use App\Enums\CourseRequestStatus;
use App\Enums\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Models\Instructor;
use App\Models\PaymentProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentProofWorkflowSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_payment_proof_upload_requires_email_hash(): void
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
            'status' => CourseRequestStatus::PENDING_PAYMENT,
            'student_email' => 'student@example.com',
        ]);

        $file = $this->paymentProofFile('jpg');

        $response = $this->postJson("/api/v1/course-requests/{$courseRequest->public_tracking_code}/payment-proof", [
            'proof_file' => $file,
            'amount_iqd' => 100000,
            'sender_name' => 'Test Student',
            'transaction_reference' => 'TXN123',
        ]);

        $response->assertNotFound();
    }

    public function test_api_payment_proof_upload_with_wrong_email_hash_rejected(): void
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
            'status' => CourseRequestStatus::PENDING_PAYMENT,
            'student_email' => 'real@example.com',
        ]);

        $file = $this->paymentProofFile('jpg');
        $wrongHash = hash('sha256', 'wrong@example.com');

        $response = $this->postJson("/api/v1/course-requests/{$courseRequest->public_tracking_code}/payment-proof", [
            'proof_file' => $file,
            'amount_iqd' => 100000,
            'sender_name' => 'Test Student',
            'transaction_reference' => 'TXN123',
            'email_hash' => $wrongHash,
        ]);

        $response->assertNotFound();
    }

    public function test_api_payment_proof_upload_with_correct_email_hash_accepted(): void
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
            'status' => CourseRequestStatus::PENDING_PAYMENT,
            'student_email' => 'real@example.com',
        ]);

        $file = $this->paymentProofFile('jpg');
        $correctHash = hash('sha256', strtolower(trim('real@example.com')));

        $response = $this->postJson("/api/v1/course-requests/{$courseRequest->public_tracking_code}/payment-proof", [
            'proof_file' => $file,
            'amount_iqd' => 100000,
            'sender_name' => 'Test Student',
            'transaction_reference' => 'TXN123',
            'email_hash' => $correctHash,
        ]);

        $response->assertCreated();
    }

    public function test_approved_request_rejects_new_proof_upload(): void
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
            'status' => CourseRequestStatus::APPROVED,
            'student_email' => 'approved@example.com',
        ]);

        $file = $this->paymentProofFile('jpg');
        $correctHash = hash('sha256', strtolower(trim('approved@example.com')));

        $response = $this->postJson("/api/v1/course-requests/{$courseRequest->public_tracking_code}/payment-proof", [
            'proof_file' => $file,
            'amount_iqd' => 100000,
            'sender_name' => 'Test Student',
            'transaction_reference' => 'TXN123',
            'email_hash' => $correctHash,
        ]);

        $response->assertStatus(422);
    }

    public function test_nonexistent_tracking_code_returns_404(): void
    {
        $file = $this->paymentProofFile('jpg');

        $response = $this->postJson('/api/v1/course-requests/INVALIDCODE12345/payment-proof', [
            'proof_file' => $file,
            'amount_iqd' => 100000,
            'sender_name' => 'Test',
            'transaction_reference' => 'TXN',
            'email_hash' => hash('sha256', 'test@example.com'),
        ]);

        $response->assertNotFound();
    }

    public function test_virus_scan_status_is_pending_on_upload(): void
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
            'status' => CourseRequestStatus::PENDING_PAYMENT,
            'student_email' => 'scan@example.com',
        ]);

        $file = $this->paymentProofFile('jpg');
        $correctHash = hash('sha256', strtolower(trim('scan@example.com')));

        $response = $this->postJson("/api/v1/course-requests/{$courseRequest->public_tracking_code}/payment-proof", [
            'proof_file' => $file,
            'amount_iqd' => 100000,
            'sender_name' => 'Test Student',
            'transaction_reference' => 'TXN123',
            'email_hash' => $correctHash,
        ]);

        $response->assertCreated();

        $proof = PaymentProof::where('course_request_id', $courseRequest->id)->latest()->first();
        $this->assertNotNull($proof);
        $this->assertEquals('pending', $proof->virus_scan_status);
    }
}
