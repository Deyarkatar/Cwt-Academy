<?php

namespace App\Actions\Courses;

use App\Enums\AuditAction;
use App\Enums\CourseStatus;
use App\Models\Course;
use App\Services\Audit\AuditLogger;
use App\Services\Courses\CourseService;

class ArchiveCourseAction
{
    public function __construct(
        private readonly CourseService $courseService,
    ) {}

    public function execute(Course $course, ?int $archivedBy): Course
    {
        $oldStatus = $course->status;

        $course->status = CourseStatus::ARCHIVED->value;
        $course->save();

        $this->courseService->forgetSlugCache($course->slug);
        $this->courseService->flushListCache();

        AuditLogger::log(
            AuditAction::COURSE_ARCHIVED,
            'Course',
            $course->id,
            ['status' => $oldStatus->value],
            ['status' => CourseStatus::ARCHIVED->value],
            $archivedBy,
        );

        return $course;
    }
}
