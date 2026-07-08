<?php

namespace App\Actions\CourseRequests;

use App\Enums\AuditAction;
use App\Enums\CourseRequestStatus;
use App\Enums\PaymentProofStatus;
use App\Models\CourseRequest;
use App\Models\PaymentProof;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectCourseRequestAction
{
    public function execute(CourseRequest $courseRequest, ?int $rejectedBy, string $reason): CourseRequest
    {
        return DB::transaction(function () use ($courseRequest, $rejectedBy, $reason) {
            $lockedRequest = CourseRequest::query()
                ->whereKey($courseRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $originalStatus = $lockedRequest->status;

            // State-machine guard: only reviewable statuses may be rejected.
            if (! in_array($lockedRequest->status, [
                CourseRequestStatus::PENDING_REVIEW,
                CourseRequestStatus::PENDING_PAYMENT,
            ], true)) {
                throw ValidationException::withMessages([
                    'course_request' => __('errors.invalid_status_transition'),
                ]);
            }

            $lockedRequest->status = CourseRequestStatus::REJECTED->value;
            $lockedRequest->rejected_by = $rejectedBy;
            $lockedRequest->rejected_at = now();
            $lockedRequest->rejection_reason = $reason;
            $lockedRequest->public_rejection_note = $this->sanitizePublicNote($reason);
            $lockedRequest->save();

            $paymentProof = PaymentProof::query()
                ->where('course_request_id', $lockedRequest->id)
                ->where('status', PaymentProofStatus::PENDING)
                ->lockForUpdate()
                ->first();

            if ($paymentProof) {
                $paymentProof->status = PaymentProofStatus::REJECTED->value;
                $paymentProof->reviewed_by = $rejectedBy;
                $paymentProof->reviewed_at = now();
                $paymentProof->rejection_reason = $reason;
                $paymentProof->save();

                AuditLogger::log(
                    AuditAction::PAYMENT_PROOF_REJECTED,
                    'PaymentProof',
                    $paymentProof->id,
                    ['status' => PaymentProofStatus::PENDING->value],
                    ['status' => PaymentProofStatus::REJECTED->value],
                    $rejectedBy,
                );
            }

            AuditLogger::log(
                AuditAction::COURSE_REQUEST_REJECTED,
                'CourseRequest',
                $lockedRequest->id,
                ['status' => $originalStatus->value],
                ['status' => CourseRequestStatus::REJECTED->value, 'reason' => $reason],
                $rejectedBy,
            );

            return $lockedRequest;
        });
    }

    private function sanitizePublicNote(string $reason): string
    {
        // Strip any internal/admin-only details. Truncate and sanitize
        // so the public note is safe for end-user display.
        $note = strip_tags($reason);

        return htmlspecialchars(substr($note, 0, 500), ENT_QUOTES, 'UTF-8');
    }
}
