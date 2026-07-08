<?php

namespace Database\Factories;

use App\Enums\CourseRequestStatus;
use App\Models\Course;
use App\Models\CourseRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CourseRequest> */
class CourseRequestFactory extends Factory
{
    protected $model = CourseRequest::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'user_id' => null,
            'student_name' => fake()->name(),
            'student_email' => fake()->safeEmail(),
            'student_phone' => fake()->phoneNumber(),
            'student_city' => fake()->randomElement(['Erbil', 'Sulaymaniyah', 'Duhok', 'Halabja', 'Kirkuk']),
            'status' => CourseRequestStatus::PENDING_PAYMENT,
            'payment_method' => 'MANUAL',
            'student_note' => fake()->optional()->sentence(),
            'admin_note' => null,
        ];
    }
}
