<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * The one path that creates a user.
 *
 * Fortify's registration route hands everything here, which is why the invite
 * check will live in this class rather than in a route guard: there is no second
 * way to reach the creation of an account that could miss it.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordRules;

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            // There is no registration without one. The rule below only checks
            // that something was typed; whether it is worth anything is settled
            // by spending it, which is a single atomic write rather than a
            // question asked and answered separately.
            'invite_code' => ['required', 'string', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::query()->create([
                'name' => $input['name'],
                'email' => $input['email'],
                // Handed over in plain and hashed by the model's `hashed` cast
                // on the way in, so the plaintext is never what gets stored. One
                // mechanism, used everywhere a password is set.
                'password' => $input['password'],
            ]);

            if (! Invite::spend((string) $input['invite_code'], $user)) {
                // Inside the transaction, so the account just created goes with
                // it. A refused registration leaves nothing behind — not a row,
                // not an address that is now taken.
                throw ValidationException::withMessages([
                    'invite_code' => __('auth.invite_invalid'),
                ]);
            }

            return $user;
        });
    }
}
