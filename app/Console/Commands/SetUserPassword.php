<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Sets a password for an account, from a shell on the machine.
 *
 * This is how a forgotten password is dealt with, because password reset by
 * email is not built: this instance has no deliverable mail, and a reset screen
 * that sends a link into a log file would be a promise the app cannot keep. The
 * README says so plainly, and this command is what it says instead.
 *
 *   php artisan diary:set-password someone@example.com
 *
 * The password is asked for rather than passed as an argument, so it does not
 * end up in the shell history or in the process list.
 */
class SetUserPassword extends Command
{
    protected $signature = 'diary:set-password {email : The account to set a password for}';

    protected $description = 'Set an account password from the console (there is no reset by email).';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            // No secrecy needed here: whoever has a shell on the machine can read
            // the table anyway. The point of not leaking existence is the public
            // sign-in screen, not this.
            $this->error("No account with the address {$email}.");

            return self::FAILURE;
        }

        $password = (string) $this->secret('New password');
        $again = (string) $this->secret('Repeat it');

        // The breach check is deliberately left out of this path: it needs the
        // network, and the one time somebody runs this is likely the time the
        // machine is having a bad day. The length floor still applies.
        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $again],
            ['password' => ['required', 'string', Password::min(8), 'confirmed']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        // Hashed by the model's cast; the plaintext is never stored and is never
        // echoed back, not even as a confirmation.
        $user->forceFill(['password' => $password])->save();

        $this->info("Password set for {$email}.");

        return self::SUCCESS;
    }
}
