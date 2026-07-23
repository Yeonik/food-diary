<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step C of three, for `recipe_ingredients`: the constraint.
 *
 * Every row already has an owner by now, so this only makes that permanent:
 * NOT NULL, a foreign key to the account, and an index — every query on this
 * table is about to be filtered by it.
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
        $orphans = DB::table('recipe_ingredients')->whereNull('user_id')->count();

        if ($orphans > 0) {
            // Checked before the rebuild, not during it: a NOT NULL constraint
            // applied to a table that still has nulls fails somewhere inside the
            // copy, and this says which table and how many rows instead.
            throw new RuntimeException(
                "Cannot require an owner on recipe_ingredients: {$orphans} rows still have none. ".
                'The backfill migration should have run first; nothing has been changed.'
            );
        }

        Schema::table('recipe_ingredients', function (Blueprint $blueprint): void {
            $blueprint->unsignedBigInteger('user_id')->nullable(false)->change();
            $blueprint->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $blueprint->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('recipe_ingredients', function (Blueprint $blueprint): void {
            $blueprint->dropForeign(['user_id']);
            $blueprint->dropIndex(['user_id']);
            $blueprint->unsignedBigInteger('user_id')->nullable()->change();
        });
    }
};
