<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Security\UrlHelper;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    public function switch(string $locale): RedirectResponse
    {
        if (in_array($locale, ['en', 'ku'], true)) {
            session(['locale' => $locale]);
            cookie()->queue(cookie()->forever('locale', $locale));
        }

        $back = url()->previous();
        $safe = UrlHelper::safeRedirect($back, '/');

        return redirect($safe);
    }
}
