<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Who the owner is, written down rather than inferred.
 *
 * "The first account" would have worked today and been wrong the first time a
 * database was restored, reseeded or had its rows renumbered — an implicit rule
 * that nobody can see in the schema and that quietly promotes whoever happens to
 * sit at the top. The flag says it instead, and the authorisation gate reads
 * the flag.
 *
 * **Dated ahead of the migration that creates the owner, deliberately.** That
 * one calls the same code the rest of the application uses to bring the account
 * into being, and that code sets this flag — so the column has to exist by the
 * time it runs. The ordering is the reason this is its own file and the first
 * of them.
 *
 * The backfill below is for a database that already holds accounts when this
 * runs. Not the expected case — the release that introduces accounts is this
 * one — but a column added to a populated table with nobody marked would leave
 * an installation with no owner and no way to appoint one.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Everything that could refuse, before the column exists. A migration
        // that adds a column and then throws leaves the column behind, and the
        // retry fails on the column rather than on the reason.
        $owner = $this->theConfiguredOwner();

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_owner')->default(false);
        });

        if ($owner !== null) {
            DB::table('users')->where('id', $owner)->update(['is_owner' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_owner');
        });
    }

    /**
     * The id of the account named by OWNER_EMAIL, or null when there is nobody
     * to mark yet.
     */
    private function theConfiguredOwner(): ?int
    {
        if (DB::table('users')->count() === 0) {
            // The ordinary case: no accounts exist yet, and the owner is created
            // a moment later with the flag already set.
            return null;
        }

        $email = config('nutrition.owner.email');

        if (! is_string($email) || trim($email) === '') {
            throw new RuntimeException(
                'Cannot mark the owner account: OWNER_EMAIL is not set, and there are accounts '.
                'that would be left with no owner among them. Nothing has been changed.'
            );
        }

        // Lowercased to match how the account was stored.
        $id = DB::table('users')->where('email', Str::lower(trim($email)))->value('id');

        if ($id === null) {
            throw new RuntimeException(
                'Cannot mark the owner account: no account matches the configured OWNER_EMAIL. '.
                'Nothing has been changed.'
            );
        }

        return (int) $id;
    }
};
