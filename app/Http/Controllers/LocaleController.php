<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

/**
 * Sets the interface language. The choice is stored in a long-lived cookie so it
 * survives a browser restart, then the redirect back re-renders in the new
 * language — the switch applies at once.
 */
class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', Rule::in(SetLocale::SUPPORTED)],
        ]);

        return redirect()
            ->back()
            ->withCookie(Cookie::forever(SetLocale::COOKIE, $validated['locale']));
    }
}
