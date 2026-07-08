<?php

namespace App\Http\Controllers\Api;

use App\Actions\CourseRequests\CreateCourseRequestAction;
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

        return response()->json([
            'ok' => true,
            'message' => 'COURSE_REQUEST_CREATED',
            'data' => [
                'tracking_code' => $courseRequest->public_tracking_code,
                'status' => $courseRequest->status->value,
                'payment_instructions' => [
                    'amount_iqd' => $course->price_iqd,
                    'method' => 'MANUAL',
                    'note' => 'Please pay the amount and submit proof using your tracking code.',
                ],
            ],
        ], 201);
    }
}
