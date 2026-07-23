<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use Illuminate\Validation\Rules\Password;

/**
 * One definition of what counts as an acceptable password, shared by the two
 * places that accept one: a minimum length and a confirmation, with no
 * composition rules. Forcing a symbol and a digit produces "Password1!" and
 * nothing safer.
 *
 * Deliberately NOT `->uncompromised()`. That rule is a good one, but it asks
 * haveibeenpwned.com over the network on every validation — including in the
 * test suite, and this project's first rule for CI is that it makes no network
 * calls and needs no key. A check that a stranger's outage can fail is not a
 * check this suite can own.
 */
trait PasswordRules
{
    /**
     * @return array<int, mixed>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::min(8), 'confirmed'];
    }
}
