<?php

namespace App\Models;

use App\Enums\CourseLanguage;
use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'category_id',
    'instructor_id',
    'title',
    'slug',
    'short_description',
    'description',
    'price_iqd',
    'thumbnail',
    'level',
    'language',
])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_iqd' => 'integer',
            'level' => CourseLevel::class,
            'language' => CourseLanguage::class,
            'status' => CourseStatus::class,
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'learning_points' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Instructor, $this>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * @return HasOne<TelegramChannel, $this>
     */
    public function telegramChannel(): HasOne
    {
        return $this->hasOne(TelegramChannel::class);
    }

    /**
     * @return HasMany<CourseRequest, $this>
     */
    public function courseRequests(): HasMany
    {
        return $this->hasMany(CourseRequest::class);
    }

    /**
     * @param  Builder<Course>  $query
     * @return Builder<Course>
     */
    public function scopeActive($query)
    {
        return $query->where('status', CourseStatus::ACTIVE);
    }

    /**
     * @param  Builder<Course>  $query
     * @return Builder<Course>
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function isActive(): bool
    {
        return $this->status === CourseStatus::ACTIVE;
    }
}
