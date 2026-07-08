<?php

namespace App\Actions\CourseRequests;

use App\Enums\AuditAction;
use App\Enums\CourseRequestStatus;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Services\Audit\AuditLogger;

class CreateCourseRequestAction
{
    public function execute(
        Course $course,
        string $studentName,
        string $studentEmail,
        ?string $studentPhone = null,
        ?string $studentCity = null,
        ?string $studentNote = null,
        ?string $paymentMethod = null,
        ?int $userId = null,
    ): CourseRequest {
        $request = new CourseRequest([
            'course_id' => $course->id,
            'student_name' => $studentName,
            'student_email' => $studentEmail,
            'student_phone' => $studentPhone,
            'student_city' => $studentCity,
            'payment_method' => $paymentMethod ?: 'MANUAL',
            'student_note' => $studentNote,
        ]);
        $request->user_id = $userId;
        $request->status = CourseRequestStatus::PENDING_PAYMENT->value;
        $request->save();

        AuditLogger::log(
            AuditAction::COURSE_REQUEST_CREATED,
            'CourseRequest',
            $request->id,
            null,
            $request->toArray(),
        );

        return $request;
    }
}
