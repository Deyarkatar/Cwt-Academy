<?php

namespace App\Http\Middleware;

use App\Services\Captcha\TurnstileService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class VerifyTurnstile
{
    public function handle(Request $request, Closure $next): Response
    {
        $service = app(TurnstileService::class);

        if (! $service->enabled()) {
            return $next($request);
        }

        $key = 'captcha|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            return response()->json(['ok' => false, 'message' => 'Too many CAPTCHA attempts.'], 429);
        }

        $token = $request->input('cf-turnstile-response') ?? $request->input('captcha_token');
        $token = is_string($token) ? $token : null;
        $result = $service->verify($token, $request->ip());

        if (! $result['success']) {
            RateLimiter::hit($key);

            return response()->json([
                'ok' => false,
                'message' => 'CAPTCHA verification failed. Please try again.',
            ], 422);
        }

        RateLimiter::clear($key);

        return $next($request);
    }
}
