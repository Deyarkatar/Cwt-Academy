<?php

declare(strict_types=1);

namespace App\Services\CircuitBreaker;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Circuit breaker pattern for external service resilience.
 * Protects the application from cascading failures.
 */
class CircuitBreaker
{
    private const int FAILURE_THRESHOLD = 5;

    private const int TIMEOUT = 60;

    private const string PREFIX = 'circuit:';

    public function __construct(private readonly string $name) {}

    public function call(callable $callback, mixed $fallback = null): mixed
    {
        $state = $this->state();

        if ($state === 'OPEN') {
            Log::warning("Circuit OPEN for {$this->name}");

            return $fallback;
        }

        try {
            $result = $callback();
            $this->recordSuccess();

            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();
            Log::error("Circuit failure for {$this->name}", [
                'error' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }

    public function state(): string
    {
        $failuresRaw = Cache::get(self::PREFIX."{$this->name}:failures", 0);
        $failures = is_int($failuresRaw) ? $failuresRaw : (is_numeric($failuresRaw) ? (int) $failuresRaw : 0);
        $lastFailureRaw = Cache::get(self::PREFIX."{$this->name}:last", 0);
        $lastFailure = is_int($lastFailureRaw) ? $lastFailureRaw : (is_numeric($lastFailureRaw) ? (int) $lastFailureRaw : 0);

        if ($failures >= self::FAILURE_THRESHOLD) {
            if (time() - $lastFailure > self::TIMEOUT) {
                return 'HALF_OPEN';
            }

            return 'OPEN';
        }

        return 'CLOSED';
    }

    private function recordSuccess(): void
    {
        Cache::delete(self::PREFIX."{$this->name}:failures");
        Cache::delete(self::PREFIX."{$this->name}:last");
    }

    private function recordFailure(): void
    {
        Cache::increment(self::PREFIX."{$this->name}:failures");
        Cache::put(self::PREFIX."{$this->name}:last", time(), self::TIMEOUT * 2);
    }
}
