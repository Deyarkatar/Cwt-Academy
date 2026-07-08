<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aggressive rate limiting and anomaly detection for the tracking endpoint.
 * Prevents enumeration attacks on tracking codes.
 */
class TrackingRateLimitMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('track') && ! $request->is('api/v1/course-requests/*/tracking')) {
            return $next($request);
        }

        $ip = $request->ip() ?? 'unknown';
        $code = $request->string('code')->toString();
        if ($code === '') {
            $routeCode = $request->route('trackingCode');
            $code = is_string($routeCode) ? $routeCode : '';
        }
        $prefix = strlen($code) >= 4 ? substr($code, 0, 4) : 'none';

        $ipKey = 'track:ip:'.$ip;
        $prefixKey = 'track:prefix:'.$prefix;

        if (RateLimiter::tooManyAttempts($ipKey, 20)) {
            return response()->json([
                'ok' => false,
                'message' => 'Too many tracking lookups. Please try again later.',
            ], 429);
        }

        if (RateLimiter::tooManyAttempts($prefixKey, 30)) {
            return response()->json([
                'ok' => false,
                'message' => 'Suspicious activity detected.',
            ], 429);
        }

        RateLimiter::hit($ipKey, decaySeconds: 60);
        RateLimiter::hit($prefixKey, decaySeconds: 60);

        return $next($request);
    }
}
