<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return redirect('/login');
        }

        // Enforce email verification for all admin routes
        if (! $request->user()->hasVerifiedEmail()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Email verification required.',
                ], 403);
            }

            return redirect()->route('verification.notice');
        }

        // For API routes authenticated via Sanctum, verify the token has the
        // 'admin' ability. This is defense-in-depth: the isAdmin() check above
        // is the primary guard. Existing tokens with ['*'] still pass tokenCan().
        $accessToken = $request->user()->currentAccessToken();
        if ($accessToken && $request->is('api/*') && ! $request->user()->tokenCan('admin')) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        if (! $request->user()->isAdmin()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Unauthorized. Admin access required.',
                ], 403);
            }

            return redirect('/dashboard')->with('error', __('errors.unauthorized'));
        }

        return $next($request);
    }
}
