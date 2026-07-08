# Course Library

This folder is the **single source of truth** for all courses in Cwt Academy.

Each course is defined by a single PHP class extending `CourseDefinition`.
The seeder at `database/seeders/CourseLibrarySeeder.php` automatically
discovers every file in this folder and syncs it into the database
(idempotently — re-running never creates duplicates).

> **PSR-4 note**: This folder name (`Courses/`, capital `C`) maps to the
> namespace `Database\Seeders\Courses\` via Composer's PSR-4 autoloader.
> Do NOT rename it to lowercase or autoloading will break.

## Adding a New Course

### Step 1 — Make sure the category exists

Open `database/seeders/CategorySeeder.php` and confirm that the
category you want to use is listed. If not, add it with a unique `slug`.

### Step 2 — Create the course file

Create a new file in this folder. The filename **must match** the class
name (PascalCase), and the file **must end with `Course.php`**.

Example: `database/seeders/Courses/WebDevelopmentCourse.php`

```php
<?php

namespace Database\Seeders\Courses;

use App\Enums\CourseLanguage;
use App\Enums\CourseLevel;

final class WebDevelopmentCourse extends CourseDefinition
{
    public function definition(): array
    {
        return [
            'slug' => 'web-development',                // unique URL slug
            'title' => 'Web Development',
            'category_slug' => 'programming',           // must exist in CategorySeeder
            'short_description' => 'Modern full-stack web development.',
            'description' => 'Long form description shown on the course page...',
            'price_iqd' => 175000,
            'level' => CourseLevel::INTERMEDIATE,       // BEGINNER | INTERMEDIATE | ADVANCED | ALL_LEVELS
            'language' => CourseLanguage::KU,           // KU | AR | EN
            'is_featured' => false,                     // optional, default false
            'telegram_title' => 'Web Dev — Private Channel',
            'telegram_url' => 'https://t.me/+YOUR_INVITE_HASH',
        ];
    }
}
```

### Step 3 — Refresh Composer's autoload map

After creating a new file, regenerate the optimized class map so Composer
can find your new class:

```bash
composer dump-autoload -o
```

### Step 4 — Run the seeder

```bash
php artisan db:seed --class=CourseLibrarySeeder
```

Or simply run the full seed:

```bash
php artisan db:seed
```

The seeder will:

- Look up the `category_slug` to attach the right category.
- Create or update the course (matched by `slug`).
- Create or update the linked `TelegramChannel` row.
- Mark the course as `ACTIVE` and set `published_at = now()`.

## Removing / Archiving a Course

The seeder **never deletes** courses. To take a course offline,
either:

1. Mark it as archived via the admin panel, **or**
2. Delete the row in the `courses` table manually, **or**
3. Change `'status' => CourseStatus::ARCHIVED` in the migration logic
   (advanced — not recommended).

Deleting the file in this folder alone will NOT remove the course
from the database.

## Rules / Conventions

- **`slug` is the primary identity** — keep it stable. Changing the
  slug creates a new course instead of updating the old one.
- **Filename must end with `Course.php`** — anything else is ignored
  by the seeder.
- **`CourseDefinition.php` itself is never seeded** — it is the
  abstract base class.
- **Telegram URLs must be valid `https://t.me/...` invite links.**
