<?php

namespace Database\Factories;

use App\Enums\CourseLanguage;
use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\Instructor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Course> */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'instructor_id' => Instructor::factory(),
            'title' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(),
            'short_description' => fake()->sentence(10),
            'description' => fake()->paragraphs(3, true),
            'price_iqd' => fake()->numberBetween(50000, 500000),
            'thumbnail' => null,
            'level' => fake()->randomElement(CourseLevel::cases()),
            'language' => fake()->randomElement(CourseLanguage::cases()),
            'status' => CourseStatus::ACTIVE,
            'is_featured' => false,
            'published_at' => now(),
        ];
    }
}
