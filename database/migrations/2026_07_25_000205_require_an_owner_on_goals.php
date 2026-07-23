<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step C of three, for `goals`: the constraint.
 *
 * The goal was already a singleton in practice; here it becomes one per
 * account, enforced by the database rather than by how the query is written.
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
        $orphans = DB::table('goals')->whereNull('user_id')->count();

        if ($orphans > 0) {
            // Checked before the rebuild, not during it: a NOT NULL constraint
            // applied to a table that still has nulls fails somewhere inside the
            // copy, and this says which table and how many rows instead.
            throw new RuntimeException(
                "Cannot require an owner on goals: {$orphans} rows still have none. ".
                'The backfill migration should have run first; nothing has been changed.'
            );
        }

        Schema::table('goals', function (Blueprint $blueprint): void {
            $blueprint->unsignedBigInteger('user_id')->nullable(false)->change();
            $blueprint->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $blueprint->index('user_id');
        });

        // The app has always read a goal back with latest('id') and written one
        // row; the backfill removed any leftovers, so this makes the intent
        // explicit — a person has one goal or none.
        Schema::table('goals', function (Blueprint $blueprint): void {
            $blueprint->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $blueprint): void {
            $blueprint->dropUnique(['user_id']);
        });

        Schema::table('goals', function (Blueprint $blueprint): void {
            $blueprint->dropForeign(['user_id']);
            $blueprint->dropIndex(['user_id']);
            $blueprint->unsignedBigInteger('user_id')->nullable()->change();
        });
    }
};
