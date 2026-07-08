<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\CourseDTO;
use App\Models\Course;
use App\Repositories\Contracts\CourseRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CourseRepository implements CourseRepositoryInterface
{
    public function findBySlug(string $slug): ?Course
    {
        return Course::with(['category', 'instructor', 'telegramChannel'])
            ->where('slug', $slug)
            ->first();
    }

    /**
     * @return LengthAwarePaginator<int, Course>
     */
    public function getActivePaginated(int $perPage = 12): LengthAwarePaginator
    {
        return Course::active()
            ->with(['category', 'instructor'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getFeatured(int $limit = 3): array
    {
        return Course::active()
            ->with(['category', 'instructor'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function create(CourseDTO $dto): Course
    {
        return Course::create($dto->toArray());
    }

    public function update(int $id, CourseDTO $dto): Course
    {
        $course = Course::findOrFail($id);
        $course->update($dto->toArray());

        return $course;
    }
}
