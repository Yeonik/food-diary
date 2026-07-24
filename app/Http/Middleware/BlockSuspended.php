<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspended account gets in, and then gets told.
 *
 * **Why the refusal is not at the sign-in screen.** Turning away the credentials
 * would mean answering one of two ways, and both are wrong. The generic "these
 * details do not match" is a lie — the password was right. A specific "this
 * account is suspended" is worse: it makes the sign-in form an oracle, because
 * anybody typing an address learns whether it is real. So the credential
 * boundary stays uniform, and the honest explanation is given on the other side
 * of it, where the person has already proved who they are and there is nothing
 * left to disclose.
 *
 * **Why a whitelist and not a blacklist.** Everything is walled except the few
 * things somebody needs in order to read the notice and leave. A route added to
 * this application next year is closed to a suspended account because of that
 * default, not because whoever added it remembered this file.
 *
 * The column is read on every request rather than copied into the session, so
 * suspending somebody who is signed in takes effect on their next request, and
 * lifting it lets them straight back in without signing in again. There is no
 * stored copy of the state to go stale, which is also why suspension does not
 * destroy the session: the wall is the answer, and it is asked fresh every time.
 */
class BlockSuspended
{
    /**
     * Reachable while suspended.
     *
     * Signing out, and changing the language of the notice you are reading.
     * Deliberately short: this is the list somebody has to argue their way onto.
     *
     * @var list<string>
     */
    private const STILL_REACHABLE = [
        'logout',
        'locale.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Nobody signed in: not this middleware's business. The `auth`
        // middleware decides what happens to a guest.
        if ($user === null || ! $user->isSuspended()) {
            return $next($request);
        }

        if ($request->routeIs(...self::STILL_REACHABLE)) {
            return $next($request);
        }

        // Rendered in place rather than redirected to a screen of its own: there
        // is no URL to arrive at and be bounced from, so no loop to get wrong,
        // and the address bar still shows what was asked for.
        return response()->view('suspended', [
            'since' => $user->suspended_at,
        ], Response::HTTP_FORBIDDEN);
    }
}
