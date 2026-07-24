<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AccountErasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The accounts on this instance, for the person who administers it.
 *
 * **This is the one screen that reads across everybody deliberately.** Every
 * domain model carries a global scope so that no query can see another person's
 * records; `User` carries none, because an account is not a record belonging to
 * an account. So the roster is a plain query, and what keeps it honest is the
 * gate on the route rather than a scope on the model.
 *
 * What it shows is deliberately thin: a name, an address, whether the account is
 * suspended, and when it joined. Not what anybody has been eating, not how much,
 * not when they last signed in. Administering who may use the instance does not
 * require reading the diaries on it, and this screen is built so that it cannot
 * start to.
 */
class UserAdminController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            // In the order they arrived. The owner is first because the owner
            // was first, not because the list sorts them there.
            'accounts' => User::query()->orderBy('id')->get(),
        ]);
    }

    public function suspend(Request $request, User $account): RedirectResponse
    {
        if ($refusal = $this->refuseIfItIsThemselves($request, $account)) {
            return $refusal;
        }

        // Already suspended is not an error and not a second suspension: the
        // moment it began is the one thing this column is good for, and
        // overwriting it with today would lose it.
        if (! $account->isSuspended()) {
            $account->forceFill(['suspended_at' => now()])->save();
        }

        return redirect()->route('users.index')
            ->with('status', __('users.suspended_flash', ['name' => $account->name]));
    }

    public function restore(Request $request, User $account): RedirectResponse
    {
        if ($refusal = $this->refuseIfItIsThemselves($request, $account)) {
            return $refusal;
        }

        $account->forceFill(['suspended_at' => null])->save();

        return redirect()->route('users.index')
            ->with('status', __('users.restored_flash', ['name' => $account->name]));
    }

    /**
     * The screen that stands between the button and the deletion.
     *
     * It exists because the action is irreversible and aimed at somebody else:
     * one press on a list of similar-looking rows is the wrong amount of
     * deliberation for destroying another person's diary. So the account is
     * named, what would go is counted, and the address has to be typed.
     */
    public function confirmDelete(Request $request, User $account, AccountErasure $erasure): View|RedirectResponse
    {
        if ($refusal = $this->refuseIfItIsThemselves($request, $account)) {
            return $refusal;
        }

        return view('users.delete', [
            'account' => $account,
            // Counted from the same list that does the deleting.
            'tally' => array_filter($erasure->tally($account)),
        ]);
    }

    public function destroy(Request $request, User $account, AccountErasure $erasure): RedirectResponse
    {
        if ($refusal = $this->refuseIfItIsThemselves($request, $account)) {
            return $refusal;
        }

        // Not the owner's password: that would only prove the owner is present,
        // which the session already says. Typing the address proves they mean
        // this account rather than the row above it.
        $typed = mb_strtolower(trim((string) $request->input('email', '')));

        if (! hash_equals(mb_strtolower($account->email), $typed)) {
            return back()->withErrors(['email' => __('users.delete_email_mismatch')]);
        }

        $name = $account->name;

        $erasure->erase($account);

        return redirect()->route('users.index')
            ->with('status', __('users.deleted_flash', ['name' => $name]));
    }

    /**
     * The owner may not do this to their own account.
     *
     * An owner who suspended themselves would leave an instance with nobody able
     * to lift it — the same shape of dead end as an owner deleting themselves,
     * which the account screen already refuses. It is written as "yourself"
     * rather than "the owner" because that is the mistake being prevented; with
     * one owner per instance the two conditions name the same account, and if
     * that ever changes, one owner locking another out is a different question
     * that should be decided rather than inherited from this line.
     */
    private function refuseIfItIsThemselves(Request $request, User $account): ?RedirectResponse
    {
        if (! $account->is($request->user())) {
            return null;
        }

        return back()->withErrors(['account' => __('users.cannot_act_on_yourself')]);
    }
}
