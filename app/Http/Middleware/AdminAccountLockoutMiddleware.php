<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\AccountLockoutService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce account lockout on admin login routes.
 * Must run BEFORE the login controller processes credentials.
 */
class AdminAccountLockoutMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/admin/login') && ! $request->is('login')) {
            return $next($request);
        }

        $ip = $request->ip() ?? 'unknown';
        $email = $request->string('email', '')->lower()->trim()->toString();

        if ($email === '') {
            return $next($request);
        }

        $service = app(AccountLockoutService::class);

        if (! $service->isLocked($ip, $email)) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => 'Account temporarily locked due to too many failed attempts. Please try again later.',
            ], 423);
        }

        return redirect()->route('login')
            ->with('error', 'Account temporarily locked due to too many failed attempts. Please try again later.')
            ->withInput($request->only('email'));
    }
}
