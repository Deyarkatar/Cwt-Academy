<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CourseRequests\ApproveCourseRequestAction;
use App\Actions\CourseRequests\RejectCourseRequestAction;
use App\Enums\CourseRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveCourseRequestRequest;
use App\Http\Requests\Admin\RejectCourseRequestRequest;
use App\Models\CourseRequest;
use App\Models\PaymentProof;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CourseRequest::class);

        $query = CourseRequest::query()
            ->with(['course', 'latestPaymentProof']);

        if ($request->status) {
            $allowedStatuses = array_map(fn (CourseRequestStatus $s) => $s->value, CourseRequestStatus::cases());
            if (! in_array($request->status, $allowedStatuses, true)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invalid status filter.',
                ], 422);
            }
            $query->where('status', $request->status);
        }

        $requests = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'ok' => true,
            'data' => $requests,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $courseRequest = CourseRequest::query()
            ->with(['course', 'paymentProofs', 'telegramAccessGrant.course.telegramChannel', 'approver', 'rejecter'])
            ->findOrFail($id);

        $this->authorize('view', $courseRequest);

        return response()->json([
            'ok' => true,
            'data' => $courseRequest,
        ]);
    }

    public function approve(
        ApproveCourseRequestRequest $request,
        int $id,
        ApproveCourseRequestAction $action,
    ): JsonResponse {
        $courseRequest = CourseRequest::query()->findOrFail($id);
        $paymentProofId = $request->validated('payment_proof_id');
        if (! is_int($paymentProofId)) {
            abort(422, 'Invalid payment proof ID.');
        }
        $paymentProof = PaymentProof::query()->findOrFail($paymentProofId);

        $this->authorize('approve', $courseRequest);

        $courseRequest = $action->execute($courseRequest, $paymentProof, auth()->user()?->id);

        return response()->json([
            'ok' => true,
            'message' => 'COURSE_REQUEST_APPROVED',
            'data' => $courseRequest->fresh(['telegramAccessGrant']),
        ]);
    }

    public function reject(
        RejectCourseRequestRequest $request,
        int $id,
        RejectCourseRequestAction $action,
    ): JsonResponse {
        $courseRequest = CourseRequest::query()->findOrFail($id);

        $this->authorize('reject', $courseRequest);

        $rejectionReason = $request->validated('rejection_reason');
        if (! is_string($rejectionReason)) {
            abort(422, 'Invalid rejection reason.');
        }

        $courseRequest = $action->execute(
            $courseRequest,
            auth()->user()?->id,
            $rejectionReason,
        );

        return response()->json([
            'ok' => true,
            'message' => 'COURSE_REQUEST_REJECTED',
            'data' => $courseRequest,
        ]);
    }
}
