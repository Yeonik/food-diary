<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\Goal;
use App\Models\MealEntry;
use App\Models\User;
use App\Models\WeightEntry;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every domain record belongs to somebody, enforced by the database rather than
 * by the code that happens to write it.
 *
 * Reading across owners is a separate claim and is not asserted here — the
 * global scope arrives next, with a test class of its own. What this pins is the
 * shape underneath it: without the column being required and keyed, a scope is a
 * suggestion.
 */
class RecordOwnershipTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function domainTables(): array
    {
        return [
            'food items' => ['food_items'],
            'aliases' => ['food_item_aliases'],
            'recipe ingredients' => ['recipe_ingredients'],
            'meal entries' => ['meal_entries'],
            'weight entries' => ['weight_entries'],
            'goals' => ['goals'],
        ];
    }

    #[DataProvider('domainTables')]
    public function test_the_owner_column_is_required_and_keyed_to_an_account(string $table): void
    {
        $column = collect(DB::select("pragma table_info({$table})"))->firstWhere('name', 'user_id');

        $this->assertNotNull($column, "{$table} has no user_id column at all.");
        $this->assertSame(1, (int) $column->notnull, "{$table}.user_id is still nullable.");

        // One row per column per key, so `user_id` can appear more than once:
        // on two tables it is also the second half of a composite key naming the
        // owner of a food item. The one wanted here is the key that is user_id
        // and nothing else.
        $key = collect(DB::select("pragma foreign_key_list({$table})"))
            ->groupBy('id')
            ->first(fn ($columns) => $columns->count() === 1 && $columns->first()->from === 'user_id');

        $this->assertNotNull($key, "{$table}.user_id is not a foreign key, so it can name an account that does not exist.");
        $this->assertSame('users', $key->first()->table);
    }

    #[DataProvider('domainTables')]
    public function test_a_row_with_no_owner_is_refused_by_the_database(string $table): void
    {
        $this->expectException(QueryException::class);

        // Deliberately through the query builder: the point is that the refusal
        // does not depend on the model remembering to fill anything in.
        DB::table($table)->insert(['user_id' => null]);
    }

    public function test_two_people_can_record_a_weight_for_the_same_day(): void
    {
        // This was impossible before: `recorded_on` was unique on its own, so the
        // second person to weigh themselves on a Tuesday was refused.
        $first = $this->signIn();
        WeightEntry::query()->create(['recorded_on' => '2026-07-20', 'weight_kg' => 77.5]);

        $second = $this->signIn(User::factory()->create());
        WeightEntry::query()->create(['recorded_on' => '2026-07-20', 'weight_kg' => 64.0]);

        // Counted past the scope on purpose: this is a claim about the table, not
        // about what either person can see.
        $this->assertSame(2, DB::table('weight_entries')->count());
        $this->assertSame(1, WeightEntry::ownedBy($first)->count());
        $this->assertSame(1, WeightEntry::ownedBy($second)->count());

        // And one person still cannot log two readings for one day.
        $this->signIn($first);

        $this->expectException(QueryException::class);
        WeightEntry::query()->create(['recorded_on' => '2026-07-20', 'weight_kg' => 78.0]);
    }

    public function test_a_person_has_at_most_one_goal(): void
    {
        $user = $this->signIn();
        Goal::query()->create(['daily_kcal' => 2000]);

        $this->signIn(User::factory()->create());
        Goal::query()->create(['daily_kcal' => 1800]);

        $this->assertSame(2, DB::table('goals')->count());

        $this->signIn($user);

        $this->expectException(QueryException::class);
        Goal::query()->create(['daily_kcal' => 2200]);
    }

    public function test_a_record_takes_the_owner_of_whoever_is_signed_in(): void
    {
        $user = $this->signIn();

        $item = FoodItem::factory()->create();
        $entry = MealEntry::factory()->create();
        $weight = WeightEntry::factory()->create();

        foreach ([$item, $entry, $weight] as $record) {
            $this->assertSame($user->id, $record->user_id);
            $this->assertTrue($record->user->is($user));
        }
    }

    public function test_an_alias_disagreeing_with_its_item_is_refused_by_the_database(): void
    {
        // An alias keeps its own user_id rather than reaching through the item,
        // so the two could in principle disagree. `(food_item_id, user_id)` is
        // one key, so they cannot — and this asks the table rather than the
        // application, which would only be agreeing with itself.
        //
        // Worth holding from underneath: lookups match against aliases as well
        // as against the item's own name, so one attached across the boundary
        // would steer somebody else's recognition.
        $this->signIn();
        $item = FoodItem::factory()->create();

        $this->signIn(User::factory()->create());

        $this->expectException(QueryException::class);

        DB::table('food_item_aliases')->insert([
            'user_id' => auth()->id(),
            'food_item_id' => $item->id,
            'name' => 'a name for something that is not mine',
        ]);
    }

    public function test_a_recipe_line_disagreeing_with_its_recipe_is_refused_by_the_database(): void
    {
        // The same invariant for recipe lines, held from underneath instead of
        // by the application agreeing with itself. `(recipe_id, user_id)` is one
        // key, so a line naming this recipe has to name this recipe's owner too.
        $this->signIn();
        $recipe = FoodItem::factory()->recipe()->create();
        $ingredient = FoodItem::factory()->create();

        $somebodyElse = User::factory()->create();

        $this->expectException(QueryException::class);

        // Through the query builder: no model, no scope, no validation rule —
        // just the row and the table's opinion of it.
        DB::table('recipe_ingredients')->insert([
            'user_id' => $somebodyElse->id,
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'grams' => 200,
        ]);
    }

    public function test_a_recipe_line_naming_another_persons_ingredient_is_refused_by_the_database(): void
    {
        $this->signIn();
        $recipe = FoodItem::factory()->recipe()->create();

        $this->signIn(User::factory()->create());
        $theirIngredient = FoodItem::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('recipe_ingredients')->insert([
            'user_id' => $recipe->user_id,
            'recipe_id' => $recipe->id,
            'ingredient_id' => $theirIngredient->id,
            'grams' => 200,
        ]);
    }

    public function test_an_entry_pointing_at_another_persons_item_is_refused_by_the_database(): void
    {
        // The lock under the code from the merge commit: the validation rules
        // and the scoped read there are what a person meets, and this is what
        // holds if a query is ever written that meets neither.
        $mine = $this->signIn();

        $this->signIn(User::factory()->create());
        $theirItem = FoodItem::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('meal_entries')->insert([
            'user_id' => $mine->id,
            'food_item_id' => $theirItem->id,
            'logged_at' => '2026-07-20 12:00:00',
            'meal' => 'lunch',
            'name' => 'Something of theirs',
            'grams' => 100,
            'kcal' => 100,
            'protein_g' => 1,
            'fat_g' => 2,
            'carbs_g' => 3,
            'source' => 'library',
        ]);
    }

    public function test_an_entry_with_no_link_at_all_is_still_allowed(): void
    {
        // Half the entries in the app are this: logged from a source that is not
        // the library, or from an item since deleted. A null anywhere in a
        // composite key means the key is not checked, which is what makes the
        // constraint above affordable — but it is worth pinning, because getting
        // it wrong would make every hand-entered meal impossible to save.
        $mine = $this->signIn();

        DB::table('meal_entries')->insert([
            'user_id' => $mine->id,
            'food_item_id' => null,
            'logged_at' => '2026-07-20 12:00:00',
            'meal' => 'lunch',
            'name' => 'Straight off the packet',
            'grams' => 100,
            'kcal' => 100,
            'protein_g' => 1,
            'fat_g' => 2,
            'carbs_g' => 3,
            'source' => 'manual',
        ]);

        $this->assertSame(1, DB::table('meal_entries')->count());
    }

    public function test_nothing_is_written_when_nobody_is_signed_in(): void
    {
        // Fail closed. A console context that means to write on somebody's behalf
        // has to say whose; it does not get a silent default.
        $this->assertFalse(auth()->check());

        $this->expectException(QueryException::class);
        WeightEntry::query()->create(['recorded_on' => '2026-07-20', 'weight_kg' => 77.5]);
    }

    public function test_the_weight_uniqueness_is_now_per_person(): void
    {
        $indexes = collect(DB::select('pragma index_list(weight_entries)'))
            ->filter(fn ($index) => (bool) $index->unique)
            ->map(fn ($index) => collect(DB::select("pragma index_info({$index->name})"))
                ->pluck('name')->sort()->values()->all())
            ->values()
            ->all();

        $this->assertContains(['recorded_on', 'user_id'], $indexes);
        $this->assertNotContains(['recorded_on'], $indexes, 'The old global unique index survived.');
    }

    public function test_every_domain_table_is_accounted_for(): void
    {
        // A table added later that holds a person's data and is not listed above
        // would otherwise be scoped by nobody and noticed by no one.
        //
        // `invites` is listed here rather than among the domain tables, and the
        // distinction is the point: it is not one person's data kept apart from
        // another's, it is the owner's record of who may join. It carries no
        // user_id to scope by and is reached only through the owner's own
        // screens, behind the owner's authorisation — never through a query
        // made on behalf of whoever is signed in.
        $ownerAdministered = ['invites'];

        $known = array_merge(
            array_map(fn (array $case): string => $case[0], array_values(self::domainTables())),
            $ownerAdministered,
            ['users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks',
                'jobs', 'job_batches', 'failed_jobs', 'migrations'],
        );

        $tables = collect(Schema::getTableListing())
            ->map(fn (string $table): string => str_contains($table, '.') ? explode('.', $table)[1] : $table)
            ->reject(fn (string $table): bool => in_array($table, $known, true))
            ->values();

        $this->assertEmpty(
            $tables->all(),
            'Unlisted table(s): '.$tables->implode(', ').'. If they hold a person\'s data they need an owner.',
        );
    }
}
