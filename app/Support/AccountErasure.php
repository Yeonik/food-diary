<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Removing an account and everything it holds.
 *
 * **The order is the whole of this class**, and it is written out rather than
 * left to the engine. Every table cascades from `users`, so a bare
 * `$user->delete()` looks like it would do the job — but two of the keys added
 * with the schema work are not plain cascades:
 *
 * - `recipe_ingredients.ingredient_id` is RESTRICT. A food item a recipe is
 *   built on cannot be deleted while the line exists, so the lines have to go
 *   first. Relying on the cascade to reach them before it reaches the items is
 *   relying on the order SQLite happens to process foreign keys in, which is not
 *   a promise and is not visible to anybody reading this.
 * - `meal_entries.food_item_id` takes no delete action at all, by design (a
 *   composite key cannot null one of its columns without nulling the owner). An
 *   entry still pointing at a food item blocks that item's removal.
 *
 * So the order is: everything that refers to a food item, then food items, then
 * the rest, then the account. The cascades stay in place underneath as a
 * backstop; what they are not is the plan.
 *
 * What deliberately survives is the invitation the person joined with. Its
 * `used_by` becomes null and `used_at` stays set, so the owner keeps the record
 * that somebody was invited and the code cannot be spent again — see the
 * migration that added revocation.
 */
final class AccountErasure
{
    /**
     * Tables to empty, in the order they have to be emptied in.
     *
     * @var list<string>
     */
    private const IN_ORDER = [
        // Referring to a food item, so before food items.
        'meal_entries',
        'recipe_ingredients',
        'food_item_aliases',

        'food_items',

        // Standing alone: nothing points at these.
        'weight_entries',
        'goals',
        'recognitions',
    ];

    public function erase(User $user): void
    {
        DB::transaction(function () use ($user): void {
            foreach (self::IN_ORDER as $table) {
                // Deliberately by column rather than through the scoped models:
                // this has to work the same whether it is run by the person
                // themselves, by the owner, or by a console command, and none of
                // those should depend on who happens to be signed in.
                DB::table($table)->where('user_id', $user->id)->delete();
            }

            // Sessions are not the person's data and are not in the list above,
            // but they are rows that name them. `sessions.user_id` carries no
            // foreign key — it is a plain indexed column — so nothing removes
            // these on its own, and a signed-in session outliving the account it
            // belonged to is a loose end whichever path got here. Somebody
            // leaving of their own accord has their current session invalidated
            // by the controller as well; this covers the others, and covers the
            // owner removing an account whose session they cannot reach.
            DB::table('sessions')->where('user_id', $user->id)->delete();

            DB::table('users')->where('id', $user->id)->delete();
        });
    }

    /**
     * What erasing this account would remove, table by table.
     *
     * Read from the same list that does the removing, so a screen that warns
     * somebody cannot quietly fall out of step with what actually happens — add
     * a table to the erasure and it appears in the warning by itself.
     *
     * @return array<string, int>
     */
    public function tally(User $user): array
    {
        $counts = [];

        foreach (self::IN_ORDER as $table) {
            $counts[$table] = DB::table($table)->where('user_id', $user->id)->count();
        }

        return $counts;
    }
}
