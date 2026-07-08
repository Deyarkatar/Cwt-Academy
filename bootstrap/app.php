<?php

use App\Http\Middleware\AdminAccountLockoutMiddleware;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\BruteForceDetectionMiddleware;
use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\HoneyTokenGuard;
use App\Http\Middleware\ReadReplicaMiddleware;
use App\Http\Middleware\ResponseCacheMiddleware;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackingRateLimitMiddleware;
use App\Http\Middleware\VerifyTurnstile;
use App\Support\Security\TrustedProxyValidator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // No webhook routes needed for manual Telegram access workflow
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 2026 hardening: strict trusted proxy validation.
        // In production, TRUSTED_PROXIES MUST be explicitly configured.
        // Empty or wildcard (*) values are rejected to prevent IP spoofing.
        $proxies = array_filter(explode(',', (string) env('TRUSTED_PROXIES', '')));
        $environment = (string) env('APP_ENV', '');

        (new TrustedProxyValidator)->validate($environment, $proxies);

        $middleware->trustProxies(
            at: $proxies,
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        // Honey-token breach detection: run before any request processing.
        $middleware->prepend(HoneyTokenGuard::class);

        // Assign request IDs for distributed tracing and log correlation.
        $middleware->prepend(AssignRequestId::class);

        // Brute-force detection: early in stack to block before processing.
        $middleware->prepend(BruteForceDetectionMiddleware::class);

        // Force HTTPS in production (and any time FORCE_HTTPS=true). Runs
        // before SecurityHeaders so the response has the correct scheme.
        $middleware->prepend(ForceHttps::class);

        // Apply SecurityHeaders + locale + response cache + read replica to WEB stack.
        // SecurityHeaders MUST come before ResponseCacheMiddleware: a cache HIT
        // short-circuits the pipeline, and headers must still be applied to it.
        $middleware->appendToGroup('web', [
            SetLocale::class,
            TrackingRateLimitMiddleware::class,
            SecurityHeaders::class,
            ResponseCacheMiddleware::class,
            ReadReplicaMiddleware::class,
        ]);

        // Apply SecurityHeaders + throttling to the API stack.
        // CSP is skipped for JSON responses inside the middleware itself, but
        // X-Content-Type-Options / X-Frame-Options / Referrer-Policy /
        // Cache-Control still apply, including on file downloads.
        $middleware->api(prepend: [
            ThrottleRequests::class.':api',
        ]);
        $middleware->appendToGroup('api', [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdminAuthenticated::class,
            'turnstile' => VerifyTurnstile::class,
            'lockout' => AdminAccountLockoutMiddleware::class,
        ]);

        $middleware->prepend(AdminAccountLockoutMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->respond(function (Response $response) {
            if ($response->getStatusCode() >= 500) {
                $content = $response->getContent();
                $data = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data['ok'] = false;
                    $data['message'] = $data['message'] ?? 'Server error';
                    $response->setContent(json_encode($data));
                }
            }

            return $response;
        });
    })->create();
