<?php

return [
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),
    'release' => env('SENTRY_RELEASE', config('app.version', 'dev')),
    'environment' => env('APP_ENV', 'production'),
    'breadcrumbs' => [
        'sql_bindings' => false,
    ],
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.01),
    'attach_stacktrace' => true,
    'send_default_pii' => false,
];
