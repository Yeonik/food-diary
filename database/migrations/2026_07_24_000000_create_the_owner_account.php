<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\OwnerAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\App;

/**
 * The deploy that removes the shared password gate is also the deploy on which
 * nobody can get in: registration will need an invite, and invites need an owner
 * to issue them. This creates that owner from OWNER_EMAIL / OWNER_PASSWORD.
 *
 * It is a migration and not a command because the next migration attributes
 * every existing record to this account, and it has to exist by then.
 *
 * If OWNER_EMAIL is not set this throws before writing anything: the migration
 * fails, the deploy fails, and the platform keeps the previous release serving
 * an untouched database. That is the intended outcome, not a rough edge.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tests build whichever accounts they need; an account seeded into every
        // test database would be a stowaway in all of them, and the behaviour
        // this migration delegates is covered directly in OwnerAccountTest.
        if (App::environment('testing')) {
            return;
        }

        OwnerAccount::ensure();
    }

    public function down(): void
    {
        $email = config('nutrition.owner.email');

        // Only the account this migration would have made, and only while it is
        // still empty-handed. Once anything belongs to the owner, removing them
        // is a data decision and not a schema one.
        if (is_string($email) && trim($email) !== '') {
            User::query()->where('email', mb_strtolower(trim($email)))->delete();
        }
    }
};
