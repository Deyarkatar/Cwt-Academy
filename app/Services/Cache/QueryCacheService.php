<?php

declare(strict_types=1);

namespace App\Services\Cache;

use App\Models\Course;
use App\Models\CourseRequest;
use App\Models\PaymentProof;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Centralized query result caching with tagged invalidation.
 */
class QueryCacheService
{
    public static function remember(string $key, int $ttl, Closure $callback): mixed
    {
        /** @phpstan-ignore argument.templateType */
        return Cache::tags(['query'])->remember($key, $ttl, $callback);
    }

    public static function forget(string $key): void
    {
        Cache::tags(['query'])->forget($key);
    }

    public static function flush(): void
    {
        Cache::tags(['query'])->flush();
    }

    /** Warm the most frequently accessed dashboard statistics. */
    public static function warmDashboardStats(): void
    {
        self::remember('stats:pending_requests', 60, static fn (): int => CourseRequest::where('status', 'PENDING_REVIEW')->count()
        );

        self::remember('stats:pending_proofs', 60, static fn (): int => PaymentProof::where('status', 'PENDING')->count()
        );

        self::remember('stats:total_courses', 300, static fn (): int => Course::where('status', 'ACTIVE')->count()
        );

        self::remember('stats:total_students', 300, static fn (): int => User::count()
        );
    }
}
