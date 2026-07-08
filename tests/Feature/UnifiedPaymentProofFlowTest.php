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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UnifiedPaymentProofFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function makeActiveCourse(int $price = 100000): Course
    {
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();

        return Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => $price,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function basePayload(Course $course, array $overrides = []): array
    {
        return array_merge([
            'course_id' => $course->id,
            'student_name' => 'Demo Student',
            'student_email' => 'demo@example.com',
            'student_phone' => '+9647501234567',
            'student_city' => 'Erbil',
            'payment_method' => 'FIB',
        ], $overrides);
    }

    public function test_form_creates_request_and_proof_in_one_submit(): void
    {
        Storage::fake('local');
        $course = $this->makeActiveCourse(150000);

        $file = $this->paymentProofFile();

        $response = $this->post(
            route('course-requests.store'),
            $this->basePayload($course, ['payment_proof' => $file]),
        );

        $courseRequest = CourseRequest::query()->firstOrFail();

        $response->assertRedirect(route('request.success', ['code' => $courseRequest->public_tracking_code]));

        $this->assertSame(CourseRequestStatus::PENDING_REVIEW, $courseRequest->status);
        $this->assertSame('FIB', $courseRequest->payment_method);

        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);
        $this->assertSame(150000, $proof->amount_iqd);
        $this->assertSame(PaymentProofStatus::PENDING, $proof->status);
        $this->assertNotNull($proof->proof_file_path);

        Storage::disk('local')->assertExists($proof->proof_file_path);
    }

    public function test_form_accepts_jpg_png_webp_pdf(): void
    {
        Storage::fake('local');
        $course = $this->makeActiveCourse();

        $cases = [
            ['name' => 'a.jpg', 'mime' => 'image/jpeg'],
            ['name' => 'b.png', 'mime' => 'image/png'],
            ['name' => 'c.webp', 'mime' => 'image/webp'],
            ['name' => 'd.pdf', 'mime' => 'application/pdf'],
        ];

        foreach ($cases as $i => $case) {
            $file = $this->paymentProofFile($case['mime'], $case['name']);

            $payload = $this->basePayload($course, [
                'student_email' => "u{$i}@example.com",
                'payment_proof' => $file,
            ]);

            $response = $this->post(route('course-requests.store'), $payload);

            $response->assertRedirect();
            $response->assertSessionDoesntHaveErrors(['payment_proof']);
        }

        $this->assertSame(4, PaymentProof::count());
    }

    public function test_form_rejects_invalid_mime(): void
    {
        $course = $this->makeActiveCourse();

        $file = UploadedFile::fake()->create('malicious.exe', 50, 'application/x-msdownload');

        $response = $this->post(
            route('course-requests.store'),
            $this->basePayload($course, ['payment_proof' => $file]),
        );

        $response->assertSessionHasErrors(['payment_proof']);
        $this->assertDatabaseCount('course_requests', 0);
        $this->assertDatabaseCount('payment_proofs', 0);
    }

    public function test_form_rejects_oversized_file(): void
    {
        $course = $this->makeActiveCourse();

        $file = $this->paymentProofFile('image/jpeg', 'huge.jpg', 6000); // 6 MB > 5 MB limit

        $response = $this->post(
            route('course-requests.store'),
            $this->basePayload($course, ['payment_proof' => $file]),
        );

        $response->assertSessionHasErrors(['payment_proof']);
        $this->assertDatabaseCount('course_requests', 0);
    }

    public function test_proof_is_stored_privately_on_local_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $course = $this->makeActiveCourse();
        $file = $this->paymentProofFile('image/png', 'receipt.png');

        $this->post(
            route('course-requests.store'),
            $this->basePayload($course, ['payment_proof' => $file]),
        );

        $proof = PaymentProof::firstOrFail();

        $this->assertNotNull($proof->proof_file_path);
        $this->assertStringStartsWith('payment_proofs/', $proof->proof_file_path);
        Storage::disk('local')->assertExists($proof->proof_file_path);
        Storage::disk('public')->assertMissing($proof->proof_file_path);
    }

    public function test_storage_directory_is_not_publicly_accessible(): void
    {
        $response = $this->get('/storage/payment_proofs/anything.jpg');
        $response->assertStatus(403);
    }

    public function test_admin_can_download_payment_proof_via_web_route(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $course = $this->makeActiveCourse();
        $this->post(
            route('course-requests.store'),
            $this->basePayload($course, ['payment_proof' => $this->paymentProofFile()]),
        );
        $proof = PaymentProof::firstOrFail();

        $response = $this->actingAs($admin)
            ->get(route('admin.payment-proofs.download', ['id' => $proof->id]));

        $response->assertOk();
    }

    public function test_guest_cannot_download_payment_proof_via_web_route(): void
    {
        Storage::fake('local');

        $course = $this->makeActiveCourse();
        $this->post(
            route('course-requests.store'),
            $this->basePayload($course, ['payment_proof' => $this->paymentProofFile()]),
        );
        $proof = PaymentProof::firstOrFail();

        $response = $this->get(route('admin.payment-proofs.download', ['id' => $proof->id]));

        $response->assertRedirect(route('login'));
    }

    public function test_student_cannot_download_payment_proof_via_web_route(): void
    {
        Storage::fake('local');

        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
        ]);

        $course = $this->makeActiveCourse();
        $this->post(
            route('course-requests.store'),
            $this->basePayload($course, ['payment_proof' => $this->paymentProofFile()]),
        );
        $proof = PaymentProof::firstOrFail();

        $response = $this->actingAs($student)
            ->get(route('admin.payment-proofs.download', ['id' => $proof->id]));

        $this->assertNotEquals(200, $response->status());
        $this->assertContains($response->status(), [302, 403]);
    }

    public function test_success_page_shows_full_unified_message(): void
    {
        Storage::fake('local');

        $course = $this->makeActiveCourse();
        $this->post(
            route('course-requests.store'),
            $this->basePayload($course, ['payment_proof' => $this->paymentProofFile()]),
        );
        $courseRequest = CourseRequest::firstOrFail();

        $response = $this->get(route('request.success', ['code' => $courseRequest->public_tracking_code]));

        $response->assertOk()
            ->assertSee($courseRequest->public_tracking_code)
            ->assertSee(__('request.success_submitted_body'))
            ->assertSee(__('request.status_waiting_admin_review'))
            ->assertSee($course->title);
    }

    public function test_form_renders_payment_methods_section(): void
    {
        $course = $this->makeActiveCourse();

        $response = $this->get("/courses/{$course->slug}/request");
        $response->assertOk()
            ->assertSee(__('request.payment_proof_title'))
            ->assertSee(__('request.payment_proof_body'))
            ->assertSee(__('request.method_fib'))
            ->assertSee(__('request.method_fastpay'))
            ->assertSee(__('request.method_card'))
            ->assertSee(__('request.upload_proof_label'))
            ->assertSee(__('request.upload_proof_helper'))
            ->assertSee(__('request.submit_request_button'));
    }
}
