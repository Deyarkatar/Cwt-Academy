<?php

namespace App\Http\Controllers\Web;

use App\Enums\TelegramAccessGrantStatus;
use App\Http\Controllers\Controller;
use App\Models\CourseRequest;
use Illuminate\View\View;

class TrackingController extends Controller
{
    public function show(): View
    {
        $code = request()->string('code')->toString();
        $emailHash = request()->string('email_hash')->toString();
        $requestData = null;

        if ($code !== '') {
            if (! preg_match('/^[A-Z0-9]{16}$/', $code)) {
                return view('public.tracking', ['code' => $code, 'requestData' => null]);
            }

            $courseRequest = CourseRequest::with([
                'course.telegramChannel',
                'latestPaymentProof',
                'telegramAccessGrant',
            ])
                ->where('public_tracking_code', $code)
                ->first();

            if ($courseRequest && $emailHash !== '' && $emailHash !== hash('sha256', $courseRequest->student_email)) {
                $courseRequest = null;
            }

            if ($courseRequest) {
                $channel = $courseRequest->course?->telegramChannel;
                $hasUsableChannel = $channel && $channel->isUsable();

                $requestData = [
                    'course_title' => $courseRequest->course->title ?? '',
                    'course_slug' => $courseRequest->course->slug ?? '',
                    'tracking_code' => $courseRequest->public_tracking_code,
                    'status' => $courseRequest->status->value,
                ];

                if ($emailHash !== '') {
                    $requestData['rejection_reason'] = $courseRequest->public_rejection_note;
                    $requestData['payment_proof_status'] = $courseRequest->latestPaymentProof?->status->value;

                    if ($courseRequest->telegramAccessGrant) {
                        $grantStatus = $courseRequest->telegramAccessGrant->status;
                        $isAccessReady = in_array($grantStatus, [
                            TelegramAccessGrantStatus::MANUALLY_ADDED,
                            TelegramAccessGrantStatus::ACCESS_SENT,
                        ], true);

                        $requestData['telegram_access'] = [
                            'status' => $grantStatus->value,
                            'message' => match ($grantStatus) {
                                TelegramAccessGrantStatus::PENDING_MANUAL_ADD => __('tracking.access_pending_manual'),
                                TelegramAccessGrantStatus::MANUALLY_ADDED,
                                TelegramAccessGrantStatus::ACCESS_SENT => __('tracking.access_granted'),
                                TelegramAccessGrantStatus::REVOKED => __('tracking.access_revoked'),
                                default => '',
                            },
                        ];

                        if ($isAccessReady && $hasUsableChannel) {
                            $requestData['telegram_channel_url'] = $channel->telegram_url;
                        } elseif ($isAccessReady && ! $hasUsableChannel) {
                            $requestData['telegram_channel_fallback'] = true;
                        }
                    }
                }
            }
        }

        return view('public.tracking', ['code' => $code, 'requestData' => $requestData]);
    }
}
