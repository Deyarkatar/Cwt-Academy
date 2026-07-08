<?php

namespace App\Models;

use App\Enums\CourseRequestStatus;
use Database\Factories\CourseRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'course_id',
    'student_name',
    'student_email',
    'student_phone',
    'student_city',
    'payment_method',
    'student_note',
    'admin_note',
])]
class CourseRequest extends Model
{
    /** @use HasFactory<CourseRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => CourseRequestStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'student_name' => 'encrypted',
            'student_email' => 'encrypted',
            'student_phone' => 'encrypted',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->public_tracking_code)) {
                $attempts = 0;
                do {
                    $model->public_tracking_code = strtoupper(Str::random(16));
                    $attempts++;
                } while (self::where('public_tracking_code', $model->public_tracking_code)->exists() && $attempts < 10);

                if (empty($model->public_tracking_code)) {
                    $model->public_tracking_code = strtoupper((string) Str::uuid());
                }
            }
        });
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PaymentProof, $this>
     */
    public function paymentProofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }

    /**
     * @return HasOne<PaymentProof, $this>
     */
    public function latestPaymentProof(): HasOne
    {
        return $this->hasOne(PaymentProof::class)->ofMany([
            'created_at' => 'max',
            'id' => 'max',
        ]);
    }

    /**
     * @return HasOne<TelegramAccessGrant, $this>
     */
    public function telegramAccessGrant(): HasOne
    {
        return $this->hasOne(TelegramAccessGrant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * @param  Builder<CourseRequest>  $query
     * @return Builder<CourseRequest>
     */
    public function scopePendingPayment($query)
    {
        return $query->where('status', CourseRequestStatus::PENDING_PAYMENT);
    }

    /**
     * @param  Builder<CourseRequest>  $query
     * @return Builder<CourseRequest>
     */
    public function scopePendingReview($query)
    {
        return $query->where('status', CourseRequestStatus::PENDING_REVIEW);
    }

    /**
     * @param  Builder<CourseRequest>  $query
     * @return Builder<CourseRequest>
     */
    public function scopeApproved($query)
    {
        return $query->where('status', CourseRequestStatus::APPROVED);
    }

    public function canSubmitPaymentProof(): bool
    {
        return $this->status === CourseRequestStatus::PENDING_PAYMENT;
    }

    public function isApproved(): bool
    {
        return $this->status === CourseRequestStatus::APPROVED;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [
            CourseRequestStatus::PENDING_PAYMENT,
            CourseRequestStatus::PENDING_REVIEW,
        ], true);
    }

    public function isRejected(): bool
    {
        return in_array($this->status, [
            CourseRequestStatus::REJECTED,
            CourseRequestStatus::EXPIRED,
            CourseRequestStatus::REVOKED,
        ], true);
    }
}
