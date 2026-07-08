<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Honey-token breach detector.
 *
 * The application intentionally exposes fake secret values (honey tokens) in
 * configuration that are NEVER used by legitimate code. If a request contains
 * one of these values, it strongly indicates a configuration leak or an
 * attacker probing the environment. We log a critical alert and abort.
 *
 * The tokens are compared using hash-equality to avoid timing side-channels.
 */
class HoneyTokenGuard
{
    /**
     * @var array<string, string>
     */
    private array $tokens;

    public function __construct()
    {
        $tokens = config('app.honey_tokens');
        if (! is_array($tokens)) {
            $tokens = [];
        }

        $this->tokens = [
            'fake_aws_access_key' => is_string($tokens['fake_aws_access_key'] ?? null) ? $tokens['fake_aws_access_key'] : '',
            'fake_aws_secret_key' => is_string($tokens['fake_aws_secret_key'] ?? null) ? $tokens['fake_aws_secret_key'] : '',
            'fake_db_password' => is_string($tokens['fake_db_password'] ?? null) ? $tokens['fake_db_password'] : '',
        ];
    }

    public function handle(Request $request, Closure $next): Response
    {
        $haystack = $this->requestHaystack($request);

        foreach ($this->tokens as $name => $token) {
            if ($token === '') {
                continue;
            }

            if ($this->containsToken($haystack, $token)) {
                Log::critical('Honey token detected in request', [
                    'token_name' => $name,
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                    'user_agent' => $request->userAgent(),
                ]);

                abort(403, 'Unauthorized');
            }
        }

        return $next($request);
    }

    /**
     * Build a single string haystack from request headers and body values.
     *
     * @return array<int, string>
     */
    private function requestHaystack(Request $request): array
    {
        $values = [];

        foreach ($request->headers->all() as $headerValues) {
            foreach (is_array($headerValues) ? $headerValues : [$headerValues] as $value) {
                if (is_scalar($value)) {
                    $values[] = strval($value);
                }
            }
        }

        foreach ($request->all() as $value) {
            if (is_array($value)) {
                $this->flatten($value, $values);
            } elseif (is_scalar($value)) {
                $values[] = strval($value);
            }
        }

        return array_values(array_filter($values, static fn (string $v): bool => $v !== ''));
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @param  array<int, string>  $output
     */
    private function flatten(array $input, array &$output): void
    {
        foreach ($input as $value) {
            if (is_array($value)) {
                $this->flatten($value, $output);
            } elseif (is_scalar($value)) {
                $output[] = strval($value);
            }
        }
    }

    /**
     * @param  array<int, string>  $haystack
     */
    private function containsToken(array $haystack, string $token): bool
    {
        $tokenLength = strlen($token);
        if ($tokenLength === 0) {
            return false;
        }

        $tokenHash = hash('sha256', $token);

        foreach ($haystack as $value) {
            $valueLength = strlen($value);
            if ($valueLength < $tokenLength) {
                continue;
            }

            // Exact-match check for values that are exactly the token.
            if (hash_equals($tokenHash, hash('sha256', $value))) {
                return true;
            }

            // Substring check only on reasonably-sized values to prevent CPU
            // exhaustion from huge request bodies.
            if ($valueLength <= 10000 && str_contains($value, $token)) {
                return true;
            }
        }

        return false;
    }
}
