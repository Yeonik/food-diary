<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The last table where a row could name a food item its owner does not hold.
 *
 * `(food_item_id, user_id)` references `food_items(id, user_id)`, so an alias
 * belongs to the same person as the item it names — held by the table rather
 * than by the fact that both are written by whoever is signed in.
 *
 * An alias is not decoration: lookups match against these as well as against the
 * item's own name, so one attached across the boundary would be a stranger's
 * phrasing steering somebody else's recognition. The reason the personal library
 * outranks USDA is that the person verified what is in it, and that reason has
 * to cover the names too.
 *
 * `CASCADE` survives the change untouched — it removes the whole row, so nothing
 * has to be nulled and `user_id` stays NOT NULL under it.
 *
 * A rebuild, so not in a transaction — see the step C migrations for why.
 */
return new class extends Migration
{
    public function up(): void
    {
        $crossOwner = DB::table('food_item_aliases as alias')
            ->join('food_items as item', 'item.id', '=', 'alias.food_item_id')
            ->whereColumn('item.user_id', '!=', 'alias.user_id')
            ->count();

        if ($crossOwner > 0) {
            // Before the rebuild, while the table is still the table it was.
            throw new RuntimeException(
                "Cannot key food_item_aliases.food_item_id to its owner: {$crossOwner} aliases name an item ".
                'belonging to somebody else. Nothing has been changed.'
            );
        }

        Schema::table('food_item_aliases', function (Blueprint $blueprint): void {
            $blueprint->dropForeign(['food_item_id']);

            $blueprint->foreign(['food_item_id', 'user_id'])
                ->references(['id', 'user_id'])->on('food_items')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('food_item_aliases', function (Blueprint $blueprint): void {
            $blueprint->dropForeign(['food_item_id', 'user_id']);
            $blueprint->foreign('food_item_id')->references('id')->on('food_items')->cascadeOnDelete();
        });
    }
};
