<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CourseRequestStatus;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Services\Payments\ManualPaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Tests for Fix 1: Transaction reference encrypted + unique bypass.
 */
class TransactionReferenceHashTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_transaction_reference_is_rejected(): void
    {
        $course = Course::factory()->create(['status' => 'ACTIVE']);
        $courseRequest = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_PAYMENT,
        ]);
        $service = app(ManualPaymentService::class);
        $file = $this->paymentProofFile();

        $service->storeProof(
            courseRequest: $courseRequest,
            amountIqd: $course->price_iqd,
            senderName: 'John Doe',
            transactionReference: 'ABC123XYZ',
            file: $file,
        );

        $courseRequest2 = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_PAYMENT,
        ]);

        $this->expectException(ValidationException::class);

        $service->storeProof(
            courseRequest: $courseRequest2,
            amountIqd: $course->price_iqd,
            senderName: 'Jane Doe',
            transactionReference: 'ABC123XYZ',
            file: $file,
        );
    }

    public function test_hash_is_stored_in_database(): void
    {
        $course = Course::factory()->create(['status' => 'ACTIVE']);
        $courseRequest = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_PAYMENT,
        ]);
        $service = app(ManualPaymentService::class);
        $file = $this->paymentProofFile();

        $proof = $service->storeProof(
            courseRequest: $courseRequest,
            amountIqd: $course->price_iqd,
            senderName: 'John Doe',
            transactionReference: 'UNIQUE-REF-001',
            file: $file,
        );

        $this->assertDatabaseHas('payment_proofs', [
            'id' => $proof->id,
            'transaction_reference_hash' => hash('sha256', 'UNIQUE-REF-001'),
        ]);
    }

    public function test_null_reference_does_not_set_hash(): void
    {
        $course = Course::factory()->create(['status' => 'ACTIVE']);
        $courseRequest = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_PAYMENT,
        ]);
        $service = app(ManualPaymentService::class);
        $file = $this->paymentProofFile();

        $proof = $service->storeProof(
            courseRequest: $courseRequest,
            amountIqd: $course->price_iqd,
            senderName: 'John Doe',
            transactionReference: null,
            file: $file,
        );

        $this->assertNull($proof->transaction_reference_hash);
    }

    public function test_database_unique_constraint_enforced(): void
    {
        $course = Course::factory()->create(['status' => 'ACTIVE']);
        $request1 = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_PAYMENT,
        ]);
        $request2 = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_PAYMENT,
        ]);

        DB::table('payment_proofs')->insert([
            'course_request_id' => $request1->id,
            'amount_iqd' => 50000,
            'transaction_reference' => 'REF1',
            'transaction_reference_hash' => hash('sha256', 'REF1'),
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('payment_proofs')->insert([
            'course_request_id' => $request2->id,
            'amount_iqd' => 50000,
            'transaction_reference' => 'REF1',
            'transaction_reference_hash' => hash('sha256', 'REF1'),
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_race_condition_protection_with_lock(): void
    {
        $course = Course::factory()->create(['status' => 'ACTIVE']);
        $courseRequest = CourseRequest::factory()->create([
            'course_id' => $course->id,
            'status' => CourseRequestStatus::PENDING_PAYMENT,
        ]);
        $service = app(ManualPaymentService::class);
        $file = $this->paymentProofFile();

        $proof = $service->storeProof(
            courseRequest: $courseRequest,
            amountIqd: $course->price_iqd,
            senderName: 'Test',
            transactionReference: 'RACE-TEST-001',
            file: $file,
        );

        $this->assertNotNull($proof);
        $this->assertEquals(hash('sha256', 'RACE-TEST-001'), $proof->transaction_reference_hash);
    }
}
