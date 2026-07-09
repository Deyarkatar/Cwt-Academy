<?php

namespace App\Http\Controllers\Api;

use App\Enums\CourseRequestStatus;
use App\Enums\TelegramAccessGrantStatus;
use App\Http\Controllers\Controller;
use App\Models\CourseRequest;
use App\Services\Payments\ManualPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RequestTrackingController extends Controller
{
    public function show(Request $request, string $trackingCode): JsonResponse
    {
        if (! preg_match('/^[A-Z0-9]{16}$/', $trackingCode)) {
            return response()->json([
                'ok' => false,
                'message' => __('errors.request_not_found'),
            ], 404);
        }

        $emailHash = $request->input('email_hash');
        $courseRequest = CourseRequest::query()
            ->with(['course', 'latestPaymentProof', 'telegramAccessGrant'])
            ->where('public_tracking_code', $trackingCode)
            ->first();

        if (! $courseRequest) {
            return response()->json([
                'ok' => false,
                'message' => __('errors.request_not_found'),
            ], 404);
        }

        if ($emailHash !== null && $emailHash !== hash('sha256', $courseRequest->student_email)) {
            return response()->json([
                'ok' => false,
                'message' => __('errors.request_not_found'),
            ], 404);
        }

        $response = [
            'ok' => true,
            'data' => [
                'tracking_code' => $courseRequest->public_tracking_code,
                'status' => $courseRequest->status->value,
                'course_title' => $courseRequest->course->title ?? '',
            ],
        ];

        if ($emailHash !== null) {
            $response['data']['payment_proof_status'] = $courseRequest->latestPaymentProof?->status->value;

            if ($courseRequest->status === CourseRequestStatus::APPROVED) {
                $grant = $courseRequest->telegramAccessGrant;

                if ($grant) {
                    $status = $grant->status;
                    if ($status === TelegramAccessGrantStatus::PENDING_MANUAL_ADD) {
                        $response['data']['telegram_access'] = [
                            'status' => $status->value,
                            'message' => 'Your payment was approved. Our team will add you to the private Telegram course channel.',
                        ];
                    } elseif (in_array($status, [TelegramAccessGrantStatus::MANUALLY_ADDED, TelegramAccessGrantStatus::ACCESS_SENT], true)) {
                        $response['data']['telegram_access'] = [
                            'status' => $status->value,
                            'message' => 'Your Telegram access has been granted. Please check Telegram or contact support if you need help.',
                        ];
                    } elseif ($status === TelegramAccessGrantStatus::REVOKED) {
                        $response['data']['telegram_access'] = [
                            'status' => $status->value,
                            'message' => 'Your Telegram access has been revoked. Contact support for more information.',
                        ];
                    }
                }
            } elseif ($courseRequest->status === CourseRequestStatus::REJECTED) {
                $response['data']['public_rejection_note'] = $courseRequest->public_rejection_note;
            }
        }

        return response()->json($response);
    }

    public function storePaymentProof(
        Request $request,
        string $trackingCode,
        ManualPaymentService $paymentService,
    ): JsonResponse {
        $ip = $request->ip() ?? 'unknown';
        $ipKey = 'upload:ip:'.$ip;
        $codeKey = 'upload:code:'.$trackingCode;

        if (RateLimiter::tooManyAttempts($ipKey, 3)) {
            return response()->json([
                'ok' => false,
                'message' => 'Too many upload attempts. Please try again later.',
            ], 429);
        }

        if (RateLimiter::tooManyAttempts($codeKey, 5)) {
            return response()->json([
                'ok' => false,
                'message' => 'Too many upload attempts for this request.',
            ], 429);
        }

        RateLimiter::hit($ipKey, decaySeconds: 600);
        RateLimiter::hit($codeKey, decaySeconds: 600);

        $emailHash = $request->input('email_hash');
        $courseRequest = CourseRequest::query()
            ->where('public_tracking_code', $trackingCode)
            ->first();

        if (! $courseRequest) {
            return response()->json([
                'ok' => false,
                'message' => __('errors.request_not_found'),
            ], 404);
        }

        if ($emailHash !== hash('sha256', strtolower(trim($courseRequest->student_email)))) {
            return response()->json([
                'ok' => false,
                'message' => __('errors.request_not_found'),
            ], 404);
        }

        if (! $courseRequest->canSubmitPaymentProof()) {
            return response()->json([
                'ok' => false,
                'message' => __('errors.cannot_submit_proof'),
            ], 422);
        }

        $validated = $request->validate([
            'amount_iqd' => ['required', 'integer', 'min:1', 'max:10000000'],
            'sender_name' => ['nullable', 'string', 'max:255'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'proof_file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
                'max:'.($this->maxProofSizeKb()),
            ],
        ]);

        $proof = $paymentService->storeProof(
            courseRequest: $courseRequest,
            amountIqd: $validated['amount_iqd'],
            senderName: $validated['sender_name'] ?? null,
            transactionReference: $validated['transaction_reference'] ?? null,
            file: $request->file('proof_file'),
        );

        return response()->json([
            'ok' => true,
            'message' => 'PAYMENT_PROOF_PENDING_REVIEW',
            'data' => [
                'proof_id' => $proof->id,
                'status' => $proof->status->value,
            ],
        ], 201);
    }

    private function maxProofSizeKb(): int
    {
        $value = config('services.payment_proof_max_mb', 5);

        return (is_numeric($value) ? (int) $value : 5) * 1024;
    }
}
