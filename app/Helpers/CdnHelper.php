<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * CDN URL helper for production asset delivery.
 * Falls back to local asset() when CDN is not configured.
 */
class CdnHelper
{
    public static function asset(string $path): string
    {
        $cdn = config('app.cdn_url');
        if (is_string($cdn) && $cdn !== '' && app()->environment('production')) {
            return rtrim($cdn, '/').'/'.ltrim($path, '/');
        }

        return asset($path);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function image(string $path, array $options = []): string
    {
        $url = self::asset($path);
        $imageCdn = config('app.image_cdn_url');

        if (is_string($imageCdn) && $imageCdn !== '') {
            $params = http_build_query($options);

            return $imageCdn.'/cdn-cgi/image/'.($params ? '?'.$params.'&' : '?').'url='.urlencode($url);
        }

        return $url;
    }
}
