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

class AdminApprovalTest extends TestCase
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

    public function test_admin_can_approve_valid_payment_proof(): void
    {
        $admin = $this->createAdmin();
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
        $paymentProof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/approve", [
                'payment_proof_id' => $paymentProof->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'COURSE_REQUEST_APPROVED');

        $this->assertDatabaseHas('course_requests', [
            'id' => $courseRequest->id,
            'status' => CourseRequestStatus::APPROVED->value,
        ]);
    }

    public function test_admin_cannot_approve_wrong_amount(): void
    {
        $admin = $this->createAdmin();
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
        $paymentProof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 50000,
            'status' => PaymentProofStatus::PENDING,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/approve", [
                'payment_proof_id' => $paymentProof->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', __('errors.amount_mismatch'));
    }

    public function test_admin_cannot_approve_with_payment_proof_from_another_request(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => 100000,
        ]);

        $courseRequestA = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);
        $courseRequestB = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);

        $proofForA = PaymentProof::factory()->create([
            'course_request_id' => $courseRequestA->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);
        PaymentProof::factory()->create([
            'course_request_id' => $courseRequestB->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/course-requests/{$courseRequestB->id}/approve", [
                'payment_proof_id' => $proofForA->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', __('errors.proof_not_belong_to_request'));

        $this->assertDatabaseHas('course_requests', [
            'id' => $courseRequestB->id,
            'status' => CourseRequestStatus::PENDING_REVIEW->value,
        ]);

        $this->assertDatabaseMissing('telegram_access_grants', [
            'course_request_id' => $courseRequestB->id,
        ]);
    }

    public function test_duplicate_approval_does_not_create_duplicate_telegram_grant(): void
    {
        $admin = $this->createAdmin();
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
        $paymentProof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        // First approval should succeed
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/approve", [
                'payment_proof_id' => $paymentProof->id,
            ])->assertOk();

        $this->assertDatabaseCount('telegram_access_grants', 1);

        // Second approval should fail
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/approve", [
                'payment_proof_id' => $paymentProof->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', __('errors.invalid_status_transition'));

        $this->assertDatabaseCount('telegram_access_grants', 1);
    }

    public function test_approval_creates_manual_telegram_access_pending(): void
    {
        $admin = $this->createAdmin();
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
        $paymentProof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/approve", [
                'payment_proof_id' => $paymentProof->id,
            ]);

        $this->assertDatabaseHas('telegram_access_grants', [
            'course_request_id' => $courseRequest->id,
            'status' => TelegramAccessGrantStatus::PENDING_MANUAL_ADD->value,
        ]);
    }

    public function test_rejected_request_does_not_create_access(): void
    {
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
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/course-requests/{$courseRequest->id}/reject", [
                'rejection_reason' => 'Insufficient payment proof.',
            ]);

        $this->assertDatabaseHas('course_requests', [
            'id' => $courseRequest->id,
            'status' => CourseRequestStatus::REJECTED->value,
        ]);

        $this->assertDatabaseMissing('telegram_access_grants', [
            'course_request_id' => $courseRequest->id,
        ]);
    }

    public function test_admin_can_mark_access_added(): void
    {
        $admin = $this->createAdmin();
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

        $action = app(ApproveCourseRequestAction::class);
        $paymentProof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);
        $action->execute($courseRequest, $paymentProof, $admin->id);

        $grant = $courseRequest->fresh()?->telegramAccessGrant;
        $this->assertNotNull($grant);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/telegram-access-grants/{$grant->id}/mark-added", [
                'manual_access_reference' => 'Added to Channel A by admin',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'MANUAL_ACCESS_MARKED_ADDED');

        $this->assertDatabaseHas('telegram_access_grants', [
            'id' => $grant->id,
            'status' => TelegramAccessGrantStatus::MANUALLY_ADDED->value,
        ]);
    }

    public function test_admin_can_revoke_access(): void
    {
        $admin = $this->createAdmin();
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

        $action = app(ApproveCourseRequestAction::class);
        $paymentProof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);
        $action->execute($courseRequest, $paymentProof, $admin->id);

        $grant = $courseRequest->fresh()?->telegramAccessGrant;
        $this->assertNotNull($grant);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/telegram-access-grants/{$grant->id}/mark-revoked", [
                'revoked_reason' => 'Refund requested by student.',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'ACCESS_REVOKED');

        $this->assertDatabaseHas('telegram_access_grants', [
            'id' => $grant->id,
            'status' => TelegramAccessGrantStatus::REVOKED->value,
        ]);
    }

    public function test_payment_proof_approve_endpoint_approves_parent_request_and_creates_grant(): void
    {
        $admin = $this->createAdmin();
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
        $paymentProof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/payment-proofs/{$paymentProof->id}/approve");

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'PAYMENT_PROOF_APPROVED');

        $this->assertDatabaseHas('payment_proofs', [
            'id' => $paymentProof->id,
            'status' => PaymentProofStatus::APPROVED->value,
        ]);

        $this->assertDatabaseHas('course_requests', [
            'id' => $courseRequest->id,
            'status' => CourseRequestStatus::APPROVED->value,
        ]);

        $this->assertDatabaseHas('telegram_access_grants', [
            'course_request_id' => $courseRequest->id,
            'status' => TelegramAccessGrantStatus::PENDING_MANUAL_ADD->value,
        ]);
    }

    public function test_payment_proof_approve_endpoint_rejects_amount_mismatch(): void
    {
        $admin = $this->createAdmin();
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
        $paymentProof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 50000,
            'status' => PaymentProofStatus::PENDING,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/payment-proofs/{$paymentProof->id}/approve");

        $response->assertUnprocessable()
            ->assertJsonPath('message', __('errors.amount_mismatch'));

        $this->assertDatabaseHas('course_requests', [
            'id' => $courseRequest->id,
            'status' => CourseRequestStatus::PENDING_REVIEW->value,
        ]);

        $this->assertDatabaseMissing('telegram_access_grants', [
            'course_request_id' => $courseRequest->id,
        ]);
    }

    public function test_payment_proof_approve_endpoint_rejects_proof_from_another_request(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();
        $instructor = Instructor::factory()->create();
        $course = Course::factory()->create([
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::ACTIVE,
            'price_iqd' => 100000,
        ]);

        $courseRequestA = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);
        $courseRequestB = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_REVIEW,
        ]);

        $proofForA = PaymentProof::factory()->create([
            'course_request_id' => $courseRequestA->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        // Approve request A with proof A first.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/payment-proofs/{$proofForA->id}/approve")
            ->assertOk();

        // Attempting to approve proof A again (now attached to an APPROVED request)
        // should fail because the parent request is no longer in a reviewable state.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/payment-proofs/{$proofForA->id}/approve");

        $response->assertUnprocessable()
            ->assertJsonPath('message', __('errors.invalid_status_transition'));

        $this->assertDatabaseCount('telegram_access_grants', 1);
    }

    public function test_payment_proof_approve_endpoint_requires_approve_permission(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
        ]);
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
        $paymentProof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);

        $token = $student->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/payment-proofs/{$paymentProof->id}/approve");

        $response->assertForbidden();

        $this->assertDatabaseHas('payment_proofs', [
            'id' => $paymentProof->id,
            'status' => PaymentProofStatus::PENDING->value,
        ]);
    }

    public function test_admin_cannot_approve_payment_proof_for_their_own_request(): void
    {
        $admin = $this->createAdmin();
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
            'student_email' => $admin->email,
        ]);
        $paymentProof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/payment-proofs/{$paymentProof->id}/approve");

        $response->assertForbidden();

        $this->assertDatabaseHas('payment_proofs', [
            'id' => $paymentProof->id,
            'status' => PaymentProofStatus::PENDING->value,
        ]);

        $this->assertDatabaseMissing('telegram_access_grants', [
            'course_request_id' => $courseRequest->id,
        ]);
    }

    public function test_revoked_access_is_not_shown_as_valid(): void
    {
        $admin = $this->createAdmin();
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

        $action = app(ApproveCourseRequestAction::class);
        $paymentProof = PaymentProof::factory()->create([
            'course_request_id' => $courseRequest->id,
            'amount_iqd' => 100000,
            'status' => PaymentProofStatus::PENDING,
        ]);
        $action->execute($courseRequest, $paymentProof, $admin->id);

        $grant = $courseRequest->fresh()?->telegramAccessGrant;
        $this->assertNotNull($grant);

        $token = $admin->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/telegram-access-grants/{$grant->id}/mark-revoked", [
                'revoked_reason' => 'Test revocation.',
            ]);

        $response = $this->getJson("/api/v1/course-requests/{$courseRequest->public_tracking_code}?email_hash=".hash('sha256', $courseRequest->student_email));

        $response->assertOk();
        /** @var array<string, mixed> $data */
        $data = $response->json('data') ?? [];
        $this->assertFalse(isset($data['invite']));
        $telegramAccess = is_array($data['telegram_access'] ?? null) ? $data['telegram_access'] : [];
        $this->assertSame('REVOKED', $telegramAccess['status'] ?? null);
    }
}
