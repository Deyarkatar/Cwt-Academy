<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Programming', 'slug' => 'programming', 'description' => 'Software development and coding courses'],
            ['name' => 'Cyber Security', 'slug' => 'cyber-security', 'description' => 'Cyber security, ethical hacking, and IT security courses'],
            ['name' => 'Design', 'slug' => 'design', 'description' => 'Graphic and UI/UX design courses'],
            ['name' => 'Business', 'slug' => 'business', 'description' => 'Business and entrepreneurship courses'],
            ['name' => 'Language', 'slug' => 'language', 'description' => 'Language learning courses'],
        ];

        foreach ($categories as $index => $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                $category + ['sort_order' => $index + 1, 'is_active' => true],
            );
        }
    }
}
