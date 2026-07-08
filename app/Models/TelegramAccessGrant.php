<?php

namespace App\Models;

use App\Enums\TelegramAccessGrantStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'course_request_id',
    'course_id',
    'student_name',
    'student_email',
    'student_phone',
    'admin_note',
    'manual_access_reference',
])]
class TelegramAccessGrant extends Model
{
    protected function casts(): array
    {
        return [
            'status' => TelegramAccessGrantStatus::class,
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CourseRequest, $this>
     */
    public function courseRequest(): BelongsTo
    {
        return $this->belongsTo(CourseRequest::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            TelegramAccessGrantStatus::PENDING_MANUAL_ADD,
            TelegramAccessGrantStatus::MANUALLY_ADDED,
            TelegramAccessGrantStatus::ACCESS_SENT,
        ], true);
    }
}
