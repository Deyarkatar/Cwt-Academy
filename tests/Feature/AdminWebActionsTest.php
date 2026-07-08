<?php

namespace Tests\Feature;

use App\Actions\CourseRequests\ApproveCourseRequestAction;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWebActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    protected function createFinanceManager(): User
    {
        return User::factory()->create([
            'role' => UserRole::FINANCE_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    protected function createStudent(): User
    {
        return User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    protected function seedCourseRequest(): CourseRequest
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

        PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);

        return $courseRequest;
    }

    public function test_admin_can_approve_request_via_web(): void
    {
        $admin = $this->createAdmin();
        $courseRequest = $this->seedCourseRequest();
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
        ]);
    }

    public function test_admin_can_reject_request_via_web(): void
    {
        $admin = $this->createAdmin();
        $courseRequest = $this->seedCourseRequest();

        $this->actingAs($admin);

        $response = $this->post(route('admin.requests.reject', $courseRequest->id), [
            'rejection_reason' => 'Insufficient payment proof.',
        ]);

        $response->assertRedirect()
            ->assertSessionHas('success', __('admin.request_rejected'));

        $this->assertDatabaseHas('course_requests', [
            'id' => $courseRequest->id,
            'status' => CourseRequestStatus::REJECTED->value,
        ]);
    }

    public function test_finance_manager_can_approve_request_via_web(): void
    {
        $manager = $this->createFinanceManager();
        $courseRequest = $this->seedCourseRequest();
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);

        $this->actingAs($manager);

        $response = $this->post(route('admin.requests.approve', $courseRequest->id), [
            'payment_proof_id' => $proof->id,
        ]);

        $response->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_student_cannot_access_admin_actions(): void
    {
        $student = $this->createStudent();
        $courseRequest = $this->seedCourseRequest();
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);

        $this->actingAs($student);

        $response = $this->post(route('admin.requests.approve', $courseRequest->id), [
            'payment_proof_id' => $proof->id,
        ]);

        $response->assertRedirect('/dashboard');
    }

    public function test_guest_cannot_access_admin_actions(): void
    {
        $courseRequest = $this->seedCourseRequest();
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);

        $response = $this->post(route('admin.requests.approve', $courseRequest->id), [
            'payment_proof_id' => $proof->id,
        ]);

        $response->assertRedirect('/login');
    }

    public function test_admin_can_mark_telegram_access_added_via_web(): void
    {
        $admin = $this->createAdmin();
        $courseRequest = $this->seedCourseRequest();
        /** @var PaymentProof $proof */
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);
        $action = app(ApproveCourseRequestAction::class);
        $action->execute($courseRequest, $proof, $admin->id);

        $grant = $courseRequest->fresh()?->telegramAccessGrant;
        $this->assertNotNull($grant);

        $this->actingAs($admin);

        $response = $this->post(route('admin.telegram.mark_added', $grant->id), [
            'manual_access_reference' => 'Added to Channel A',
            'admin_note' => 'Confirmed by admin.',
        ]);

        $response->assertRedirect()
            ->assertSessionHas('success', __('admin.access_marked_added'));

        $this->assertDatabaseHas('telegram_access_grants', [
            'id' => $grant->id,
            'status' => TelegramAccessGrantStatus::MANUALLY_ADDED->value,
        ]);
    }

    public function test_admin_can_revoke_telegram_access_via_web(): void
    {
        $admin = $this->createAdmin();
        $courseRequest = $this->seedCourseRequest();
        /** @var PaymentProof $proof */
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);
        $action = app(ApproveCourseRequestAction::class);
        $action->execute($courseRequest, $proof, $admin->id);

        $grant = $courseRequest->fresh()?->telegramAccessGrant;
        $this->assertNotNull($grant);

        $this->actingAs($admin);

        $response = $this->post(route('admin.telegram.revoke', $grant->id), [
            'revoked_reason' => 'Refund requested.',
        ]);

        $response->assertRedirect()
            ->assertSessionHas('success', __('admin.access_revoked'));

        $this->assertDatabaseHas('telegram_access_grants', [
            'id' => $grant->id,
            'status' => TelegramAccessGrantStatus::REVOKED->value,
        ]);
    }

    public function test_approve_writes_audit_log(): void
    {
        $admin = $this->createAdmin();
        $courseRequest = $this->seedCourseRequest();
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);

        $this->actingAs($admin);

        $this->post(route('admin.requests.approve', $courseRequest->id), [
            'payment_proof_id' => $proof->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'CourseRequest',
            'entity_id' => $courseRequest->id,
            'action' => 'COURSE_REQUEST_APPROVED',
        ]);
    }

    public function test_reject_writes_audit_log(): void
    {
        $admin = $this->createAdmin();
        $courseRequest = $this->seedCourseRequest();

        $this->actingAs($admin);

        $this->post(route('admin.requests.reject', $courseRequest->id), [
            'rejection_reason' => 'Test reason.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'CourseRequest',
            'entity_id' => $courseRequest->id,
            'action' => 'COURSE_REQUEST_REJECTED',
        ]);
    }

    public function test_web_actions_require_post_method(): void
    {
        $admin = $this->createAdmin();
        $courseRequest = $this->seedCourseRequest();

        $this->actingAs($admin);

        $this->get(route('admin.requests.approve', $courseRequest->id))->assertStatus(405);
        $this->get(route('admin.requests.reject', $courseRequest->id))->assertStatus(405);
    }

    public function test_reject_requires_reason(): void
    {
        $admin = $this->createAdmin();
        $courseRequest = $this->seedCourseRequest();

        $this->actingAs($admin);

        $response = $this->post(route('admin.requests.reject', $courseRequest->id), []);

        $response->assertSessionHasErrors(['rejection_reason']);
    }

    public function test_revoke_requires_reason(): void
    {
        $admin = $this->createAdmin();
        $courseRequest = $this->seedCourseRequest();
        /** @var PaymentProof $proof */
        $proof = $courseRequest->latestPaymentProof;
        $this->assertNotNull($proof);
        $action = app(ApproveCourseRequestAction::class);
        $action->execute($courseRequest, $proof, $admin->id);

        $grant = $courseRequest->fresh()?->telegramAccessGrant;
        $this->assertNotNull($grant);

        $this->actingAs($admin);

        $response = $this->post(route('admin.telegram.revoke', $grant->id), []);

        $response->assertSessionHasErrors(['revoked_reason']);
    }
}
