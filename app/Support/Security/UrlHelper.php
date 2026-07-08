<?php

namespace App\Support\Security;

/**
 * URL sanitisation helpers (2026).
 *
 * Centralises all URL validation used before rendering into HTML attributes
 * (href, src, action) to prevent:
 *   - javascript: / data: / vbscript: / file: injection (XSS)
 *   - open-redirect abuse
 *   - path-traversal in file download routes
 */
final class UrlHelper
{
    private const SAFE_SCHEMES = ['https'];

    /**
     * Ensure a URL is safe to render into an HTML `href` / `src` attribute.
     *
     * Rules:
     *   1. Must be a valid parseable URL.
     *   2. Scheme must be in SAFE_SCHEMES (default: https only).
     *   3. Host must be present (no scheme-only strings).
     *   4. No null bytes or control characters.
     *
     * Returns the cleaned URL if safe, otherwise '#' (or a configured
     * fallback so the link becomes a no-op rather than blank / current page).
     */
    public static function safeHref(string $url, string $fallback = '#'): string
    {
        $trimmed = trim($url);

        if ($trimmed === '' || $trimmed === '#') {
            return $fallback;
        }

        // Reject null bytes and control characters early.
        if (preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $trimmed)) {
            return $fallback;
        }

        // Reject obvious dangerous schemes before parsing.
        $lower = strtolower($trimmed);
        $dangerousSchemes = ['javascript:', 'data:', 'vbscript:', 'file:', 'about:', 'blob:'];
        foreach ($dangerousSchemes as $bad) {
            if (str_starts_with($lower, $bad)) {
                return $fallback;
            }
        }

        $parsed = parse_url($trimmed);
        if ($parsed === false) {
            return $fallback;
        }

        $scheme = $parsed['scheme'] ?? '';
        if ($scheme === '') {
            // Allow protocol-relative URLs? No — force explicit scheme.
            return $fallback;
        }

        if (! in_array(strtolower($scheme), self::SAFE_SCHEMES, true)) {
            return $fallback;
        }

        $host = $parsed['host'] ?? '';
        if ($host === '') {
            return $fallback;
        }

        return $trimmed;
    }

    /**
     * Telegram-specific URL validator.
     *
     * Only allows:
     *   - https://t.me/  (public channels / groups)
     *   - https://telegram.me/  (alias domain)
     *
     * Anything else falls back to '#'.
     */
    public static function safeTelegramUrl(string $url, string $fallback = '#'): string
    {
        $safe = self::safeHref($url, $fallback);

        if ($safe === $fallback) {
            return $fallback;
        }

        $host = parse_url($safe, PHP_URL_HOST);
        $host = $host !== false && $host !== null ? strtolower($host) : '';
        $allowed = ['t.me', 'telegram.me', 'telegram.org'];

        if (! in_array($host, $allowed, true)) {
            return $fallback;
        }

        return $safe;
    }

    /**
     * Validate that a relative or absolute path does not contain path-traversal
     * sequences ('..'). Used before Storage::download() or file_exists().
     */
    public static function safePath(string $path): bool
    {
        $normalised = str_replace(['\\', '/'], '/', $path);
        $parts = explode('/', $normalised);

        foreach ($parts as $part) {
            if ($part === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * Enforce that a payment proof file path lives inside the expected directory.
     */
    public static function safePaymentProofPath(string $path): bool
    {
        if (! self::safePath($path)) {
            return false;
        }

        return str_starts_with($path, 'payment_proofs/');
    }

    /**
     * Same-origin redirect validation.
     *
     * Returns the `$url` if it is on the same origin as the application,
     * otherwise returns `$fallback` (default '/').
     *
     * Prevents open-redirect attacks from attacker-controlled Referer /
     * previous-url values.
     */
    public static function safeRedirect(string $url, string $fallback = '/'): string
    {
        $trimmed = trim($url);

        if ($trimmed === '' || $trimmed === $fallback) {
            return $fallback;
        }

        // Reject null bytes and newlines.
        if (preg_match('/[\x00\x0a\x0d]/', $trimmed)) {
            return $fallback;
        }

        // If the URL starts with a scheme, enforce same-origin.
        if (preg_match('/^https?:\/\//i', $trimmed)) {
            $appUrlRaw = config('app.url');
            $appUrl = rtrim(is_string($appUrlRaw) ? $appUrlRaw : '', '/');
            if ($appUrl === '') {
                return $fallback;
            }

            $allowedPrefixes = [$appUrl];
            // In local dev, APP_URL=http://localhost but the server may be on 127.0.0.1:8000.
            // Allow common local origins so locale switching redirects correctly.
            if ($appUrl === 'http://localhost' || $appUrl === 'https://localhost') {
                $allowedPrefixes[] = 'http://127.0.0.1';
                $allowedPrefixes[] = 'https://127.0.0.1';
                $allowedPrefixes[] = 'http://[::1]';
                $allowedPrefixes[] = 'https://[::1]';
            }

            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($trimmed, $prefix)) {
                    return $trimmed;
                }
            }

            return $fallback;
        }

        // If the URL starts with '//' it's a protocol-relative URL.
        if (str_starts_with($trimmed, '//')) {
            return $fallback;
        }

        // Relative URLs (starting with /) are safe.
        if (str_starts_with($trimmed, '/')) {
            return $trimmed;
        }

        // Anything else (e.g. bare hostname, scheme-less) is rejected.
        return $fallback;
    }
}
