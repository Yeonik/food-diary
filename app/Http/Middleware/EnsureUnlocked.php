<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the app behind the single optional access password. When no password is
 * configured the gate is open — this is a self-hosted, single-user tool and the
 * password is opt-in.
 */
class EnsureUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $password = config('nutrition.access_password');

        // The unlock screen itself must stay reachable while locked, otherwise
        // there is no way in.
        if ($request->routeIs('unlock', 'unlock.show')) {
            return $next($request);
        }

        // No password configured, or already unlocked this session: let it pass.
        if (! is_string($password) || $password === '' || $request->session()->get('unlocked') === true) {
            return $next($request);
        }

        return redirect()->route('unlock.show');
    }
}
