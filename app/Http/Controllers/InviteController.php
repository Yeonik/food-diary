<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Nutrition\RecognitionQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The owner's invitations: a list and a button.
 *
 * Not an administration console — there are no accounts to manage here, no
 * roles, no settings. Somebody is invited, or an invitation that should not have
 * gone out is withdrawn. Everything that makes this safe is elsewhere: the
 * authorisation gate on the routes, and the conditional writes on the model.
 */
class InviteController extends Controller
{
    public function index(RecognitionQuota $quota): View
    {
        return view('invites.index', [
            'invites' => Invite::query()->with(['creator', 'redeemer'])->latest('id')->get(),
            // One number: how hard the shared key is being worked today. Not a
            // dashboard, and deliberately not per person — the owner pays for
            // the key and can see the load on it, which is a different thing
            // from being able to watch what anybody is eating.
            'recognisedToday' => $quota->everybodysToday(),
            'dailyLimit' => $quota->limit(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $code = Invite::issue($request->user());

        // Flashed, not stored. This is the only moment the code exists in a
        // readable form, and it survives exactly one redirect — long enough to
        // be shown once and copied. Refreshing the list afterwards will not
        // bring it back, because nothing anywhere still knows it.
        return redirect()->route('invites.index')->with('issued_code', $code);
    }

    public function destroy(Invite $invite): RedirectResponse
    {
        if (! $invite->revoke()) {
            // Already spent, or already withdrawn. Both are the same answer
            // here: there is nothing left to withdraw.
            return back()->withErrors(['revoke' => __('invites.not_revocable')]);
        }

        return redirect()->route('invites.index')->with('status', __('invites.revoked'));
    }
}
