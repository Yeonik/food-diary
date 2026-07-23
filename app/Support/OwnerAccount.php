<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Brings the owner's account into being from configuration.
 *
 * The shared password gate is gone, so on the deploy that removes it there is no
 * way into the application at all — registration needs an invite and invites
 * need somebody to issue them. That somebody is created here, from OWNER_EMAIL
 * and OWNER_PASSWORD, which the owner sets on the platform before the deploy and
 * removes after the first sign-in.
 *
 * Two properties matter more than the code:
 *
 * 1. **It refuses before it writes.** With no address configured this throws
 *    having touched nothing, so the migration fails, the deploy fails, the
 *    previous release keeps serving and the database is exactly as it was.
 * 2. **It never rewrites an existing account.** Found by address, it is returned
 *    untouched — a re-run cannot reset a password the owner has since changed
 *    back to whatever is still sitting in the platform's variables.
 */
final class OwnerAccount
{
    /**
     * @throws RuntimeException when the configuration cannot produce an owner
     */
    public static function ensure(): User
    {
        // Lowercased on the way in, because Fortify lowercases what is typed at
        // sign-in: an address stored with a capital could never be matched.
        $email = Str::lower(self::required('email', 'OWNER_EMAIL'));

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            return $existing;
        }

        $name = config('nutrition.owner.name');

        return User::query()->create([
            // Not a translated default: a stored name does not follow the
            // interface language, and the owner can change it later anyway.
            'name' => is_string($name) && trim($name) !== ''
                ? trim($name)
                : Str::before($email, '@'),
            'email' => $email,
            // Plain here, hashed by the model's cast on the way in. It is read
            // from configuration and handed straight to the model: it is never
            // interpolated into a message, an exception or a log line.
            'password' => self::required('password', 'OWNER_PASSWORD'),
        ]);
    }

    /**
     * Reads one required piece of owner configuration, naming the variable that
     * is missing — and only ever the variable's name, never its value.
     */
    private static function required(string $key, string $variable): string
    {
        $value = config('nutrition.owner.'.$key);

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException(
                "Cannot create the owner account: {$variable} is not set. ".
                'Set it on the platform and deploy again; nothing has been changed.'
            );
        }

        return trim($value);
    }
}
