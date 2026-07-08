<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force HTTPS for the application in production (and any time
 * `security.force_https` is enabled). Runs early in the pipeline so:
 *
 *   1. URL::route() / URL::to() generate HTTPS URLs unconditionally.
 *   2. Plain HTTP requests get a 301 to the HTTPS equivalent.
 *
 * Plays nicely with reverse proxies because TrustProxies has already
 * normalised `$request->isSecure()` and `$request->getScheme()`.
 *
 * The local health-check route (/up) is exempted to keep load balancer
 * probes simple.
 */
class ForceHttps
{
    /**
     * Private / loopback / link-local ranges that should NEVER be forced to
     * HTTPS so that `php artisan serve`, Docker, LAN testing, and mobile
     * device previews continue to work without TLS certificates.
     */
    private const LOCAL_RANGES = [
        '127.0.0.0/8',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '169.254.0.0/16',
        '::1/128',
        'fc00::/7',
        'fe80::/10',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldForceHttps($request)) {
            return $next($request);
        }

        // Make all subsequently-generated URLs HTTPS.
        URL::forceScheme('https');

        // If the original request came in over HTTP, redirect.
        if (! $request->isSecure() && $request->getMethod() === 'GET' && ! $this->isExempt($request)) {
            $target = 'https://'.$request->getHttpHost().$request->getRequestUri();

            return redirect()->to($target, 301);
        }

        return $next($request);
    }

    private function shouldForceHttps(Request $request): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        // NEVER force HTTPS for artisan serve / LAN / Docker / local dev.
        if ($this->isLocalRequest($request)) {
            return false;
        }

        // Explicit opt-in or production by default.
        return (bool) config('security.force_https', app()->environment('production'));
    }

    private function isLocalRequest(Request $request): bool
    {
        $host = $request->getHttpHost();

        // Common local hostnames (no TLS certs exist).
        $localHosts = ['localhost', '127.0.0.1', '[::1]', '0.0.0.0'];
        foreach ($localHosts as $lh) {
            if (str_starts_with($host, $lh)) {
                return true;
            }
        }

        // Check IP against private ranges.
        $ip = $request->ip();
        if ($ip === null) {
            return false;
        }

        foreach (self::LOCAL_RANGES as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    private function ipInRange(string $ip, string $range): bool
    {
        [$subnet, $bitsRaw] = explode('/', $range);
        $bits = is_numeric($bitsRaw) ? (int) $bitsRaw : 0;
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $mask = str_repeat("\xff", (int) ($bits / 8));
        $remainder = $bits % 8;
        if ($remainder > 0) {
            $mask .= chr(0xFF << (8 - $remainder));
        }

        return substr($ipBin, 0, strlen($mask)) === substr($subnetBin, 0, strlen($mask));
    }

    private function isExempt(Request $request): bool
    {
        // Health checks (load balancers) and well-known routes (Let's Encrypt
        // ACME challenges, etc.) must remain HTTP-reachable.
        return $request->is('up') || $request->is('.well-known/*');
    }
}
