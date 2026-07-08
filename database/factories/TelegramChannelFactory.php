<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\TelegramChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TelegramChannel> */
class TelegramChannelFactory extends Factory
{
    protected $model = TelegramChannel::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => fake()->sentence(2),
            'private_channel_name' => fake()->word(),
            'telegram_url' => 'https://t.me/+'.fake()->regexify('[A-Za-z0-9_-]{16}'),
            'internal_channel_reference' => fake()->word(),
            'admin_note' => null,
            'is_active' => true,
        ];
    }
}
