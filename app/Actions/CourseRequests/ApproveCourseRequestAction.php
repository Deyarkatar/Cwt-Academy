<?php

namespace App\Actions\CourseRequests;

use App\Enums\AuditAction;
use App\Enums\CourseRequestStatus;
use App\Enums\PaymentProofStatus;
use App\Enums\TelegramAccessGrantStatus;
use App\Models\CourseRequest;
use App\Models\PaymentProof;
use App\Models\TelegramAccessGrant;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveCourseRequestAction
{
    public function execute(CourseRequest $courseRequest, PaymentProof $paymentProof, ?int $approvedBy): CourseRequest
    {
        return DB::transaction(function () use ($courseRequest, $paymentProof, $approvedBy) {
            $lockedRequest = CourseRequest::query()
                ->whereKey($courseRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedProof = PaymentProof::query()
                ->whereKey($paymentProof->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedProof->course_request_id !== $lockedRequest->id) {
                throw ValidationException::withMessages([
                    'payment_proof' => __('errors.proof_not_belong_to_request'),
                ]);
            }

            // State-machine guard: fail closed. Only PENDING_REVIEW or
            // PENDING_PAYMENT may transition to APPROVED.
            if (! in_array($lockedRequest->status, [
                CourseRequestStatus::PENDING_REVIEW,
                CourseRequestStatus::PENDING_PAYMENT,
            ], true)) {
                throw ValidationException::withMessages([
                    'course_request' => __('errors.invalid_status_transition'),
                ]);
            }

            if ($lockedProof->status !== PaymentProofStatus::PENDING) {
                throw ValidationException::withMessages([
                    'payment_proof' => __('errors.invalid_status'),
                ]);
            }

            if ($lockedProof->amount_iqd !== ($lockedRequest->course->price_iqd ?? 0)) {
                throw ValidationException::withMessages([
                    'amount' => __('errors.amount_mismatch'),
                ]);
            }

            $lockedProof->status = PaymentProofStatus::APPROVED->value;
            $lockedProof->reviewed_by = $approvedBy;
            $lockedProof->reviewed_at = now();
            $lockedProof->save();

            $lockedRequest->status = CourseRequestStatus::APPROVED->value;
            $lockedRequest->approved_by = $approvedBy;
            $lockedRequest->approved_at = now();
            $lockedRequest->save();

            $grant = TelegramAccessGrant::create([
                'course_request_id' => $lockedRequest->id,
                'course_id' => $lockedRequest->course_id,
                'student_name' => $lockedRequest->student_name,
                'student_email' => $lockedRequest->student_email,
                'student_phone' => $lockedRequest->student_phone,
                'status' => TelegramAccessGrantStatus::PENDING_MANUAL_ADD,
            ]);

            AuditLogger::log(
                AuditAction::COURSE_REQUEST_APPROVED,
                'CourseRequest',
                $lockedRequest->id,
                ['status' => CourseRequestStatus::PENDING_REVIEW->value],
                ['status' => CourseRequestStatus::APPROVED->value, 'grant_id' => $grant->id],
                $approvedBy,
            );

            AuditLogger::log(
                AuditAction::PAYMENT_PROOF_APPROVED,
                'PaymentProof',
                $lockedProof->id,
                ['status' => PaymentProofStatus::PENDING->value],
                ['status' => PaymentProofStatus::APPROVED->value],
                $approvedBy,
            );

            AuditLogger::log(
                AuditAction::MANUAL_TELEGRAM_ACCESS_PENDING,
                'TelegramAccessGrant',
                $grant->id,
                null,
                ['status' => TelegramAccessGrantStatus::PENDING_MANUAL_ADD->value],
                $approvedBy,
            );

            return $lockedRequest;
        });
    }
}
