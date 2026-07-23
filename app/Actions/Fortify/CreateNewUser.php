<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
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
        ])->validate();

        return User::query()->create([
            'name' => $input['name'],
            'email' => $input['email'],
            // Handed over in plain and hashed by the model's `hashed` cast on
            // the way in, so the plaintext is never what gets stored. One
            // mechanism, used everywhere a password is set.
            'password' => $input['password'],
        ]);
    }
}
