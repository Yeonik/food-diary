<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step A of three: additive only.
 *
 * `user_id` arrives nullable and without a foreign key, which on SQLite is a
 * plain ALTER TABLE ADD COLUMN — a write to the table header, no rows touched,
 * no rebuild. It is the step that cannot really fail, and it is deliberately
 * first: the code running in production right now does not know these columns
 * exist and carries on working, so a deploy that dies after this point leaves a
 * serving application behind it.
 *
 * The constraints come in step C, one file per table, after step B has given
 * every row an owner.
 *
 * The two child tables (aliases, recipe ingredients) get their own column rather
 * than reaching through their parent. The owner has to be readable in a single
 * table for a global scope to constrain on it without a join, and a scope that
 * needs a join is a scope somebody will eventually bypass.
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
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedBigInteger('user_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('user_id');
            });
        }
    }
};
