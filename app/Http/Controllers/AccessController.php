<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The single optional access password. This is not user accounts — it is one
 * shared secret that unlocks the instance for the session.
 */
class AccessController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (! $this->isLocked()) {
            return redirect()->route('diary.index');
        }

        return view('access.unlock');
    }

    public function unlock(Request $request): RedirectResponse
    {
        $password = config('nutrition.access_password');
        $given = (string) $request->input('password', '');

        if (is_string($password) && $password !== '' && hash_equals($password, $given)) {
            $request->session()->put('unlocked', true);

            return redirect()->intended(route('diary.index'));
        }

        return back()->withErrors(['password' => 'That password does not match.']);
    }

    private function isLocked(): bool
    {
        $password = config('nutrition.access_password');

        return is_string($password) && $password !== '';
    }
}
