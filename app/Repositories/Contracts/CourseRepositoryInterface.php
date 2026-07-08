<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTOs\CourseDTO;
use App\Models\Course;
use Illuminate\Pagination\LengthAwarePaginator;

interface CourseRepositoryInterface
{
    public function findBySlug(string $slug): ?Course;

    /**
     * @return LengthAwarePaginator<int, Course>
     */
    public function getActivePaginated(int $perPage = 12): LengthAwarePaginator;

    /** @return Course[] */
    public function getFeatured(int $limit = 3): array;

    public function create(CourseDTO $dto): Course;

    public function update(int $id, CourseDTO $dto): Course;
}
