<?php

namespace Tests\Feature;

use App\Enums\CourseRequestStatus;
use App\Enums\CourseStatus;
use App\Enums\PaymentProofStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Models\Instructor;
use App\Models\PaymentProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_course_request_via_web_form(): void
    {
        Storage::fake('local');

        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => 100000,
        ]);

        $file = $this->paymentProofFile();

        $response = $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'student@example.com',
            'student_phone' => '+9647501234567',
            'student_city' => 'Erbil',
            'student_note' => 'Test note',
            'payment_method' => 'FIB',
            'payment_proof' => $file,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('course_requests', [
            'course_id' => $course->id,
            'student_city' => 'Erbil',
            'payment_method' => 'FIB',
            'status' => CourseRequestStatus::PENDING_REVIEW->value,
        ]);

        $createdRequest = CourseRequest::query()->where('course_id', $course->id)->first();
        $this->assertNotNull($createdRequest);
        $this->assertEquals('student@example.com', $createdRequest->student_email);
        $this->assertEquals('Test Student', $createdRequest->student_name);

        $this->assertDatabaseHas('payment_proofs', [
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING->value,
        ]);
    }

    public function test_web_form_redirects_to_success_page_with_tracking_code(): void
    {
        Storage::fake('local');

        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => 100000,
        ]);

        $file = $this->paymentProofFile('image/png', 'receipt.png');

        $response = $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'student@example.com',
            'student_phone' => '+9647501234567',
            'student_city' => 'Sulaymaniyah',
            'payment_proof' => $file,
        ]);

        $courseRequest = CourseRequest::query()->first();
        $this->assertNotNull($courseRequest);

        $response->assertRedirect(route('request.success', ['code' => $courseRequest->public_tracking_code]));
    }

    public function test_success_page_shows_tracking_code_and_status_when_session_bound(): void
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
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);

        session(['latest_course_request.'.$course->id => $courseRequest->public_tracking_code]);

        $response = $this->get(route('request.success', ['code' => $courseRequest->public_tracking_code]));

        $response->assertOk()
            ->assertSee($courseRequest->public_tracking_code)
            ->assertSee(__('request.your_tracking_code'))
            ->assertSee(__('request.success_submitted_body'))
            ->assertSee(__('request.status_waiting_admin_review'));
    }

    public function test_success_page_redirects_to_track_without_session_binding(): void
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
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);

        $response = $this->get(route('request.success', ['code' => $courseRequest->public_tracking_code]));

        $response->assertRedirect(route('track', ['code' => $courseRequest->public_tracking_code]));
    }

    public function test_student_can_upload_payment_proof_via_web_form(): void
    {
        Storage::fake('local');

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
        ]);

        $file = $this->paymentProofFile();

        $response = $this->post(
            route('payment-proof.store', ['code' => $courseRequest->public_tracking_code]),
            [
                'amount_iqd' => 100000,
                'sender_name' => 'Test Sender',
                'transaction_reference' => 'REF-12345',
                'proof_file' => $file,
            ]
        );

        $response->assertRedirect(route('track', ['code' => $courseRequest->public_tracking_code]));

        $this->assertDatabaseHas('payment_proofs', [
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING->value,
        ]);

        $proof = PaymentProof::query()->where('course_request_id', $courseRequest->id)->first();
        $this->assertNotNull($proof);
        $this->assertEquals('Test Sender', $proof->sender_name);
    }

    public function test_web_upload_rejects_invalid_mime(): void
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

        $file = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');

        $response = $this->post(
            route('payment-proof.store', ['code' => $courseRequest->public_tracking_code]),
            [
                'amount_iqd' => 100000,
                'proof_file' => $file,
            ]
        );

        $response->assertSessionHasErrors(['proof_file']);
    }

    public function test_web_upload_rejects_oversized_file(): void
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

        $file = $this->paymentProofFile('image/jpeg', 'large.jpg', 6000);

        $response = $this->post(
            route('payment-proof.store', ['code' => $courseRequest->public_tracking_code]),
            [
                'amount_iqd' => 100000,
                'proof_file' => $file,
            ]
        );

        $response->assertSessionHasErrors(['proof_file']);
    }

    public function test_web_form_validates_required_fields(): void
    {
        $response = $this->post(route('course-requests.store'), []);
        $response->assertSessionHasErrors([
            'course_id', 'student_name', 'student_email',
            'student_phone', 'student_city', 'payment_proof',
        ]);
    }

    public function test_web_form_requires_city(): void
    {
        Storage::fake('local');

        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
        ]);

        $response = $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'student@example.com',
            'student_phone' => '+9647501234567',
            'payment_proof' => $this->paymentProofFile(),
            // student_city missing
        ]);

        $response->assertSessionHasErrors(['student_city']);
    }

    public function test_web_form_requires_payment_proof(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
        ]);

        $response = $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'student@example.com',
            'student_phone' => '+9647501234567',
            'student_city' => 'Erbil',
            // payment_proof missing
        ]);

        $response->assertSessionHasErrors(['payment_proof']);
        $this->assertDatabaseCount('course_requests', 0);
        $this->assertDatabaseCount('payment_proofs', 0);
    }

    public function test_web_upload_requires_amount_and_file(): void
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

        $response = $this->post(
            route('payment-proof.store', ['code' => $courseRequest->public_tracking_code]),
            []
        );

        $response->assertSessionHasErrors(['amount_iqd', 'proof_file']);
    }

    public function test_invalid_tracking_code_returns_not_found(): void
    {
        $response = $this->get(route('request.success', ['code' => 'INVALID123']));
        $response->assertNotFound();
    }

    public function test_course_detail_page_does_not_show_tracking_box_before_request(): void
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
        ]);

        $response = $this->get('/courses/'.$course->slug);

        $response->assertOk();
        $response->assertDontSee(__('course.request_submitted'));
        $response->assertDontSeeText('course-tracking-code');
    }

    public function test_course_detail_page_shows_tracking_box_after_request(): void
    {
        Storage::fake('local');

        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => 100000,
        ]);

        $file = $this->paymentProofFile();

        $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'student@example.com',
            'student_phone' => '+9647501234567',
            'student_city' => 'Erbil',
            'payment_proof' => $file,
        ]);

        $courseRequest = CourseRequest::query()->first();
        $this->assertNotNull($courseRequest);

        $response = $this->get('/courses/'.$course->slug);
        $response->assertOk();
        $response->assertSee(__('course.request_submitted'));
        $response->assertSee($courseRequest->public_tracking_code);
        $response->assertSee(__('course.track_request'));
        $response->assertSee(route('track', ['code' => $courseRequest->public_tracking_code]));
    }

    public function test_tracking_code_is_stored_per_course_in_session(): void
    {
        Storage::fake('local');

        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $courseA = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => 100000,
        ]);
        $courseB = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => 100000,
        ]);

        $file = $this->paymentProofFile();

        $this->post(route('course-requests.store'), [
            'course_id' => $courseA->id,
            'student_name' => 'Test Student',
            'student_email' => 'student@example.com',
            'student_phone' => '+9647501234567',
            'student_city' => 'Erbil',
            'payment_proof' => $file,
        ]);

        $courseRequestA = CourseRequest::query()->where('course_id', $courseA->id)->first();
        $this->assertNotNull($courseRequestA);

        // Course A detail page should show tracking card
        $responseA = $this->get('/courses/'.$courseA->slug);
        $responseA->assertOk();
        $responseA->assertSee($courseRequestA->public_tracking_code);

        // Course B detail page should NOT show tracking card
        $responseB = $this->get('/courses/'.$courseB->slug);
        $responseB->assertOk();
        $responseB->assertDontSee(__('course.request_submitted'));
        $responseB->assertDontSee($courseRequestA->public_tracking_code);
    }

    public function test_tracking_box_does_not_expose_private_data(): void
    {
        Storage::fake('local');

        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => 100000,
        ]);

        $file = $this->paymentProofFile();

        $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'student@example.com',
            'student_phone' => '+9647501234567',
            'student_city' => 'Erbil',
            'payment_proof' => $file,
        ]);

        $courseRequest = CourseRequest::query()->first();
        $this->assertNotNull($courseRequest);

        $response = $this->get('/courses/'.$course->slug);
        $response->assertOk();

        // Should NOT expose admin notes, rejection reason, or file path
        $response->assertDontSee($courseRequest->rejection_reason ?? 'n/a-placeholder');
        if ($courseRequest->latestPaymentProof) {
            $proofPath = $courseRequest->latestPaymentProof->proof_file_path;
            $this->assertNotNull($proofPath);
            $response->assertDontSee($proofPath);
        }
    }
}
