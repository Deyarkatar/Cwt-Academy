<?php

namespace Database\Factories;

use App\Enums\InstructorStatus;
use App\Models\Instructor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Instructor> */
class InstructorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'bio' => fake()->paragraph(),
            'avatar' => null,
            'status' => InstructorStatus::APPROVED,
            'admin_notes' => null,
        ];
    }
}
