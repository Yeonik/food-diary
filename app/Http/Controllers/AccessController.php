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
        $configured = config('nutrition.access_password');
        $given = (string) $request->input('password', '');

        if (is_string($configured) && $configured !== '' && $this->matches($configured, $given)) {
            $request->session()->put('unlocked', true);

            return redirect()->intended(route('diary.index'));
        }

        // The same answer for a wrong password and an unconfigured gate: the
        // screen never distinguishes them, and never says which part was wrong.
        return back()->withErrors(['password' => __('access.wrong_password')]);
    }

    /**
     * A bcrypt hash (the recommended form, never a plaintext secret at rest) is
     * verified with password_verify; a plain value is compared in constant time.
     */
    private function matches(string $configured, string $given): bool
    {
        if (preg_match('/^\$2[aby]\$/', $configured) === 1) {
            return password_verify($given, $configured);
        }

        return hash_equals($configured, $given);
    }

    private function isLocked(): bool
    {
        $password = config('nutrition.access_password');

        return is_string($password) && $password !== '';
    }
}
