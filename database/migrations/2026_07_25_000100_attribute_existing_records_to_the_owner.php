<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Step B of three: data only, no schema.
 *
 * Everything logged before accounts existed belongs to the owner — the account
 * the previous migration created. This is the only step that moves data, and it
 * is the only one wrapped in an explicit transaction.
 *
 * The transaction is safe here precisely because nothing in this file touches
 * the schema. Step C rebuilds tables, and a rebuild toggles
 * `PRAGMA foreign_keys`, which SQLite ignores inside a transaction — so the
 * step that could most use atomicity is the one that must not have it. This step
 * can, and does: either every row gets an owner or none does.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'food_items',
        'food_item_aliases',
        'recipe_ingredients',
        'meal_entries',
        'weight_entries',
        'goals',
    ];

    public function up(): void
    {
        $orphans = $this->rowsWithoutAnOwner();

        if ($orphans === 0) {
            // A fresh installation, or a test database: nothing was logged before
            // accounts existed, so there is nothing to attribute.
            return;
        }

        // Read through the query builder rather than the model. A migration is a
        // historical record and has to keep working while the models around it
        // change — and the very next commit puts a global scope on six of them.
        $owner = DB::table('users')->orderBy('id')->first();

        if ($owner === null) {
            throw new RuntimeException(
                "There are {$orphans} records from before accounts existed and no account to attribute them to. ".
                'The owner is created by an earlier migration; nothing has been changed.'
            );
        }

        DB::transaction(function () use ($owner): void {
            // A goal was always a single row read back with latest('id'), so any
            // others are leftovers. They have to go before user_id can be unique
            // per user, and the one kept is the one the app was already reading.
            $keep = DB::table('goals')->max('id');

            if ($keep !== null) {
                DB::table('goals')->where('id', '!=', $keep)->delete();
            }

            foreach (self::TABLES as $table) {
                DB::table($table)->whereNull('user_id')->update(['user_id' => $owner->id]);
            }
        });
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->update(['user_id' => null]);
        }
    }

    private function rowsWithoutAnOwner(): int
    {
        $total = 0;

        foreach (self::TABLES as $table) {
            $total += DB::table($table)->whereNull('user_id')->count();
        }

        return $total;
    }
};
