<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Base Data Transfer Object with array hydration support.
 */
abstract class BaseDTO
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        /** @phpstan-ignore new.static */
        return new static(...$data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
