<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step C of three, for `weight_entries`: the constraint.
 *
 * This table also carries the change that is not merely bookkeeping: one
 * reading per day becomes one reading per day PER PERSON.
 *
 * Deliberately NOT wrapped in a transaction. Making a column non-nullable on
 * SQLite is not an edit, it is a rebuild — create a copy of the table, move the
 * rows, drop the original, rename — and the rebuild toggles
 * `PRAGMA foreign_keys`, which SQLite silently ignores while a transaction is
 * open. Laravel does not wrap SQLite migrations either (only the Postgres and
 * SQL Server grammars declare schema transactions), so this file inherits the
 * right behaviour rather than fighting it.
 *
 * One file per table, so a failure names the table it failed on and the tables
 * already done stay recorded and are not attempted again.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('weight_entries')->whereNull('user_id')->count();

        if ($orphans > 0) {
            // Checked before the rebuild, not during it: a NOT NULL constraint
            // applied to a table that still has nulls fails somewhere inside the
            // copy, and this says which table and how many rows instead.
            throw new RuntimeException(
                "Cannot require an owner on weight_entries: {$orphans} rows still have none. ".
                'The backfill migration should have run first; nothing has been changed.'
            );
        }

        Schema::table('weight_entries', function (Blueprint $blueprint): void {
            $blueprint->unsignedBigInteger('user_id')->nullable(false)->change();
            $blueprint->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $blueprint->index('user_id');
        });

        // A reading was unique by date alone, which is correct for one person and
        // wrong the moment there are two: the second person could not record a
        // weight for a day the first had already used.
        Schema::table('weight_entries', function (Blueprint $blueprint): void {
            $blueprint->dropUnique(['recorded_on']);
            $blueprint->unique(['user_id', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::table('weight_entries', function (Blueprint $blueprint): void {
            $blueprint->dropUnique(['user_id', 'recorded_on']);
            $blueprint->unique('recorded_on');
        });

        Schema::table('weight_entries', function (Blueprint $blueprint): void {
            $blueprint->dropForeign(['user_id']);
            $blueprint->dropIndex(['user_id']);
            $blueprint->unsignedBigInteger('user_id')->nullable()->change();
        });
    }
};
