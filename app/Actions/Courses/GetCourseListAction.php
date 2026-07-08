<?php

declare(strict_types=1);

namespace App\Actions\Courses;

use App\DTOs\CourseDTO;
use App\Models\Course;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Encapsulated action for retrieving paginated course lists.
 * Returns DTOs to decouple internal model from API response.
 */
class GetCourseListAction
{
    /**
     * @return LengthAwarePaginator<int, CourseDTO>
     */
    public function execute(int $perPage = 12, bool $activeOnly = true): LengthAwarePaginator
    {
        $query = Course::with(['category', 'instructor'])
            ->orderByDesc('created_at');

        if ($activeOnly) {
            $query->where('status', 'ACTIVE');
        }

        return $query->paginate($perPage)
            ->through(fn (Course $course): CourseDTO => new CourseDTO(
                id: $course->id,
                title: $course->title,
                slug: $course->slug,
                description: $course->description,
                image: $course->thumbnail,
                status: $course->status->value ?? $course->status,
                price_iqd: $course->price_iqd,
                category_name: $course->category?->name,
                instructor_name: $course->instructor?->name,
                created_at: $course->created_at?->toIso8601String(),
            ));
    }
}
