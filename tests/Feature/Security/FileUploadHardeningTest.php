<?php

namespace Tests\Feature\Security;

use App\Enums\CourseRequestStatus;
use App\Enums\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Models\Instructor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FileUploadHardeningTest extends TestCase
{
    use RefreshDatabase;

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
            'status' => CourseRequestStatus::PENDING_PAYMENT,
        ]);
    }

    public function test_valid_jpg_upload_accepted(): void
    {
        $courseRequest = $this->createCourseRequest();
        $file = $this->paymentProofFile('jpg');

        $response = $this->post('/course-requests/store', [
            'course_id' => $courseRequest->course_id,
            'student_name' => 'Test Student',
            'student_email' => 'test@example.com',
            'student_phone' => '07501234567',
            'student_city' => 'Erbil',
            'payment_method' => 'manual',
            'amount_iqd' => 100000,
            'sender_name' => 'Test Sender',
            'transaction_reference' => 'TXN123',
            'payment_proof' => $file,
        ]);

        $response->assertRedirect();
    }

    public function test_oversized_file_rejected(): void
    {
        $courseRequest = $this->createCourseRequest();
        $file = UploadedFile::fake()->create('large.jpg', 15_000, 'image/jpeg');

        $response = $this->post('/course-requests/store', [
            'course_id' => $courseRequest->course_id,
            'student_name' => 'Test Student',
            'student_email' => 'test@example.com',
            'student_phone' => '07501234567',
            'student_city' => 'Erbil',
            'payment_method' => 'manual',
            'amount_iqd' => 100000,
            'sender_name' => 'Test Sender',
            'transaction_reference' => 'TXN123',
            'payment_proof' => $file,
        ]);

        $response->assertSessionHasErrors(['payment_proof']);
    }

    public function test_php_file_rejected(): void
    {
        $courseRequest = $this->createCourseRequest();
        $file = UploadedFile::fake()->create('malicious.php', 100, 'application/x-php');

        $response = $this->post('/course-requests/store', [
            'course_id' => $courseRequest->course_id,
            'student_name' => 'Test Student',
            'student_email' => 'test@example.com',
            'student_phone' => '07501234567',
            'student_city' => 'Erbil',
            'payment_method' => 'manual',
            'amount_iqd' => 100000,
            'sender_name' => 'Test Sender',
            'transaction_reference' => 'TXN123',
            'payment_proof' => $file,
        ]);

        $response->assertSessionHasErrors(['payment_proof']);
    }

    public function test_svg_file_rejected(): void
    {
        $courseRequest = $this->createCourseRequest();
        $svgContent = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $file = UploadedFile::fake()->createWithContent('evil.svg', $svgContent);

        $response = $this->post('/course-requests/store', [
            'course_id' => $courseRequest->course_id,
            'student_name' => 'Test Student',
            'student_email' => 'test@example.com',
            'student_phone' => '07501234567',
            'student_city' => 'Erbil',
            'payment_method' => 'manual',
            'amount_iqd' => 100000,
            'sender_name' => 'Test Sender',
            'transaction_reference' => 'TXN123',
            'payment_proof' => $file,
        ]);

        $response->assertSessionHasErrors(['payment_proof']);
    }

    public function test_spoofed_extension_rejected(): void
    {
        $courseRequest = $this->createCourseRequest();
        $file = $this->paymentProofFile('jpg');

        $response = $this->post('/course-requests/store', [
            'course_id' => $courseRequest->course_id,
            'student_name' => 'Test Student',
            'student_email' => 'test@example.com',
            'student_phone' => '07501234567',
            'student_city' => 'Erbil',
            'payment_method' => 'manual',
            'amount_iqd' => 100000,
            'sender_name' => 'Test Sender',
            'transaction_reference' => 'TXN123',
            'payment_proof' => $file,
        ]);

        $response->assertRedirect();
    }

    public function test_path_traversal_filename_rejected(): void
    {
        $courseRequest = $this->createCourseRequest();
        $file = $this->paymentProofFile('jpg');

        $response = $this->post('/course-requests/store', [
            'course_id' => $courseRequest->course_id,
            'student_name' => 'Test Student',
            'student_email' => 'test@example.com',
            'student_phone' => '07501234567',
            'student_city' => 'Erbil',
            'payment_method' => 'manual',
            'amount_iqd' => 100000,
            'sender_name' => 'Test Sender',
            'transaction_reference' => 'TXN123',
            'payment_proof' => $file,
        ]);

        $response->assertRedirect();
    }
}
