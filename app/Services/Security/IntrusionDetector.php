<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Account-level intrusion detection for failed authentication attempts.
 * Tracks per-identifier (email/username) to detect distributed attacks.
 */
class IntrusionDetector
{
    private const int WINDOW_SECONDS = 900;    // 15 minutes

    private const int WARN_THRESHOLD = 5;        // warning at 5

    private const int BLOCK_THRESHOLD = 10;      // block at 10

    private const int BLOCK_DURATION = 3600;     // 1 hour

    public static function recordFailedLogin(string $identifier): void
    {
        $key = self::key($identifier);
        $previous = Cache::get($key, 0);
        $count = is_int($previous) ? $previous + 1 : (is_numeric($previous) ? (int) $previous + 1 : 1);
        Cache::put($key, $count, self::WINDOW_SECONDS);

        if ($count >= self::BLOCK_THRESHOLD) {
            Cache::put(self::blockKey($identifier), true, self::BLOCK_DURATION);
            Log::critical('Account temporarily locked due to brute force', [
                'identifier_hash' => hash('sha256', $identifier),
                'count' => $count,
            ]);
        } elseif ($count >= self::WARN_THRESHOLD) {
            Log::warning('Repeated failed login attempts detected', [
                'identifier_hash' => hash('sha256', $identifier),
                'count' => $count,
            ]);
        }
    }

    public static function isBlocked(string $identifier): bool
    {
        return (bool) Cache::get(self::blockKey($identifier), false);
    }

    public static function clear(string $identifier): void
    {
        Cache::delete(self::key($identifier));
        Cache::delete(self::blockKey($identifier));
    }

    private static function key(string $identifier): string
    {
        return 'intrusion:login:'.hash('sha256', $identifier);
    }

    private static function blockKey(string $identifier): string
    {
        return 'intrusion:blocked:'.hash('sha256', $identifier);
    }
}
