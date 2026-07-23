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
 * A running instance also refuses passwords that appear in public breach
 * corpora, which is a far better filter than any composition rule. That check
 * asks api.pwnedpasswords.com over the network — by k-anonymity, so only the
 * first five characters of the hash are ever sent, never the password — and the
 * test environment is the one place it is left off, because CI here makes no
 * network calls at all and `Http::preventStrayRequests()` would refuse it.
 *
 * Leaving it off in tests costs nothing at runtime: the rule already fails open.
 * If the API cannot be reached, Laravel reports the exception and treats the
 * password as unseen, so a breach-list outage never locks anybody out of setting
 * a password.
 */
trait PasswordRules
{
    /**
     * @return array<int, mixed>
     */
    protected function passwordRules(): array
    {
        $password = Password::min(8);

        if (! app()->environment('testing')) {
            $password = $password->uncompromised();
        }

        return ['required', 'string', $password, 'confirmed'];
    }
}
