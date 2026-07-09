<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Account lockout with exponential backoff to prevent brute-force attacks.
 * Stores failed-attempt counters in Redis (cache) for distributed safety.
 */
class AccountLockoutService
{
    private const BASE_LOCKOUT_SECONDS = 300;

    private const MAX_LOCKOUT_SECONDS = 3600;

    public function keyForIp(string $ip): string
    {
        return 'lockout:ip:'.$ip;
    }

    public function keyForEmail(string $email): string
    {
        return 'lockout:email:'.strtolower(trim($email));
    }

    public function isLocked(string $ip, string $email): bool
    {
        try {
            if (Cache::get($this->keyForIp($ip).':locked')) {
                return true;
            }

            if (Cache::get($this->keyForEmail($email).':locked')) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            // Cache unavailable: fail closed using database fallback.
            // Check AuditLog for recent failed login attempts.
            Log::critical('Cache unavailable, using database fallback for lockout check', [
                'ip' => $ip,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return $this->isLockedDatabaseFallback($ip);
        }
    }

    /**
     * Database fallback for lockout check when cache/Redis is unavailable.
     * Queries AuditLog for recent LOGIN_FAILED entries from the same IP.
     */
    private function isLockedDatabaseFallback(string $ip): bool
    {
        $thresholdValue = config('security.lockout.threshold', 5);
        $threshold = is_numeric($thresholdValue) ? (int) $thresholdValue : 5;

        $failedAttempts = AuditLog::query()
            ->where('action', AuditAction::LOGIN_FAILED)
            ->where('ip_address', $ip)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

        return $failedAttempts >= $threshold;
    }

    public function recordFailure(string $ip, string $email): void
    {
        $ipKey = $this->keyForIp($ip);
        $emailKey = $this->keyForEmail($email);

        $ipAttempts = (int) Cache::increment($ipKey);
        $emailAttempts = (int) Cache::increment($emailKey);

        Cache::put($ipKey, $ipAttempts, now()->addMinutes(15));
        Cache::put($emailKey, $emailAttempts, now()->addMinutes(15));

        $this->maybeLock($ipKey, $ipAttempts);
        $this->maybeLock($emailKey, $emailAttempts);

        if ($emailAttempts >= 5) {
            Log::warning('Suspicious login activity detected', [
                'ip' => $ip,
                'email' => $email,
                'attempts' => $emailAttempts,
            ]);
        }
    }

    public function recordSuccess(string $ip, string $email): void
    {
        Cache::forget($this->keyForIp($ip));
        Cache::forget($this->keyForEmail($email));
        Cache::forget($this->keyForIp($ip).':locked');
        Cache::forget($this->keyForEmail($email).':locked');
    }

    private function maybeLock(string $key, int $attempts): void
    {
        $thresholdValue = config('security.lockout.threshold', 5);
        $threshold = is_numeric($thresholdValue) ? (int) $thresholdValue : 5;

        if ($attempts < $threshold) {
            return;
        }

        $multiplier = min($attempts - $threshold + 1, 12);
        $duration = min(self::BASE_LOCKOUT_SECONDS * $multiplier, self::MAX_LOCKOUT_SECONDS);

        Cache::put($key.':locked', true, now()->addSeconds($duration));
    }

    public function remainingSeconds(string $ip, string $email): int
    {
        $ipLocked = Cache::get($this->keyForIp($ip).':locked');
        $emailLocked = Cache::get($this->keyForEmail($email).':locked');

        if (! $ipLocked && ! $emailLocked) {
            return 0;
        }

        return max(
            Cache::get($this->keyForIp($ip).':locked') ? 60 : 0,
            Cache::get($this->keyForEmail($email).':locked') ? 60 : 0,
        );
    }
}
