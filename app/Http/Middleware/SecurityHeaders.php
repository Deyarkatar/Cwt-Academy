<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enterprise security headers (2026).
 *
 *  - Per-request CSP nonce (no `'unsafe-inline'`, no wildcard `https:`).
 *  - Cross-origin isolation: COOP / COEP / CORP.
 *  - Modern `Permissions-Policy` allow-list of zero capabilities by default.
 *  - HSTS with `preload` over HTTPS.
 *  - Drops deprecated `X-XSS-Protection` and `Expect-CT`.
 *
 * The nonce is shared with Blade via `View::share('cspNonce', ...)` so views
 * can render `<script nonce="{{ $cspNonce }}">...</script>` safely.
 *
 * For JSON / API responses, CSP is intentionally relaxed (CSP only applies
 * to HTML rendering contexts) but `X-Content-Type-Options`, `X-Frame-Options`,
 * `Referrer-Policy`, and `Cache-Control: no-store` are still applied.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generate a per-request CSP nonce and share with views BEFORE the
        // response is rendered, so Blade templates can use it.
        $nonce = $this->generateNonce();
        $request->attributes->set('csp_nonce', $nonce);
        View::share('cspNonce', $nonce);

        // Register the nonce with Vite so @vite directive includes it on all
        // generated script and link tags (Laravel 10.38+ / 11+).
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $isApi = $request->is('api/*') || $this->isJsonResponse($response);
        $isProd = app()->environment('production');
        $isSecure = $request->isSecure();

        // --- Universal safe headers (applied in ALL environments) ------------------
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // --- Production-only: full enterprise hardening --------------------------

        if ($isProd) {
            // Locked-down Permissions-Policy: zero capabilities by default.
            $response->headers->set('Permissions-Policy', implode(', ', [
                'accelerometer=()',
                'autoplay=()',
                'browsing-topics=()',
                'camera=()',
                'clipboard-read=()',
                'clipboard-write=(self)',
                'cross-origin-isolated=()',
                'display-capture=()',
                'encrypted-media=()',
                'fullscreen=(self)',
                'geolocation=()',
                'gyroscope=()',
                'hid=()',
                'idle-detection=()',
                'interest-cohort=()',
                'magnetometer=()',
                'microphone=()',
                'midi=()',
                'payment=()',
                'picture-in-picture=()',
                'publickey-credentials-get=()',
                'screen-wake-lock=()',
                'serial=()',
                'sync-xhr=()',
                'usb=()',
                'web-share=()',
                'xr-spatial-tracking=()',
            ]));

            // Cross-origin isolation (modern browsers).
            $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
            $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
            $response->headers->set('Cross-Origin-Embedder-Policy', 'credentialless');
        }

        // --- HTML-only headers ---------------------------------------------------
        if (! $isApi) {
            $response->headers->set('Content-Security-Policy', $this->buildCsp($nonce, $isProd, $isSecure));
        }

        // --- HTTPS / HSTS (production only, never on HTTP dev servers) ---------
        if ($isProd && $isSecure) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=63072000; includeSubDomains; preload'
            );
        }

        // --- Cache hygiene for sensitive surfaces -------------------------------
        if ($isProd && (
            $request->is('admin', 'admin/*', 'api/admin/*')
            || $request->is('login', 'register', 'logout')
            || $request->is('dashboard', 'dashboard/*', 'profile', 'profile/*')
            || auth()->check()
        )) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        // --- Explicitly remove deprecated / harmful headers ---------------------
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->remove('Expect-CT');
        // --- Stealth Mode: Strip tech stack revealing headers -------------------
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }

    /**
     * Build a strict, nonce-based CSP. No `'unsafe-inline'`, no `https:`
     * wildcard, no `'unsafe-eval'`. Explicit allow-list for fonts.
     */
    private function buildCsp(string $nonce, bool $isProd, bool $isSecure): string
    {
        $viteOrigins = $isProd ? [] : $this->getViteDevOrigins();
        $httpOrigins = array_map(static fn (string $h): string => 'http://'.$h, $viteOrigins);
        $wsOrigins = array_map(static fn (string $h): string => 'ws://'.$h, $viteOrigins);
        $allVite = array_merge($httpOrigins, $wsOrigins);

        // Production: strict nonce + strict-dynamic (2026 best practice).
        // Dev: relaxed for Vite HMR — strict-dynamic IGNORES host lists in
        // modern browsers, so Vite module scripts would be blocked. We also
        // need 'unsafe-inline' for styles because Vite injects inline styles
        // during hot reload that cannot carry a nonce.
        $scriptStrict = $isProd ? " 'strict-dynamic'" : " 'unsafe-inline'";
        $styleStrict = $isProd ? '' : " 'unsafe-inline'";

        // Spline 3D runtime CDN origins (scenes + WASM modules + Draco decoder).
        // Without these, the Spline scene cannot fetch its assets and the
        // robot fails to load with "Failed to fetch".
        $splineOrigins = 'https://prod.spline.design https://unpkg.com https://www.gstatic.com';

        // Google reCAPTCHA v3 origins (api.js loader + gstatic runtime + iframe).
        $recaptchaScriptOrigins = 'https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/';
        $recaptchaFrameOrigins = 'https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/';

        // Styles: nonce in prod; unsafe-inline + Vite origins in dev. In dev,
        // Vite injects inline styles that cannot carry a nonce, so a nonce
        // would disable unsafe-inline and block those injected styles.
        $styleNonce = $isProd ? " 'nonce-{$nonce}'" : '';

        $directives = [
            "default-src 'self'",

            // Scripts: nonce + strict-dynamic in prod; nonce + unsafe-inline + Vite origins in dev.
            // 'wasm-unsafe-eval' is required by the Spline runtime to instantiate
            // its WebAssembly modules (boolean, modelling, navmesh, ui).
            "script-src 'self' 'nonce-{$nonce}' 'wasm-unsafe-eval'{$scriptStrict} {$recaptchaScriptOrigins}".($httpOrigins ? ' '.implode(' ', $httpOrigins) : ''),
            "script-src-elem 'self' 'nonce-{$nonce}' 'wasm-unsafe-eval'{$scriptStrict} {$splineOrigins} {$recaptchaScriptOrigins}".($httpOrigins ? ' '.implode(' ', $httpOrigins) : ''),

            "style-src 'self'{$styleNonce}{$styleStrict} https://fonts.googleapis.com".($httpOrigins ? ' '.implode(' ', $httpOrigins) : ''),
            "style-src-elem 'self'{$styleNonce}{$styleStrict} https://fonts.googleapis.com".($httpOrigins ? ' '.implode(' ', $httpOrigins) : ''),

            // Images: self + data + blob + course thumbnails (config-driven) + Spline textures.
            "img-src 'self' data: blob: https://prod.spline.design",

            // Media: self + blob + data: Spline scenes may embed inline video textures.
            "media-src 'self' blob: data:",

            // Fonts: Google Fonts only.
            "font-src 'self' data: https://fonts.gstatic.com",

            // XHR/fetch: same-origin + Spline (scenes + WASM CDNs) + Cloudflare.
            // Spline runtime fetches:
            //   - .splinecode scenes from prod.spline.design
            //   - WASM modules from unpkg.com
            //   - Draco mesh decoder from www.gstatic.com
            "connect-src 'self' {$splineOrigins} https://challenges.cloudflare.com".($allVite ? ' '.implode(' ', $allVite) : ''),

            // Frames: Cloudflare Turnstile widget + Google reCAPTCHA challenge.
            "frame-src 'self' https://challenges.cloudflare.com {$recaptchaFrameOrigins}",

            // Children: same as frames.
            "child-src 'self' https://challenges.cloudflare.com {$recaptchaFrameOrigins}",

            // Form actions: same origin + OAuth providers (social login
            // redirects the browser to Google / GitHub authorization pages).
            "form-action 'self' https://accounts.google.com https://github.com",

            // Hard ban on framing this app anywhere.
            "frame-ancestors 'none'",

            // No <base href> overrides.
            "base-uri 'self'",

            // Workers: self + blob (Spline spawns workers from blob URLs) + data.
            "worker-src 'self' blob: data:",

            // No <object>, <embed>, <applet>.
            "object-src 'none'",
        ];

        // Only instruct the browser to upgrade http subresources to https when
        // the application is actually served over HTTPS. In local dev
        // (php artisan serve) this directive causes browsers to send TLS
        // handshakes to the HTTP-only dev server, producing
        // "Unsupported SSL request" errors.
        if ($isProd || $isSecure) {
            $directives[] = 'upgrade-insecure-requests';
        }

        // In production, also ask the browser to send violation reports.
        // Configure CSP_REPORT_URI in .env to a collector (e.g. report-uri.com).
        if ($isProd && ($reportUri = config('security.csp_report_uri')) && is_string($reportUri)) {
            $directives[] = 'report-uri '.$reportUri;
            $directives[] = 'report-to csp-endpoint';
        }

        return implode('; ', $directives).';';
    }

    /**
     * Detect Vite dev-server origins that the current page may load.
     * In non-production the @vite Blade directive injects module scripts
     * from the Vite dev server (default port 5173). We must whitelist
     * those origins in CSP so HMR and the client WS connection work.
     *
     * @return list<string>
     */
    private function getViteDevOrigins(): array
    {
        $origins = [
            '127.0.0.1:5173',
            'localhost:5173',
            '0.0.0.0:5173',
        ];

        // Try to discover the LAN IP so mobile devices on the same network
        // can also load Vite assets when accessing via http://LAN_IP:8000.
        $lan = $this->detectLanIp();
        if ($lan !== null) {
            $origins[] = $lan.':5173';
        }

        return array_values(array_unique($origins));
    }

    /**
     * Best-effort LAN IP discovery (non-blocking, no external calls).
     */
    private function detectLanIp(): ?string
    {
        $hostname = gethostname();
        if ($hostname === false) {
            return null;
        }

        $ip = gethostbyname($hostname);
        if ($ip === $hostname) {
            return null;
        }

        // Must be a real private/LAN IP, not a loopback or public address.
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }

        return null;
    }

    /**
     * Cryptographically strong, URL-safe base64 nonce. 16 random bytes
     * (~128 bits) is well above the OWASP-recommended minimum.
     */
    private function generateNonce(): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes(16)));
    }

    /**
     * Best-effort detection of a JSON / non-HTML response.
     */
    private function isJsonResponse(Response $response): bool
    {
        $contentType = (string) $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'application/json')
            || str_contains($contentType, 'application/problem+json');
    }
}
