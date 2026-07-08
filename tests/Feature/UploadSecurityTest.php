<?php

namespace Tests\Feature;

use App\Enums\CourseRequestStatus;
use App\Enums\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Models\Instructor;
use App\Models\PaymentProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_rejects_invalid_mime(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
        ]);

        $request = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_PAYMENT,
        ]);

        $file = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');

        $response = $this->postJson("/api/v1/course-requests/{$request->public_tracking_code}/payment-proof", [
            'amount_iqd' => 100000,
            'proof_file' => $file,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['proof_file']);
    }

    public function test_upload_rejects_oversized_file(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
        ]);

        $request = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_PAYMENT,
        ]);

        $file = $this->paymentProofFile('image/jpeg', 'large.jpg', 6000);

        $response = $this->postJson("/api/v1/course-requests/{$request->public_tracking_code}/payment-proof", [
            'amount_iqd' => 100000,
            'proof_file' => $file,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['proof_file']);
    }

    public function test_upload_uses_mime_derived_extension_not_client_filename(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
        ]);

        $request = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_PAYMENT,
        ]);

        $file = $this->paymentProofFile('image/jpeg', 'invoice.php.jpg');

        $response = $this->postJson("/api/v1/course-requests/{$request->public_tracking_code}/payment-proof", [
            'amount_iqd' => 100000,
            'proof_file' => $file,
        ]);

        $response->assertCreated();

        $proof = PaymentProof::query()->first();
        $this->assertNotNull($proof);
        $this->assertNotNull($proof->proof_file_path);
        $this->assertStringEndsWith('.jpg', $proof->proof_file_path);
        $this->assertStringNotContainsString('invoice.php', $proof->proof_file_path);
    }
}
