<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Asynchronous cache invalidation to prevent cache-bust operations
 * from adding latency to admin mutation requests.
 */
class CacheBustJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $backoff = 5;

    /**
     * @param  array<string>  $keys  Individual cache keys to forget.
     * @param  array<string>  $tags  Cache tags to flush (if driver supports tags).
     * @param  bool  $bumpVersion  Whether to increment the listing version key.
     */
    public function __construct(
        private readonly array $keys = [],
        private readonly array $tags = [],
        private readonly bool $bumpVersion = false,
    ) {}

    public function handle(): void
    {
        foreach ($this->keys as $key) {
            try {
                Cache::forget($key);
            } catch (\Throwable $e) {
                Log::warning('CacheBustJob: failed to forget key', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }

        foreach ($this->tags as $tag) {
            try {
                Cache::tags($tag)->flush();
            } catch (\Throwable $e) {
                Log::warning('CacheBustJob: failed to flush tag', ['tag' => $tag, 'error' => $e->getMessage()]);
            }
        }

        if ($this->bumpVersion) {
            try {
                Cache::increment('courses.list:version');
            } catch (\Throwable $e) {
                Log::warning('CacheBustJob: failed to bump version', ['error' => $e->getMessage()]);
            }
        }
    }
}
