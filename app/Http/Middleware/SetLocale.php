<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Visitor pages and their APIs speak the player's language: the saved choice from the library's
 * switcher, otherwise the browser's preference. The admin area never uses this middleware.
 */
class SetLocale
{
    public const LOCALES = ['en', 'fr', 'ar'];

    public const COOKIE = 'glisa_locale';

    public function handle(Request $request, Closure $next)
    {
        $saved = $request->cookie(self::COOKIE);
        App::setLocale(in_array($saved, self::LOCALES, true) ? $saved : ($request->getPreferredLanguage(self::LOCALES) ?? 'en'));

        return $next($request);
    }
}
