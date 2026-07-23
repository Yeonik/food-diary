<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\AccountErasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Leaving.
 *
 * The password is asked for because this cannot be undone and an unattended
 * screen should not be enough to do it — not because leaving is discouraged.
 * There is no "are you sure you want to lose your progress", no offer to
 * deactivate instead, and nothing kept back for later: the diary is the
 * person's, and so is the decision.
 */
class AccountController extends Controller
{
    public function destroy(Request $request, AccountErasure $erasure): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->isOwner()) {
            // The owner administers the installation and there is no way to
            // appoint another from inside the application. An owner who could
            // delete themselves would leave an instance nobody can issue an
            // invitation from — say so plainly rather than half-doing it.
            return back()->withErrors(['delete_account' => __('account.owner_cannot_leave')]);
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ], [], ['current_password' => __('auth.current_password')]);

        // Anything half-finished goes too: a photo waiting on the confirm screen
        // is on disk, and nobody is coming back for it.
        $this->discardAnythingPending($request);

        $erasure->erase($user);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('account.deleted'));
    }

    private function discardAnythingPending(Request $request): void
    {
        $pending = $request->session()->get(PendingLogController::SESSION_KEY);

        if (is_array($pending)) {
            $photo = $pending['photo'] ?? null;

            if (is_string($photo) && is_file($photo)) {
                @unlink($photo);
            }
        }

        $request->session()->forget(PendingLogController::SESSION_KEY);
    }
}
