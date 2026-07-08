<?php

namespace Database\Seeders\Courses;

use App\Enums\CourseLanguage;
use App\Enums\CourseLevel;

/**
 * Base contract for a single course definition.
 *
 * To add a new course to the library:
 *   1. Create a new file in `database/seeders/courses/`
 *      (e.g. `WebDevelopmentCourse.php`).
 *   2. Extend this class and implement `definition()`.
 *   3. Run `php artisan db:seed --class=CourseLibrarySeeder`
 *      (or just `php artisan db:seed`).
 *
 * The seeder is idempotent: re-running it updates existing rows
 * by `slug`, never creates duplicates.
 *
 * The `definition()` array MUST contain the following keys:
 *
 *   - slug                : unique URL slug          (string)
 *   - title               : course title             (string)
 *   - category_slug       : Category slug            (string, must exist in `categories`)
 *   - short_description   : ~1 sentence summary      (string)
 *   - description         : full description         (string)
 *   - price_iqd           : price in IQD             (int)
 *   - level               : CourseLevel enum         (CourseLevel)
 *   - language            : CourseLanguage enum      (CourseLanguage)
 *   - is_featured         : show on homepage?        (bool)  — optional, default false
 *   - telegram_title      : channel display title    (string)
 *   - telegram_url        : Telegram invite URL      (string, https://t.me/...)
 */
abstract class CourseDefinition
{
    /**
     * @return array{
     *     slug: string,
     *     title: string,
     *     category_slug: string,
     *     short_description: string,
     *     description: string,
     *     price_iqd: int,
     *     level: CourseLevel,
     *     language: CourseLanguage,
     *     is_featured?: bool,
     *     telegram_title: string,
     *     telegram_url: string,
     * }
     */
    abstract public function definition(): array;
}
