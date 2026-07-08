<?php

namespace App\Models;

use Database\Factories\TelegramChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'course_id',
    'title',
    'private_channel_name',
    'telegram_url',
    'internal_channel_reference',
    'admin_note',
    'is_active',
])]
class TelegramChannel extends Model
{
    /** @use HasFactory<TelegramChannelFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function isUsable(): bool
    {
        return $this->is_active && ! empty($this->telegram_url);
    }
}
