<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\TelegramChannel;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CwtAcademyCourseSeeder;
use Database\Seeders\InstructorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CwtAcademyCourseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function seedCwtCourses(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(InstructorSeeder::class);
        $this->seed(CwtAcademyCourseSeeder::class);
    }

    public function test_seeder_creates_three_active_courses(): void
    {
        $this->seedCwtCourses();

        foreach (['it-security', 'ethical-hacking', 'programming'] as $slug) {
            $this->assertDatabaseHas('courses', [
                'slug' => $slug,
                'status' => CourseStatus::ACTIVE->value,
            ]);
        }
    }

    public function test_each_course_has_its_own_active_telegram_channel(): void
    {
        $this->seedCwtCourses();

        $expected = [
            'it-security' => 'https://t.me/+-eN6uK3ouW0yNDcy',
            'ethical-hacking' => 'https://t.me/+5-SwMtYMCNc4NmRi',
            'programming' => 'https://t.me/+hbAArrXyuMVjNTY6',
        ];

        foreach ($expected as $slug => $url) {
            $course = Course::where('slug', $slug)->first();
            $this->assertNotNull($course, "Course {$slug} missing");

            $channel = $course->telegramChannel;
            $this->assertNotNull($channel, "Telegram channel for {$slug} missing");
            $this->assertSame($url, $channel->telegram_url);
            $this->assertTrue((bool) $channel->is_active);
            $this->assertStringStartsWith('https://t.me/', $channel->telegram_url);
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seedCwtCourses();
        $coursesBefore = Course::whereIn('slug', ['it-security', 'ethical-hacking', 'programming'])->count();
        $channelsBefore = TelegramChannel::count();

        $this->seedCwtCourses();
        $coursesAfter = Course::whereIn('slug', ['it-security', 'ethical-hacking', 'programming'])->count();
        $channelsAfter = TelegramChannel::count();

        $this->assertSame($coursesBefore, $coursesAfter);
        $this->assertSame($channelsBefore, $channelsAfter);
    }

    public function test_no_two_courses_share_the_same_telegram_url(): void
    {
        $this->seedCwtCourses();

        /** @var array<int, string> $urls */
        $urls = TelegramChannel::pluck('telegram_url')->all();
        $this->assertSame($urls, array_unique($urls), 'Telegram URLs must be unique per course');
    }

    public function test_each_course_uses_its_own_telegram_url_when_loaded_from_route(): void
    {
        $this->seedCwtCourses();

        $itSecurity = Course::where('slug', 'it-security')->with('telegramChannel')->firstOrFail();
        $ethical = Course::where('slug', 'ethical-hacking')->with('telegramChannel')->firstOrFail();
        $programming = Course::where('slug', 'programming')->with('telegramChannel')->firstOrFail();

        // Each course must use only its own link
        $this->assertNotNull($itSecurity->telegramChannel);
        $this->assertNotNull($ethical->telegramChannel);
        $this->assertNotNull($programming->telegramChannel);

        $this->assertSame('https://t.me/+-eN6uK3ouW0yNDcy', $itSecurity->telegramChannel->telegram_url);
        $this->assertSame('https://t.me/+5-SwMtYMCNc4NmRi', $ethical->telegramChannel->telegram_url);
        $this->assertSame('https://t.me/+hbAArrXyuMVjNTY6', $programming->telegramChannel->telegram_url);

        // Cross-check no leakage
        $this->assertNotSame($itSecurity->telegramChannel->telegram_url, $ethical->telegramChannel->telegram_url);
        $this->assertNotSame($ethical->telegramChannel->telegram_url, $programming->telegramChannel->telegram_url);
        $this->assertNotSame($itSecurity->telegramChannel->telegram_url, $programming->telegramChannel->telegram_url);
    }
}
