<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A logged entry may only point back at a library item its own owner holds.
 *
 * `(food_item_id, user_id)` references `food_items(id, user_id)`. An entry with
 * no link still satisfies it: a foreign key with a null anywhere in the child
 * key is not checked, which is exactly the "logged, but not from the library"
 * case.
 *
 * **The delete behaviour had to change, and this is the whole trade.** The key
 * used to be `ON DELETE SET NULL`, which is how deleting a library item left
 * past entries holding their snapshot and losing only the provenance link. A
 * composite key cannot keep that: SET NULL nulls every column of the child key,
 * including `user_id`, which is NOT NULL — so deleting an item any entry
 * referenced would be refused outright.
 *
 * So the key takes no delete action at all, and the unlinking moves into the one
 * place that deletes a library item — the library's delete route, since the
 * merge already unlinks by repointing. That is code doing what the engine
 * did, which is a real cost — but it fails in the safe direction. If the unlink
 * were ever forgotten, the delete is refused and the person sees their own
 * action fail. What cannot happen either way is an entry reaching across to
 * somebody else's library.
 *
 * A rebuild, so not in a transaction — see the step C migrations for why.
 */
return new class extends Migration
{
    public function up(): void
    {
        $crossOwner = DB::table('meal_entries as entry')
            ->join('food_items as item', 'item.id', '=', 'entry.food_item_id')
            ->whereColumn('item.user_id', '!=', 'entry.user_id')
            ->count();

        if ($crossOwner > 0) {
            // Before the rebuild, while the table is still the table it was.
            throw new RuntimeException(
                "Cannot key meal_entries.food_item_id to its owner: {$crossOwner} entries point at an item ".
                'belonging to somebody else. Nothing has been changed.'
            );
        }

        Schema::table('meal_entries', function (Blueprint $blueprint): void {
            $blueprint->dropForeign(['food_item_id']);

            $blueprint->foreign(['food_item_id', 'user_id'])
                ->references(['id', 'user_id'])->on('food_items');
        });
    }

    public function down(): void
    {
        Schema::table('meal_entries', function (Blueprint $blueprint): void {
            $blueprint->dropForeign(['food_item_id', 'user_id']);
            $blueprint->foreign('food_item_id')->references('id')->on('food_items')->nullOnDelete();
        });
    }
};
