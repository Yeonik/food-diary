<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * Changing your own password. The current one is required — otherwise a session
 * left open on a borrowed phone is enough to take the account.
 */
class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordRules;

    /**
     * @param  array<string, string>  $input
     */
    public function update(Authenticatable $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('auth.current_password_wrong'),
        ])->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => $input['password'],
        ])->save();
    }
}
