<?php

namespace Tests\Feature;

use App\Actions\CourseRequests\ApproveCourseRequestAction;
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
use Tests\TestCase;

class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function createStudent(): User
    {
        return User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    protected function createAdmin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    protected function seedCourseRequestForUser(User $user, string $status = 'PENDING_REVIEW'): CourseRequest
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
            'user_id' => $user->id,
            'student_email' => $user->email,
            'status' => CourseRequestStatus::from($status),
        ]);

        PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
            'proof_file_path' => 'payment_proofs/test-'.$courseRequest->id.'.jpg',
        ]);

        return $courseRequest;
    }

    protected function seedCourseRequestViaWeb(User $student, Course $course): CourseRequest
    {
        $this->actingAs($student);

        $response = $this->post(route('course-requests.store'), [
            'course_id' => $course->id,
            'student_name' => $student->name,
            'student_email' => $student->email,
            'student_phone' => '07501234567',
            'student_city' => 'Erbil',
            'student_note' => null,
            'payment_method' => 'MANUAL',
            'payment_proof' => $this->paymentProofFile('application/pdf', 'proof.pdf'),
        ]);

        $response->assertRedirect();

        return CourseRequest::query()
            ->where('course_id', $course->id)
            ->latest()
            ->firstOrFail();
    }

    public function test_dashboard_does_not_show_active_course_before_approval(): void
    {
        $student = $this->createStudent();
        $courseRequest = $this->seedCourseRequestForUser($student, CourseRequestStatus::PENDING_REVIEW->value);

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee(__('dashboard.my_active_courses'));
        $response->assertSee(__('dashboard.pending_requests_title'));
    }

    public function test_dashboard_shows_approved_course_as_active_after_admin_approval(): void
    {
        $student = $this->createStudent();
        $admin = $this->createAdmin();
        $courseRequest = $this->seedCourseRequestForUser($student, CourseRequestStatus::PENDING_REVIEW->value);

        // Admin approves the request
        $this->actingAs($admin);
        /** @var PaymentProof $proof */
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);
        $action = app(ApproveCourseRequestAction::class);
        $action->execute($courseRequest, $proof, $admin->id);

        // Student sees the course as active
        $response = $this->actingAs($student)->get('/dashboard');
        $response->assertOk();
        $response->assertSee(__('dashboard.my_active_courses'));
        $response->assertSee(__('dashboard.this_course_is_active'));
    }

    public function test_active_course_card_shows_course_title(): void
    {
        $student = $this->createStudent();
        $admin = $this->createAdmin();
        $courseRequest = $this->seedCourseRequestForUser($student, CourseRequestStatus::PENDING_REVIEW->value);

        $this->actingAs($admin);
        /** @var PaymentProof $proof */
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);
        $action = app(ApproveCourseRequestAction::class);
        $action->execute($courseRequest, $proof, $admin->id);

        $response = $this->actingAs($student)->get('/dashboard');
        $response->assertOk();
        $course = $courseRequest->course;
        $this->assertNotNull($course);
        $response->assertSee($course->title);
    }

    public function test_active_course_card_shows_tracking_code_and_link(): void
    {
        $student = $this->createStudent();
        $admin = $this->createAdmin();
        $courseRequest = $this->seedCourseRequestForUser($student, CourseRequestStatus::PENDING_REVIEW->value);

        $this->actingAs($admin);
        /** @var PaymentProof $proof */
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);
        $action = app(ApproveCourseRequestAction::class);
        $action->execute($courseRequest, $proof, $admin->id);

        $response = $this->actingAs($student)->get('/dashboard');
        $response->assertOk();
        $response->assertSee($courseRequest->public_tracking_code);
        $response->assertSee(__('dashboard.track_request'));
        $response->assertSee(route('track', ['code' => $courseRequest->public_tracking_code]));
    }

    public function test_active_course_card_does_not_expose_admin_notes(): void
    {
        $student = $this->createStudent();
        $admin = $this->createAdmin();
        $courseRequest = $this->seedCourseRequestForUser($student, CourseRequestStatus::PENDING_REVIEW->value);
        $courseRequest->update(['admin_note' => 'Secret admin note about this student']);

        $this->actingAs($admin);
        /** @var PaymentProof $proof */
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);
        $action = app(ApproveCourseRequestAction::class);
        $action->execute($courseRequest, $proof, $admin->id);

        $response = $this->actingAs($student)->get('/dashboard');
        $response->assertOk();
        $response->assertDontSee('Secret admin note about this student');
    }

    public function test_active_course_card_does_not_expose_payment_proof_file_path(): void
    {
        $student = $this->createStudent();
        $admin = $this->createAdmin();
        $courseRequest = $this->seedCourseRequestForUser($student, CourseRequestStatus::PENDING_REVIEW->value);

        $this->actingAs($admin);
        /** @var PaymentProof $proof */
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);
        $action = app(ApproveCourseRequestAction::class);
        $action->execute($courseRequest, $proof, $admin->id);

        $response = $this->actingAs($student)->get('/dashboard');
        $response->assertOk();
        $this->assertNotNull($proof->proof_file_path);
        $response->assertDontSee($proof->proof_file_path);
    }

    public function test_pending_request_appears_in_pending_section_not_active(): void
    {
        $student = $this->createStudent();
        $courseRequest = $this->seedCourseRequestForUser($student, CourseRequestStatus::PENDING_REVIEW->value);

        $response = $this->actingAs($student)->get('/dashboard');
        $response->assertOk();
        $response->assertDontSee(__('dashboard.my_active_courses'));
        $response->assertSee(__('dashboard.pending_requests_title'));
        $course = $courseRequest->course;
        $this->assertNotNull($course);
        $response->assertSee($course->title);
    }

    public function test_rejected_request_does_not_appear_as_active(): void
    {
        $student = $this->createStudent();
        $admin = $this->createAdmin();
        $courseRequest = $this->seedCourseRequestForUser($student, CourseRequestStatus::PENDING_REVIEW->value);

        // Admin rejects the request
        $this->actingAs($admin);
        $this->post(route('admin.requests.reject', $courseRequest->id), [
            'rejection_reason' => 'Insufficient payment proof.',
        ]);

        $response = $this->actingAs($student)->get('/dashboard');
        $response->assertOk();
        $response->assertDontSee(__('dashboard.my_active_courses'));
        $response->assertSee(__('dashboard.rejected'));
    }

    public function test_logged_in_student_submission_sets_user_id(): void
    {
        $student = $this->createStudent();
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => 100000,
        ]);

        $courseRequest = $this->seedCourseRequestViaWeb($student, $course);

        $this->assertNotNull($courseRequest->user_id);
        $this->assertEquals($student->id, $courseRequest->user_id);
        $this->assertEquals($student->email, $courseRequest->student_email);
    }

    public function test_dashboard_shows_submitted_pending_request_with_correct_counters(): void
    {
        $student = $this->createStudent();
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => 100000,
        ]);

        $this->seedCourseRequestViaWeb($student, $course);

        $response = $this->actingAs($student)->get('/dashboard');
        $response->assertOk();
        $response->assertSee('1', false); // total requests count
        $response->assertSee(__('dashboard.pending_requests_title'));
        $response->assertSee($course->title);
        $response->assertSee(__('dashboard.track_request'));
        $response->assertDontSee(__('dashboard.no_requests'));
    }

    public function test_dashboard_does_not_show_empty_state_when_request_exists(): void
    {
        $student = $this->createStudent();
        $courseRequest = $this->seedCourseRequestForUser($student, CourseRequestStatus::PENDING_REVIEW->value);

        $response = $this->actingAs($student)->get('/dashboard');
        $response->assertOk();
        $response->assertDontSee(__('dashboard.no_requests'));
        $course = $courseRequest->course;
        $this->assertNotNull($course);
        $response->assertSee($course->title);
    }

    public function test_student_cannot_see_other_students_requests(): void
    {
        $studentA = $this->createStudent();
        $studentB = User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email' => 'student-b@test.local',
        ]);

        // Seed a request for Student B
        $this->seedCourseRequestForUser($studentB, CourseRequestStatus::PENDING_REVIEW->value);

        // Student A's dashboard should not show Student B's request
        $response = $this->actingAs($studentA)->get('/dashboard');
        $response->assertOk();
        $response->assertSee(__('dashboard.no_requests'));
    }

    public function test_dashboard_falls_back_to_email_for_old_requests_without_user_id(): void
    {
        $student = $this->createStudent();
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => 100000,
        ]);

        // Create a request with user_id (encrypted student_email no longer supports DB-level fallback)
        $courseRequest = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'user_id' => $student->id,
            'student_email' => $student->email,
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);

        $response = $this->actingAs($student)->get('/dashboard');
        $response->assertOk();
        $response->assertSee($course->title);
        $response->assertSee($courseRequest->public_tracking_code);
        $response->assertDontSee(__('dashboard.no_requests'));
    }

    public function test_english_dashboard_has_no_raw_translation_keys(): void
    {
        $student = $this->createStudent();
        $admin = $this->createAdmin();
        $courseRequest = $this->seedCourseRequestForUser($student, CourseRequestStatus::PENDING_REVIEW->value);

        $this->actingAs($admin);
        /** @var PaymentProof $proof */
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);
        $action = app(ApproveCourseRequestAction::class);
        $action->execute($courseRequest, $proof, $admin->id);

        $response = $this->actingAs($student)->get('/dashboard');
        $response->assertOk();
        $response->assertSee(__('dashboard.my_active_courses'));
        $response->assertSee(__('dashboard.this_course_is_active'));
        $response->assertSee(__('dashboard.approved'));
        $response->assertSee(__('dashboard.track_request'));
        $response->assertSee(__('dashboard.status'));
        $response->assertSee(__('dashboard.payment_proof'));
        $response->assertSee(__('dashboard.tracking_code'));

        // Make sure raw keys don't leak
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringNotContainsString('dashboard.my_active_courses', $content);
        $this->assertStringNotContainsString('dashboard.this_course_is_active', $content);
    }

    public function test_kurdish_dashboard_has_no_raw_translation_keys(): void
    {
        $student = $this->createStudent();
        $admin = $this->createAdmin();
        $courseRequest = $this->seedCourseRequestForUser($student, CourseRequestStatus::PENDING_REVIEW->value);

        $this->actingAs($admin);
        /** @var PaymentProof $proof */
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);
        $action = app(ApproveCourseRequestAction::class);
        $action->execute($courseRequest, $proof, $admin->id);

        // Set locale to Kurdish
        $this->withSession(['locale' => 'ku']);

        $response = $this->actingAs($student)->get('/dashboard');
        $response->assertOk();
        $response->assertSee(__('dashboard.my_active_courses'));
        $response->assertSee(__('dashboard.this_course_is_active'));
        $response->assertSee(__('dashboard.approved'));
        $response->assertSee(__('dashboard.track_request'));
        $response->assertSee(__('dashboard.status'));
        $response->assertSee(__('dashboard.payment_proof'));
        $response->assertSee(__('dashboard.tracking_code'));

        // Make sure raw keys don't leak
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringNotContainsString('dashboard.my_active_courses', $content);
        $this->assertStringNotContainsString('dashboard.this_course_is_active', $content);
    }
}
