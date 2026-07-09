<?php

namespace Tests\Feature;

use App\Enums\CourseRequestStatus;
use App\Enums\CourseStatus;
use App\Enums\PaymentProofStatus;
use App\Enums\TelegramAccessGrantStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Models\Instructor;
use App\Models\PaymentProof;
use App\Models\User;
use App\Services\Captcha\MathCaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseRequestStudentAdminFlowTest extends TestCase
{
    use RefreshDatabase;

    private function createPublishedCourse(int $priceIqd = 100000): Course
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();

        return Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => $priceIqd,
        ]);
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    private function createStudent(): User
    {
        return User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    private function submitCourseRequest(Course $course, UploadedFile $file, string $email = 'test.student@example.com'): CourseRequest
    {
        $response = $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => $email,
            'student_phone' => '07500000000',
            'student_city' => 'Erbil',
            'payment_method' => 'FIB',
            'amount_iqd' => $course->price_iqd,
            'transaction_reference' => 'REF-'.uniqid(),
            'payment_proof' => $file,
        ]);

        $response->assertRedirect();

        $courseRequest = CourseRequest::query()->where('course_id', $course->id)->first();
        $this->assertNotNull($courseRequest);

        return $courseRequest;
    }

    public function test_student_can_submit_course_request_with_payment_proof(): void
    {
        Storage::fake('local');
        $course = $this->createPublishedCourse();
        $file = $this->paymentProofFile();

        $courseRequest = $this->submitCourseRequest($course, $file);

        $this->assertDatabaseHas('course_requests', [
            'id' => $courseRequest->id,
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_REVIEW->value,
        ]);

        $this->assertDatabaseHas('payment_proofs', [
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => $course->price_iqd,
            'status' => PaymentProofStatus::PENDING->value,
        ]);

        $this->assertNotEmpty($courseRequest->public_tracking_code);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{16}$/', $courseRequest->public_tracking_code);
    }

    public function test_student_can_view_tracking_page_after_submission(): void
    {
        Storage::fake('local');
        $course = $this->createPublishedCourse();
        $courseRequest = $this->submitCourseRequest($course, $this->paymentProofFile());

        $codeOnlyResponse = $this->get(route('track', ['code' => $courseRequest->public_tracking_code]));
        $codeOnlyResponse->assertOk();
        $codeOnlyResponse->assertSee($course->title);
        $codeOnlyResponse->assertSee($courseRequest->public_tracking_code);
        $codeOnlyResponse->assertSee('tracking-status', false);
        $codeOnlyResponse->assertViewHas('requestData', function (array $data) {
            return $data['status'] === 'PENDING_REVIEW';
        });
        $codeOnlyResponse->assertDontSee('payment_proof_status');

        $emailHash = hash('sha256', $courseRequest->student_email);
        $fullResponse = $this->get(route('track', [
            'code' => $courseRequest->public_tracking_code,
            'email_hash' => $emailHash,
        ]));
        $fullResponse->assertOk();
        $fullResponse->assertSee($course->title);
        $fullResponse->assertSee($courseRequest->public_tracking_code);
    }

    public function test_admin_can_approve_course_request(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $course = $this->createPublishedCourse();
        $courseRequest = $this->submitCourseRequest($course, $this->paymentProofFile());
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);

        $this->actingAs($admin);

        $response = $this->post(route('admin.requests.approve', $courseRequest->id), [
            'payment_proof_id' => $proof->id,
        ]);

        $response->assertRedirect()
            ->assertSessionHas('success', __('admin.request_approved'));

        $this->assertDatabaseHas('course_requests', [
            'id' => $courseRequest->id,
            'status' => CourseRequestStatus::APPROVED->value,
            'approved_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('payment_proofs', [
            'id' => $proof->id,
            'status' => PaymentProofStatus::APPROVED->value,
            'reviewed_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('telegram_access_grants', [
            'course_request_id' => $courseRequest->id,
            'course_id' => $course->id,
            'status' => TelegramAccessGrantStatus::PENDING_MANUAL_ADD->value,
        ]);

        $emailHash = hash('sha256', $courseRequest->student_email);
        $tracking = $this->get(route('track', [
            'code' => $courseRequest->public_tracking_code,
            'email_hash' => $emailHash,
        ]));
        $tracking->assertOk()
            ->assertViewHas('requestData', function (array $data) {
                return $data['status'] === 'APPROVED';
            })
            ->assertSee('telegram-manual-instructions', false);
    }

    public function test_admin_can_reject_course_request(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $course = $this->createPublishedCourse();
        $courseRequest = $this->submitCourseRequest($course, $this->paymentProofFile());

        $this->actingAs($admin);

        $rejectionReason = 'Payment proof is unclear';
        $response = $this->post(route('admin.requests.reject', $courseRequest->id), [
            'rejection_reason' => $rejectionReason,
        ]);

        $response->assertRedirect()
            ->assertSessionHas('success', __('admin.request_rejected'));

        $this->assertDatabaseHas('course_requests', [
            'id' => $courseRequest->id,
            'status' => CourseRequestStatus::REJECTED->value,
            'rejected_by' => $admin->id,
        ]);

        $this->assertDatabaseMissing('telegram_access_grants', [
            'course_request_id' => $courseRequest->id,
        ]);

        $emailHash = hash('sha256', $courseRequest->student_email);
        $tracking = $this->get(route('track', [
            'code' => $courseRequest->public_tracking_code,
            'email_hash' => $emailHash,
        ]));
        $tracking->assertOk()
            ->assertViewHas('requestData', function (array $data) use ($rejectionReason) {
                return $data['status'] === 'REJECTED'
                    && ($data['public_rejection_note'] ?? '') === $rejectionReason;
            })
            ->assertSee($rejectionReason)
            ->assertDontSee('telegram-manual-instructions');
    }

    public function test_unauthorized_user_cannot_approve_or_reject(): void
    {
        Storage::fake('local');
        $course = $this->createPublishedCourse();
        $courseRequest = $this->submitCourseRequest($course, $this->paymentProofFile());
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);

        $guestApprove = $this->post(route('admin.requests.approve', $courseRequest->id), [
            'payment_proof_id' => $proof->id,
        ]);
        $guestApprove->assertRedirect('/login');

        $guestReject = $this->post(route('admin.requests.reject', $courseRequest->id), [
            'rejection_reason' => 'Should not work',
        ]);
        $guestReject->assertRedirect('/login');

        $student = $this->createStudent();
        $this->actingAs($student);

        $studentApprove = $this->post(route('admin.requests.approve', $courseRequest->id), [
            'payment_proof_id' => $proof->id,
        ]);
        $studentApprove->assertRedirect('/dashboard');

        $studentReject = $this->post(route('admin.requests.reject', $courseRequest->id), [
            'rejection_reason' => 'Should not work',
        ]);
        $studentReject->assertRedirect('/dashboard');

        $this->assertDatabaseHas('course_requests', [
            'id' => $courseRequest->id,
            'status' => CourseRequestStatus::PENDING_REVIEW->value,
        ]);
    }

    public function test_duplicate_pending_course_request_returns_existing_tracking_code(): void
    {
        Storage::fake('local');
        $course = $this->createPublishedCourse();
        $file = $this->paymentProofFile();

        $firstRequest = $this->submitCourseRequest($course, $file, 'duplicate@example.com');

        $secondResponse = $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => 'Another Student',
            'student_email' => 'duplicate@example.com',
            'student_phone' => '07509999999',
            'student_city' => 'Sulaymaniyah',
            'payment_proof' => $this->paymentProofFile(),
        ]);

        $secondResponse->assertRedirect();
        $this->assertDatabaseCount('course_requests', 1);

        $onlyRequest = CourseRequest::query()->first();
        $this->assertNotNull($onlyRequest);
        $this->assertSame($firstRequest->public_tracking_code, $onlyRequest->public_tracking_code);
    }

    public function test_payment_proof_amount_mismatch_is_blocked(): void
    {
        Storage::fake('local');
        $course = $this->createPublishedCourse(100000);
        $file = $this->paymentProofFile();

        $response = $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'mismatch@example.com',
            'student_phone' => '07500000000',
            'student_city' => 'Erbil',
            'amount_iqd' => 50000,
            'payment_proof' => $file,
        ]);

        $response->assertSessionHasErrors('amount_iqd');
        $this->assertDatabaseCount('course_requests', 0);
        $this->assertDatabaseCount('payment_proofs', 0);
        $this->assertDatabaseMissing('telegram_access_grants', [
            'course_id' => $course->id,
        ]);
    }

    public function test_payment_proof_download_authorization(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $course = $this->createPublishedCourse();
        $courseRequest = $this->submitCourseRequest($course, $this->paymentProofFile());
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);

        $guestDownload = $this->get(route('admin.payment-proofs.download', $proof->id));
        $guestDownload->assertRedirect('/login');

        $student = $this->createStudent();
        $this->actingAs($student);
        $studentDownload = $this->get(route('admin.payment-proofs.download', $proof->id));
        $studentDownload->assertRedirect('/dashboard');

        $this->actingAs($admin);
        $adminDownload = $this->get(route('admin.payment-proofs.download', $proof->id));
        $adminDownload->assertOk();
        $adminDownload->assertHeader('content-disposition');
    }

    public function test_payment_proof_download_returns_404_for_missing_file(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $course = $this->createPublishedCourse();
        $courseRequest = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);
        $proof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'proof_file_path' => 'payment_proofs/missing-file.jpg',
        ]);

        $this->actingAs($admin);
        $response = $this->get(route('admin.payment-proofs.download', $proof->id));
        $response->assertNotFound();
    }

    public function test_manual_telegram_workflow_is_preserved(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $course = $this->createPublishedCourse();
        $courseRequest = $this->submitCourseRequest($course, $this->paymentProofFile());
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);

        $this->actingAs($admin);
        $this->post(route('admin.requests.approve', $courseRequest->id), [
            'payment_proof_id' => $proof->id,
        ])->assertSessionHas('success');

        $grant = $courseRequest->fresh()?->telegramAccessGrant;
        $this->assertNotNull($grant);
        $this->assertEquals(TelegramAccessGrantStatus::PENDING_MANUAL_ADD, $grant->status);

        $this->assertDatabaseMissing('telegram_access_grants', [
            'course_request_id' => $courseRequest->id,
            'status' => 'AUTO_INVITE_SENT',
        ]);

        $emailHash = hash('sha256', $courseRequest->student_email);
        $tracking = $this->get(route('track', [
            'code' => $courseRequest->public_tracking_code,
            'email_hash' => $emailHash,
        ]));
        $tracking->assertOk()
            ->assertSee('telegram-manual-instructions', false)
            ->assertDontSee('Auto invite');
    }

    public function test_math_captcha_blocks_invalid_answer_when_enabled(): void
    {
        Storage::fake('local');
        Config::set('security.captcha.driver', 'math');

        $course = $this->createPublishedCourse();
        $file = $this->paymentProofFile();

        app(MathCaptchaService::class)->generate();

        $response = $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => 'Test Student',
            'student_email' => 'captcha@example.com',
            'student_phone' => '07500000000',
            'student_city' => 'Erbil',
            'payment_proof' => $file,
            'captcha_answer' => '9999',
        ]);

        $response->assertSessionHasErrors('captcha_answer');
        $this->assertDatabaseCount('course_requests', 0);
    }
}
