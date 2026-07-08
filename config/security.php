<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies (2026)
    |--------------------------------------------------------------------------
    |
    | Restrict which upstream proxies the application is willing to trust for
    | X-Forwarded-* headers. Behind Cloudflare, set TRUSTED_PROXIES="*" only
    | when you're certain Cloudflare's IPs are the only thing that can reach
    | your origin (e.g. via Cloudflare Authenticated Origin Pulls). Otherwise
    | restrict to the actual upstream IPs.
    |
    | Comma-separated. Use "*" to trust any IP (only safe behind a managed
    | edge that authenticates origin connections).
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES', ''),
    'trusted_proxies_required_in_production' => true,

    'trusted_proxy_headers' => [
        // Standard reverse-proxy headers.
        'forwarded_for' => true,
        'forwarded_host' => true,
        'forwarded_port' => true,
        'forwarded_proto' => true,
        'forwarded' => true,
        // Cloudflare-specific (CF-Connecting-IP). The middleware will read
        // this when present.
        'cloudflare' => env('CLOUDFLARE_TRUST_CONNECTING_IP', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | CSP report-uri / report-to (optional)
    |--------------------------------------------------------------------------
    | Configure to receive Content-Security-Policy violation reports.
    */

    'csp_report_uri' => env('CSP_REPORT_URI', ''),

    /*
    |--------------------------------------------------------------------------
    | Force HTTPS in production
    |--------------------------------------------------------------------------
    |
    | When true and APP_ENV=production, the application will:
    |   - issue HTTPS URLs from URL::route(...) regardless of request scheme,
    |   - 301-redirect any HTTP request to HTTPS (handled by middleware).
    */

    'force_https' => (bool) env('FORCE_HTTPS', env('APP_ENV') === 'production'),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile / CAPTCHA
    |--------------------------------------------------------------------------
    |
    | Enable invisible/managed CAPTCHA on auth + payment-proof flows. Off by
    | default so the app remains testable without keys. To enable in prod:
    |
    |   CAPTCHA_DRIVER=turnstile
    |   TURNSTILE_SITE_KEY=0x4AAA...
    |   TURNSTILE_SECRET_KEY=0x4AAA...
    */

    'captcha' => [
        'driver' => env('CAPTCHA_DRIVER', ($_ENV['APP_ENV'] ?? '') === 'production' ? 'turnstile' : 'null'),
        'enforce_in_testing' => false,

        'turnstile' => [
            'site_key' => env('TURNSTILE_SITE_KEY'),
            'secret_key' => env('TURNSTILE_SECRET_KEY'),
            'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'script_url' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            'theme' => env('TURNSTILE_THEME', 'auto'),
            'timeout' => 5,
            'retry_times' => 1,
            'retry_sleep_ms' => 200,
            'circuit_threshold' => 5,
        ],

        'fail_open_on_network_error' => false,
    ],

    'lockout' => [
        'threshold' => 5,
        'base_duration_seconds' => 300,
        'max_duration_seconds' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit log retention
    |--------------------------------------------------------------------------
    | Days to retain audit log entries. After that the scheduled
    | `php artisan audit:prune` command (registered in routes/console.php)
    | will delete older rows.
    */

    'audit_retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Sensitive keys to redact from audit log payloads
    |--------------------------------------------------------------------------
    */

    'audit_redact_keys' => [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'api_token',
        'access_token',
        'refresh_token',
        'token',
        'secret',
        'authorization',
        'cookie',
        'cf-turnstile-response',
        'g-recaptcha-response',
        'h-captcha-response',
    ],
];
