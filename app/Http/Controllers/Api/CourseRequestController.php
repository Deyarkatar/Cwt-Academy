<?php

namespace App\Http\Controllers\Api;

use App\Actions\CourseRequests\CreateCourseRequestAction;
use App\Enums\CourseRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreCourseRequestRequest;
use App\Models\Course;
use Illuminate\Http\JsonResponse;

class CourseRequestController extends Controller
{
    public function store(StoreCourseRequestRequest $request, CreateCourseRequestAction $action): JsonResponse
    {
        /** @var Course $course */
        $course = Course::query()->active()->findOrFail($request->validated('course_id'));

        $courseRequest = $action->execute(
            course: $course,
            studentName: $request->string('student_name')->toString(),
            studentEmail: $request->string('student_email')->toString(),
            studentPhone: $request->string('student_phone')->toString(),
            studentCity: $request->string('student_city')->toString(),
            studentNote: $request->string('student_note')->toString(),
        );

        $amountIqd = is_int($course->price_iqd) ? $course->price_iqd : (int) $course->price_iqd;

        if ($amountIqd === 0 && $courseRequest->status !== CourseRequestStatus::PENDING_REVIEW) {
            $courseRequest->status = CourseRequestStatus::PENDING_REVIEW->value;
            $courseRequest->save();
        }

        $responseData = [
            'tracking_code' => $courseRequest->public_tracking_code,
            'status' => $courseRequest->status->value,
        ];

        if ($amountIqd > 0) {
            $responseData['payment_instructions'] = [
                'amount_iqd' => $course->price_iqd,
                'method' => 'MANUAL',
                'note' => 'Please pay the amount and submit proof using your tracking code.',
            ];
        }

        return response()->json([
            'ok' => true,
            'message' => 'COURSE_REQUEST_CREATED',
            'data' => $responseData,
        ], 201);
    }
}
