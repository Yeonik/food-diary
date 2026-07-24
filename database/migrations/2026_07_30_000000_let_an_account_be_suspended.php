<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suspension: an account kept, with its way in closed.
 *
 * One nullable column and nothing else. Null is active, a timestamp is
 * suspended — the state and the moment it began in the same place, so the owner
 * can see when rather than only whether. There is no `is_suspended` boolean
 * beside it to disagree with.
 *
 * Unlike the constraint work in v0.3.0, this is additive: adding a nullable
 * column with no default writes the table header and leaves every row where it
 * is, so SQLite does not rebuild the table. Nothing is copied, no foreign key is
 * re-declared, and there is no position for `insert select` to get wrong. That
 * is why this one does not need the full rehearsal against a copy of the live
 * database that the rebuilds did — though the deploy still takes its backup
 * first, because that is not a favour the migration earns, it is the rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $blueprint): void {
            // Deliberately not indexed. It is read one account at a time, on a
            // row already found by id or by address, and written by hand a few
            // times in the life of an instance.
            $blueprint->timestamp('suspended_at')->nullable()->after('is_owner');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('suspended_at');
        });
    }
};
