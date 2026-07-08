<?php

namespace App\Support\Security;

use Illuminate\Validation\Rules\Password;

/**
 * Centralised password policy used by all entry points (admin:create command,
 * web register, future password reset, profile change, etc.).
 *
 * 2026 NIST SP 800-63B / OWASP ASVS 5.0 alignment:
 *  - 12+ characters minimum (raised from legacy 8)
 *  - mixed case + digit + symbol
 *  - rejected against a curated weak-password list
 *  - rejected against HIBP (compromised) when enabled
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    /**
     * Curated list of disallowed passwords. Kept short on purpose — the real
     * defence is HIBP via `Password::uncompromised()` plus complexity rules.
     */
    public const WEAK_PASSWORDS = [
        'change-this-in-production',
        'change-me-with-secure-password',
        'admin1234',
        'admin12345',
        'password',
        'password123',
        'admin',
        '12345678',
        '123456789012',
        'qwerty12345',
        'letmein12345',
    ];

    /**
     * Default Laravel validation rule for any FormRequest needing a strong
     * password. Compromised-password lookup is enabled in production only
     * (it requires an outbound HTTP call to HIBP).
     */
    public static function rule(): Password
    {
        $rule = Password::min(self::MIN_LENGTH)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols();

        if (app()->environment('production')) {
            $rule->uncompromised();
        }

        return $rule;
    }

    /**
     * Cheap programmatic check used by the `admin:create` artisan command and
     * the production-readiness check. Returns null when valid, or a string
     * with the failure reason.
     */
    public static function validate(string $password): ?string
    {
        if (strlen($password) < self::MIN_LENGTH) {
            return sprintf('Password must be at least %d characters.', self::MIN_LENGTH);
        }

        if (! preg_match('/[a-z]/', $password)) {
            return 'Password must contain a lowercase letter.';
        }

        if (! preg_match('/[A-Z]/', $password)) {
            return 'Password must contain an uppercase letter.';
        }

        if (! preg_match('/\d/', $password)) {
            return 'Password must contain a digit.';
        }

        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Password must contain a symbol.';
        }

        if (in_array(strtolower($password), array_map('strtolower', self::WEAK_PASSWORDS), true)) {
            return 'Password matches a known weak password.';
        }

        return null;
    }
}
