<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebAuthn;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Serve the Laragear WebAuthn frontend helper script.
 *
 * This route is referenced as `webauthn.js` by the passkey login and
 * registration Blade components. The file is bundled with the
 * laragear/webauthn package and is served here so the application does
 * not depend on an external CDN and so the asset falls under the
 * application's own CSP `script-src 'self'` directive.
 */
class WebAuthnAssetController
{
    /**
     * Package-relative path to the bundled frontend helper.
     */
    private const ASSET_PATH = 'vendor/laragear/webauthn/resources/js/webauthn.js';

    public function __invoke(): Response
    {
        $path = base_path(self::ASSET_PATH);

        if (! File::isFile($path)) {
            abort(404, 'WebAuthn helper script not found.');
        }

        $contents = File::get($path);

        return response($contents, SymfonyResponse::HTTP_OK, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'public, max-age=86400, immutable',
            'Vary' => 'Accept-Encoding',
        ]);
    }
}
