<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the interface language for every web request. Priority: the user's
 * saved choice (a cookie, so it outlives the browser session — this is a real
 * setting, not a per-session whim), then the browser's Accept-Language on a
 * first visit, then the configured default. The app is single-user, so the
 * choice lives in a cookie rather than against an account.
 */
class SetLocale
{
    /** The languages the interface ships in. */
    public const SUPPORTED = ['en', 'ru'];

    /** The cookie the saved choice lives in. */
    public const COOKIE = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        App::setLocale($locale);
        // Carbon drives the localised dates the views render.
        Carbon::setLocale($locale);

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $saved = $request->cookie(self::COOKIE);
        if (is_string($saved) && in_array($saved, self::SUPPORTED, true)) {
            return $saved;
        }

        // Accept-Language on the first visit; getPreferredLanguage returns the
        // first supported language when the header asks for none of them, which
        // is the configured default (SUPPORTED[0]).
        $preferred = $request->getPreferredLanguage(self::SUPPORTED);
        if (is_string($preferred) && in_array($preferred, self::SUPPORTED, true)) {
            return $preferred;
        }

        $default = config('app.locale');

        return is_string($default) ? $default : 'en';
    }
}
