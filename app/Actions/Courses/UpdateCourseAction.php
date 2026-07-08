<?php

namespace App\Actions\Courses;

use App\Enums\AuditAction;
use App\Models\Course;
use App\Services\Audit\AuditLogger;
use App\Services\Courses\CourseService;
use Illuminate\Support\Facades\DB;

class UpdateCourseAction
{
    public function __construct(
        private readonly CourseService $courseService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Course $course, array $data, ?int $updatedBy): Course
    {
        return DB::transaction(function () use ($course, $data, $updatedBy) {
            // Re-fetch with lock to prevent concurrent overwrite
            $lockedCourse = Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();

            $old = $lockedCourse->toArray();

            // Update fillable fields via validated data
            $lockedCourse->fill($data);

            // Never allow status, is_featured, or published_at via mass assignment.
            // These must be managed through dedicated actions or explicit methods.
            $lockedCourse->save();

            // Bust course caches so users see updated data immediately.
            $oldSlug = is_string($old['slug'] ?? null) ? $old['slug'] : '';
            $this->courseService->forgetSlugCache($oldSlug);
            if ($oldSlug !== $lockedCourse->slug) {
                $this->courseService->forgetSlugCache($lockedCourse->slug);
            }
            $this->courseService->flushListCache();

            AuditLogger::log(
                AuditAction::COURSE_UPDATED,
                'Course',
                $lockedCourse->id,
                $old,
                $lockedCourse->toArray(),
                $updatedBy,
            );

            return $lockedCourse;
        });
    }
}
