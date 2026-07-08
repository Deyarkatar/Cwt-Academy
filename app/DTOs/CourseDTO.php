<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Immutable Course Data Transfer Object for API responses and actions.
 */
class CourseDTO extends BaseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly ?string $image,
        public readonly string $status,
        public readonly int $price_iqd,
        public readonly ?string $category_name,
        public readonly ?string $instructor_name,
        public readonly ?string $created_at,
    ) {}
}
