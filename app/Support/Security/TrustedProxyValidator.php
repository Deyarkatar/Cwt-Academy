<?php

namespace App\Support\Security;

use RuntimeException;

/**
 * Validates TRUSTED_PROXIES configuration for production deployments.
 *
 * Centralising the logic makes it testable while keeping the bootstrap
 * runtime check a thin wrapper around the same rules.
 */
final class TrustedProxyValidator
{
    /**
     * @param  list<string>  $proxies
     *
     * @throws RuntimeException
     */
    public function validate(string $environment, array $proxies): void
    {
        if ($environment !== 'production') {
            return;
        }

        if ($proxies === []) {
            throw new RuntimeException(
                'TRUSTED_PROXIES must be configured in production. '.
                'Set it to your load balancer / CDN IP ranges (e.g. Cloudflare: 173.245.48.0/20,...).'
            );
        }

        $wildcards = array_filter(
            $proxies,
            static fn (string $proxy): bool => $proxy === '*' || $proxy === '0.0.0.0/0' || $proxy === '::/0',
        );

        if ($wildcards !== []) {
            throw new RuntimeException(
                'TRUSTED_PROXIES contains wildcard entries in production. '.
                'This allows X-Forwarded-For spoofing. Use exact IP ranges.'
            );
        }
    }
}
