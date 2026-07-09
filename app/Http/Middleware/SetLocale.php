<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale');

        if (! in_array($locale, ['en', 'ku'], true)) {
            $locale = $request->cookie('locale');
        }

        if (! in_array($locale, ['en', 'ku'], true)) {
            $locale = config('app.locale');
        }

        // Optional query-string override so tests and direct links can request
        // /?lang=en or /?lang=ku without changing session state.
        if ($request->query('lang') !== null && in_array($request->query('lang'), ['en', 'ku'], true)) {
            $locale = $request->query('lang');
        }

        if (is_string($locale) && $locale !== '') {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
