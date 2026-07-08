<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Intrusion detection via brute-force pattern analysis.
 * Tracks failed login / unauthorized response patterns per IP
 * and temporarily blocks IPs that exceed thresholds.
 */
class BruteForceDetectionMiddleware
{
    private const string WINDOW = '300';          // 5 minutes

    private const string THRESHOLD = '10';        // attempts before warning

    private const string BLOCK_THRESHOLD = '20';    // attempts before block

    private const string BLOCK_DURATION = '3600';   // 1 hour block

    public function handle(Request $request, Closure $next): Response
    {
        $ip = (string) $request->ip();
        $blockKey = "intrusion:blocked:{$ip}";

        try {
            if (Cache::get($blockKey, false)) {
                Log::warning('Blocked IP attempted access', [
                    'ip' => $ip,
                    'path' => $request->path(),
                ]);
                abort(429, 'Too many failed attempts. IP temporarily blocked.');
            }
        } catch (\Exception $e) {
            // Cache unavailable, skip brute force detection
            Log::warning('Cache unavailable, skipping brute force detection', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
        }

        $response = $next($request);

        $status = $response->getStatusCode();
        if ($status === 401 || $status === 403 || ($status === 422 && $this->isAuthPath($request))) {
            $this->recordFailure($ip, $request);
        }

        return $response;
    }

    /**
     * Determine whether the request targets an authentication endpoint.
     * Web login failures are reported as 422 ValidationException, so we
     * explicitly track those in addition to 401/403 responses.
     */
    private function isAuthPath(Request $request): bool
    {
        return $request->is('login', 'register', 'webauthn/login', 'webauthn/login/options', 'auth/*', 'admin/login');
    }

    private function recordFailure(string $ip, Request $request): void
    {
        try {
            $key = "intrusion:failed:{$ip}";
            $previous = Cache::get($key, 0);
            $count = is_int($previous) ? $previous + 1 : 1;
            Cache::put($key, $count, (int) self::WINDOW);

            Log::info('Failed auth attempt recorded', [
                'ip' => $ip,
                'count' => $count,
                'path' => $request->path(),
            ]);

            if ($count >= (int) self::BLOCK_THRESHOLD) {
                Cache::put("intrusion:blocked:{$ip}", true, (int) self::BLOCK_DURATION);
                Log::critical('IP blocked due to brute force pattern', [
                    'ip' => $ip,
                    'count' => $count,
                ]);
            } elseif ($count >= (int) self::THRESHOLD) {
                Log::warning('Brute force threshold reached', [
                    'ip' => $ip,
                    'count' => $count,
                ]);
            }
        } catch (\Exception $e) {
            // Cache unavailable, skip failure recording
            Log::warning('Cache unavailable, skipping failure recording', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
