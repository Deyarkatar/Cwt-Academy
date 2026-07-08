<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response-level HTTP caching for public GET routes.
 * Bypasses for authenticated users and non-safe methods.
 */
class ResponseCacheMiddleware
{
    /** @var array<string, int> route pattern => TTL in seconds */
    private array $cacheableRoutes = [
        '/' => 300,
        'courses' => 300,
        'courses/*' => 600,
        'track' => 0,        // never cache (contains form)
        'contact' => 0,      // never cache (may contain a form; social links are cheap to render)
        'about' => 3600,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $key = $this->cacheKey($request);

        if (Cache::has($key)) {
            $cached = Cache::get($key);

            if (is_string($cached) && $cached !== '') {
                return response($cached)
                    ->header('X-Response-Cache', 'HIT');
            }

            Cache::forget($key);
        }

        $response = $next($request);

        if ($response->isSuccessful() && $this->isCacheableRoute($request)) {
            $ttl = $this->getTtl($request);
            $content = $response->getContent();
            if ($ttl > 0 && is_string($content) && $content !== '') {
                Cache::put($key, $content, $ttl);
                $response->headers->set('X-Response-Cache', 'MISS');
            }
        }

        return $response;
    }

    private function shouldSkip(Request $request): bool
    {
        return ! $request->isMethodSafe()
            || auth()->check();
    }

    private function cacheKey(Request $request): string
    {
        return 'response:v1:'.hash('xxh3', $request->getPathInfo().'?'.($request->getQueryString() ?? '').':'.app()->getLocale());
    }

    private function isCacheableRoute(Request $request): bool
    {
        foreach (array_keys($this->cacheableRoutes) as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function getTtl(Request $request): int
    {
        foreach ($this->cacheableRoutes as $pattern => $ttl) {
            if ($request->is($pattern)) {
                return $ttl;
            }
        }

        return 0;
    }
}
