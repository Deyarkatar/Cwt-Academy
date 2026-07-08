<?php

namespace App\Services\Courses;

use App\Models\Course;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CourseService
{
    private const LIST_TTL = 3600;

    private const SLUG_TTL = 3600;

    private const FEATURED_TTL = 3600;

    private const MAX_PAGE = 100;

    private const MAX_SEARCH_LENGTH = 100;

    private const LIST_VERSION_KEY = 'courses.list:version';

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Course>
     */
    public function listActive(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $page = min(request()->integer('page', 1), self::MAX_PAGE);
        $allowedKeys = ['category', 'level', 'language', 'search'];
        $normalized = array_intersect_key($filters, array_flip($allowedKeys));
        $normalized = array_filter($normalized, fn ($v) => $v !== null && $v !== '');

        if (! empty($normalized['search']) && is_string($normalized['search'])) {
            $normalized['search'] = substr(
                strtolower(trim($normalized['search'])),
                0,
                self::MAX_SEARCH_LENGTH,
            );
        }

        ksort($normalized);
        $hashInput = serialize($normalized).':'.$perPage.':'.$page;
        $cacheKey = 'courses.list:v'.$this->listVersion().':'.hash('xxh3', $hashInput);

        $cached = $this->rememberWithLock(
            $cacheKey,
            self::LIST_TTL,
            function () use ($normalized, $perPage, $page) {
                $paginator = $this->queryListActive($normalized, $perPage, $page);

                return [
                    'ids' => collect($paginator->items())->map(fn ($course) => $course->id)->toArray(),
                    'total' => $paginator->total(),
                ];
            },
        );

        $ids = array_values(array_filter(
            array_map(static function (mixed $id): ?int {
                if (is_int($id)) {
                    return $id;
                }

                return is_numeric($id) ? (int) $id : null;
            }, (array) ($cached['ids'] ?? [])),
        ));
        $items = $this->hydrateCourses($ids);

        return new LengthAwarePaginator($items, (int) ($cached['total'] ?? 0), $perPage, $page);
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return LengthAwarePaginator<int, Course>
     */
    private function queryListActive(array $normalized, int $perPage, int $page): LengthAwarePaginator
    {
        $query = Course::query()
            ->with(['category', 'instructor'])
            ->active();

        if (! empty($normalized['category'])) {
            $query->whereHas('category', function ($q) use ($normalized) {
                $q->where('slug', $normalized['category']);
            });
        }

        if (! empty($normalized['level'])) {
            $query->where('level', $normalized['level']);
        }

        if (! empty($normalized['language'])) {
            $query->where('language', $normalized['language']);
        }

        if (! empty($normalized['search']) && is_string($normalized['search'])) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $normalized['search']);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('short_description', 'like', '%'.$search.'%');
            });
        }

        return $query->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getActiveBySlug(string $slug): ?Course
    {
        $cacheKey = 'course.slug:'.$slug;

        $id = $this->rememberWithLock(
            $cacheKey,
            self::SLUG_TTL,
            fn () => $this->queryActiveBySlug($slug)?->id,
        );

        return $id ? $this->hydrateCourse($id) : null;
    }

    /**
     * Cached featured/home courses. Invalidated with the list version bump.
     *
     * @return Collection<int, Course>
     */
    public function featuredForHome(int $limit = 3): Collection
    {
        $cacheKey = 'courses.home:v'.$this->listVersion().':'.$limit;

        $ids = $this->rememberWithLock(
            $cacheKey,
            self::FEATURED_TTL,
            fn () => Course::query()
                ->active()
                ->orderByDesc('is_featured')
                ->orderByDesc('published_at')
                ->limit($limit)
                ->pluck('id')
                ->toArray(),
        );

        $ids = array_values(array_filter(
            array_map(static function (mixed $id): ?int {
                if (is_int($id)) {
                    return $id;
                }

                return is_numeric($id) ? (int) $id : null;
            }, (array) $ids),
        ));

        return $this->hydrateCourses($ids);
    }

    private function queryActiveBySlug(string $slug): ?Course
    {
        return Course::query()
            ->with(['category', 'instructor', 'telegramChannel'])
            ->active()
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Rehydrate a list of cached course IDs into a Collection of Eloquent models,
     * preserving the original order and eager-loading the relations used by the UI.
     *
     * @param  array<int>  $ids
     * @return Collection<int, Course>
     */
    private function hydrateCourses(array $ids): Collection
    {
        if (empty($ids)) {
            return new Collection;
        }

        $courses = Course::query()
            ->with(['category', 'instructor'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $ordered = [];
        foreach ($ids as $id) {
            if ($course = $courses->get($id)) {
                $ordered[] = $course;
            }
        }

        return new Collection($ordered);
    }

    /**
     * Rehydrate a single cached course ID into a full Eloquent model.
     */
    private function hydrateCourse(int $id): ?Course
    {
        return Course::query()
            ->with(['category', 'instructor', 'telegramChannel'])
            ->where('id', $id)
            ->first();
    }

    /**
     * Invalidate every cached course list/home page atomically by bumping
     * the version segment embedded in all list cache keys. O(1) regardless
     * of how many filter/page combinations are cached, and driver-agnostic
     * (works on redis, file, database, and array stores alike).
     */
    public function flushListCache(): void
    {
        try {
            Cache::add(self::LIST_VERSION_KEY, 0);
            Cache::increment(self::LIST_VERSION_KEY);
        } catch (\Exception $e) {
            // Cache unavailable, skip cache flush
            Log::warning('Cache unavailable, skipping list cache flush', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function forgetSlugCache(string $slug): void
    {
        try {
            Cache::forget('course.slug:'.$slug);
        } catch (\Exception $e) {
            // Cache unavailable, skip cache forget
            Log::warning('Cache unavailable, skipping slug cache forget', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function listVersion(): int
    {
        try {
            $version = Cache::get(self::LIST_VERSION_KEY, 0);

            return is_int($version) ? $version : (is_numeric($version) ? (int) $version : 0);
        } catch (\Exception $e) {
            // Cache unavailable, return default version
            Log::warning('Cache unavailable, returning default list version', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Stampede-safe read-through cache: on a cold key, only one request
     * rebuilds the value while concurrent requests fall back to a direct
     * query instead of piling onto the lock. Reads and writes use the
     * exact same un-tagged key so cache hits work on every driver.
     *
     * @template TValue
     *
     * @param  callable(): TValue  $resolver
     * @return TValue
     */
    private function rememberWithLock(string $cacheKey, int $ttl, callable $resolver)
    {
        try {
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            $lock = Cache::lock($cacheKey.':lock', 10);

            try {
                if (! $lock->get()) {
                    return $resolver();
                }

                // Guard against the race where another request rebuilt the
                // cache while this one was waiting for the lock.
                if (Cache::has($cacheKey)) { // @phpstan-ignore if.alwaysFalse
                    $lock->release();

                    return Cache::get($cacheKey);
                }

                $result = $resolver();

                Cache::put($cacheKey, $result, $ttl);

                $lock->release();

                return $result;
            } catch (\Throwable $e) {
                try {
                    $lock->release();
                } catch (\Throwable) {
                }

                Log::warning('CourseService cache lock failed', ['error' => $e->getMessage()]);

                return $resolver();
            }
        } catch (\Exception $e) {
            // Cache unavailable, execute resolver directly
            Log::warning('Cache unavailable, executing resolver directly', [
                'cacheKey' => $cacheKey,
                'error' => $e->getMessage(),
            ]);

            return $resolver();
        }
    }
}
