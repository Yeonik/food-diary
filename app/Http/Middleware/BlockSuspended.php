<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
 * **The column is read from the database, not from the account object the guard
 * hands over.** That object can be a copy resolved earlier and held in memory,
 * and a wall that consults a stale copy is not a wall — it was observed letting
 * an established session straight through. Whether it goes stale depends on how
 * long a process lives, which is a property of the server this happens to run
 * under and not a promise anybody made. So the state is fetched by primary key
 * on each request: one lookup, against a boundary that is not allowed to be
 * wrong. Doubt means no access.
 *
 * Reading it fresh every time is also what makes suspension immediate in both
 * directions — it takes effect on the suspended person's next request, and
 * lifting it lets them back in without signing in again. There is no copy of the
 * state anywhere to go stale, which is why suspension does not destroy the
 * session either: the wall is the answer, and it is asked again every time.
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
        if ($user === null) {
            return $next($request);
        }

        // Straight from the table, by primary key — see the note above on why
        // the account object is not trusted for this one question.
        $suspendedAt = DB::table('users')
            ->where('id', $user->getAuthIdentifier())
            ->value('suspended_at');

        if ($suspendedAt === null) {
            return $next($request);
        }

        if ($request->routeIs(...self::STILL_REACHABLE)) {
            return $next($request);
        }

        // Rendered in place rather than redirected to a screen of its own: there
        // is no URL to arrive at and be bounced from, so no loop to get wrong,
        // and the address bar still shows what was asked for.
        return response()->view('suspended', [
            // The value just read, not the one on the account object, for the
            // same reason the decision above used it.
            'since' => is_string($suspendedAt) ? CarbonImmutable::parse($suspendedAt) : $suspendedAt,
        ], Response::HTTP_FORBIDDEN);
    }
}
