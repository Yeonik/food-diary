<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A recipe line may only name items belonging to the person who owns the line.
 *
 * Both keys grow a second column. `(recipe_id, user_id)` and
 * `(ingredient_id, user_id)` reference `food_items(id, user_id)`, so a row
 * pointing at somebody else's food is refused by the database rather than by the
 * validation rule that happens to be in front of it — the rule stays, this is
 * what holds when a query goes round it.
 *
 * It also settles the invariant the tests used to assert about the application:
 * a line cannot claim an owner different from the recipe it belongs to, because
 * the same `user_id` has to satisfy both keys at once.
 *
 * The delete behaviour is unchanged, and can be: `CASCADE` removes the whole
 * row and `RESTRICT` removes nothing, so neither has to null a column, and
 * `user_id` stays NOT NULL under both.
 *
 * A rebuild, so not in a transaction — see the step C migrations for why.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->refuseToStartOnCrossOwnerRows();

        Schema::table('recipe_ingredients', function (Blueprint $blueprint): void {
            $blueprint->dropForeign(['recipe_id']);
            $blueprint->dropForeign(['ingredient_id']);

            $blueprint->foreign(['recipe_id', 'user_id'])
                ->references(['id', 'user_id'])->on('food_items')
                ->cascadeOnDelete();

            // An item a recipe is built on still cannot be deleted out from
            // under it. The library says so in words before it comes to this.
            $blueprint->foreign(['ingredient_id', 'user_id'])
                ->references(['id', 'user_id'])->on('food_items')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recipe_ingredients', function (Blueprint $blueprint): void {
            $blueprint->dropForeign(['recipe_id', 'user_id']);
            $blueprint->dropForeign(['ingredient_id', 'user_id']);

            $blueprint->foreign('recipe_id')->references('id')->on('food_items')->cascadeOnDelete();
            $blueprint->foreign('ingredient_id')->references('id')->on('food_items')->restrictOnDelete();
        });
    }

    /**
     * Rows that the new keys would reject, counted before anything is touched.
     *
     * A rebuild copies the rows into a new table and only then finds out that
     * one of them cannot satisfy the constraint, which fails deep inside the
     * copy with nothing useful to say. This says which column and how many rows,
     * and it says it while the table is still the table it was.
     */
    private function refuseToStartOnCrossOwnerRows(): void
    {
        foreach (['recipe_id', 'ingredient_id'] as $column) {
            $crossOwner = DB::table('recipe_ingredients as line')
                ->join('food_items as item', 'item.id', '=', "line.{$column}")
                ->whereColumn('item.user_id', '!=', 'line.user_id')
                ->count();

            if ($crossOwner > 0) {
                throw new RuntimeException(
                    "Cannot key recipe_ingredients.{$column} to its owner: {$crossOwner} rows name an item ".
                    'belonging to somebody else. Nothing has been changed.'
                );
            }
        }
    }
};
