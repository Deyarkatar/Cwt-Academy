<?php

namespace Database\Seeders;

use App\Enums\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\TelegramChannel;
use Database\Seeders\Courses\CourseDefinition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Auto-discovering course library seeder.
 *
 * Walks `database/seeders/courses/` and registers every concrete
 * `CourseDefinition` subclass it finds. Idempotent: re-running this
 * seeder updates existing rows by `slug` rather than creating duplicates.
 *
 * To add a new course: drop a new `*Course.php` file into the
 * `database/seeders/courses/` folder. See its README for the format.
 */
class CourseLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $primaryInstructor = Instructor::query()->orderBy('id')->first();

        foreach ($this->discoverCourseDefinitions() as $definition) {
            $this->seedCourse($definition->definition(), $primaryInstructor);
        }
    }

    /**
     * Discover every CourseDefinition subclass under `database/seeders/Courses/`.
     *
     * @return iterable<CourseDefinition>
     */
    private function discoverCourseDefinitions(): iterable
    {
        $folder = __DIR__.DIRECTORY_SEPARATOR.'Courses';

        if (! is_dir($folder)) {
            return [];
        }

        $files = glob($folder.DIRECTORY_SEPARATOR.'*.php') ?: [];

        sort($files);

        $definitions = [];

        foreach ($files as $file) {
            $basename = basename($file, '.php');

            // Skip the abstract base class itself.
            if ($basename === 'CourseDefinition') {
                continue;
            }

            $class = 'Database\\Seeders\\Courses\\'.$basename;

            if (! class_exists($class)) {
                require_once $file;
            }

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(CourseDefinition::class)) {
                continue;
            }

            /** @var CourseDefinition $instance */
            $instance = $reflection->newInstance();

            $definitions[] = $instance;
        }

        return $definitions;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function seedCourse(array $data, ?Instructor $primaryInstructor): void
    {
        $category = Category::query()->where('slug', $data['category_slug'])->first();

        if (! $category) {
            Log::warning('CourseLibrarySeeder: missing category', [
                'course_slug' => $data['slug'] ?? null,
                'category_slug' => $data['category_slug'] ?? null,
            ]);

            return;
        }

        $course = Course::updateOrCreate(
            ['slug' => $data['slug']],
            [
                'category_id' => $category->id,
                'instructor_id' => $primaryInstructor?->id,
                'title' => $data['title'],
                'short_description' => $data['short_description'],
                'description' => $data['description'],
                'price_iqd' => $data['price_iqd'],
                'level' => $data['level'],
                'language' => $data['language'],
                'status' => CourseStatus::ACTIVE,
                'is_featured' => $data['is_featured'] ?? false,
                'published_at' => now(),
            ],
        );

        if (! empty($data['telegram_url'])) {
            TelegramChannel::updateOrCreate(
                ['course_id' => $course->id],
                [
                    'title' => $data['telegram_title'] ?? $data['title'],
                    'private_channel_name' => $data['title'],
                    'telegram_url' => $data['telegram_url'],
                    'is_active' => true,
                ],
            );
        }
    }
}
