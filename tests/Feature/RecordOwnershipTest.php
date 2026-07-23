<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\FoodItemAlias;
use App\Models\Goal;
use App\Models\MealEntry;
use App\Models\RecipeIngredient;
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

        $key = collect(DB::select("pragma foreign_key_list({$table})"))->firstWhere('from', 'user_id');

        $this->assertNotNull($key, "{$table}.user_id is not a foreign key, so it can name an account that does not exist.");
        $this->assertSame('users', $key->table);
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

        $this->signIn(User::factory()->create());
        WeightEntry::query()->create(['recorded_on' => '2026-07-20', 'weight_kg' => 64.0]);

        $this->assertSame(2, WeightEntry::query()->count());

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

        $this->assertSame(2, Goal::query()->count());

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

    public function test_a_child_row_carries_the_same_owner_as_its_parent(): void
    {
        // Aliases and recipe lines keep their own user_id rather than reaching
        // through the parent, so the two could in principle disagree. Through the
        // app they cannot: both are written by the same signed-in person.
        $user = $this->signIn();

        $recipe = FoodItem::factory()->recipe()->create();
        $ingredient = FoodItem::factory()->create();

        $line = RecipeIngredient::query()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'grams' => 200,
        ]);
        $alias = FoodItemAlias::query()->create([
            'food_item_id' => $ingredient->id,
            'name' => 'another name for it',
        ]);

        $this->assertSame($recipe->user_id, $line->user_id);
        $this->assertSame($ingredient->user_id, $alias->user_id);
        $this->assertSame($user->id, $line->user_id);
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
        $known = array_merge(
            array_map(fn (array $case): string => $case[0], array_values(self::domainTables())),
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
