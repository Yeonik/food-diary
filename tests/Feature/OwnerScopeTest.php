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
use App\Nutrition\FoodResolver;
use App\Nutrition\SearchTerms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Reading is constrained to the signed-in person, by the model rather than by
 * the query.
 *
 * This is a different claim from "every record has an owner", which
 * RecordOwnershipTest holds, and it is kept in its own class so that removing
 * the scope breaks these and removing the write half breaks those. The two
 * failures should never be the same failure.
 *
 * What this does NOT cover is reaching another person's record through a route
 * that takes an id — that is the isolation class, and it comes next.
 */
class OwnerScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * One row for each of two people, in every scoped table.
     *
     * @return array{0: User, 1: User}
     */
    private function twoPeopleWithData(): array
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        foreach ([$mine, $theirs] as $user) {
            $this->signIn($user);

            $item = FoodItem::factory()->create(['name' => "{$user->id}'s item"]);
            FoodItemAlias::query()->create(['food_item_id' => $item->id, 'name' => "{$user->id}'s alias"]);

            $recipe = FoodItem::factory()->recipe()->create(['name' => "{$user->id}'s recipe"]);
            RecipeIngredient::query()->create([
                'recipe_id' => $recipe->id,
                'ingredient_id' => $item->id,
                'grams' => 100,
            ]);

            MealEntry::factory()->create(['name' => "{$user->id}'s entry"]);
            WeightEntry::query()->create(['recorded_on' => '2026-07-20', 'weight_kg' => 70 + $user->id]);
            Goal::query()->create(['daily_kcal' => 2000 + $user->id]);
        }

        return [$mine, $theirs];
    }

    /**
     * @return array<string, array{0: class-string<Model>, 1: int}>
     */
    public static function scopedModels(): array
    {
        return [
            // Two food items each: a direct one and a recipe.
            'food items' => [FoodItem::class, 2],
            'aliases' => [FoodItemAlias::class, 1],
            'recipe ingredients' => [RecipeIngredient::class, 1],
            'meal entries' => [MealEntry::class, 1],
            'weight entries' => [WeightEntry::class, 1],
            'goals' => [Goal::class, 1],
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('scopedModels')]
    public function test_a_read_returns_only_the_signed_in_persons_rows(string $model, int $expected): void
    {
        [$mine, $theirs] = $this->twoPeopleWithData();
        $table = (new $model)->getTable();

        $this->signIn($mine);

        $this->assertSame($expected * 2, DB::table($table)->count(), 'The fixture did not make two peoples\' rows.');
        $this->assertSame($expected, $model::query()->count());

        foreach ($model::query()->get() as $row) {
            $this->assertSame($mine->id, $row->getAttribute('user_id'));
        }

        // And the same query, as the other person, answers with the other set.
        $this->signIn($theirs);
        $this->assertSame($expected, $model::query()->count());

        foreach ($model::query()->get() as $row) {
            $this->assertSame($theirs->id, $row->getAttribute('user_id'));
        }
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('scopedModels')]
    public function test_with_nobody_signed_in_a_read_returns_nothing_not_everything(string $model, int $expected): void
    {
        $this->twoPeopleWithData();

        auth()->logout();

        // The rows are there — the emptiness below is the scope, not an empty
        // fixture.
        $this->assertSame($expected * 2, DB::table((new $model)->getTable())->count());

        // Fail closed. The tempting implementation — apply the scope only when
        // somebody is signed in — turns every console context into a query with
        // no constraint at all, which is the leak this class exists to prevent.
        $this->assertSame(0, $model::query()->count());
        $this->assertNull($model::query()->first());
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('scopedModels')]
    public function test_a_named_person_can_be_read_deliberately(string $model, int $expected): void
    {
        [, $theirs] = $this->twoPeopleWithData();

        auth()->logout();

        // The sanctioned way out, for the console and the seeders: name whose
        // rows you mean. It never widens to everybody.
        $this->assertSame($expected, $model::ownedBy($theirs)->count());
        $this->assertSame($expected, $model::ownedBy($theirs->id)->count());
    }

    public function test_a_relation_is_scoped_too(): void
    {
        // Relations are the quiet way round a scope: `$recipe->ingredients` is a
        // fresh query on the related model, so it has to be constrained by the
        // same rule the direct read is.
        [$mine] = $this->twoPeopleWithData();

        $this->signIn($mine);
        $recipe = FoodItem::query()->where('kind', 'recipe')->sole();

        $this->assertCount(1, $recipe->ingredients);
        $this->assertSame($mine->id, $recipe->ingredients->first()?->user_id);
    }

    public function test_the_first_tier_only_answers_from_your_own_library(): void
    {
        // The point of the whole thing, in domain terms. The personal library
        // outranks USDA and Open Food Facts because *you* verified it; a
        // stranger's entry arriving in tier one would destroy exactly the
        // property that makes the tier worth having.
        Http::fake([
            'api.nal.usda.gov/*' => Http::response(['foods' => []]),
            'world.openfoodfacts.org/*' => Http::response(['products' => []]),
        ]);

        $stranger = User::factory()->create();
        $this->signIn($stranger);
        FoodItem::factory()->direct(kcal: 165, protein: 31, fat: 3.6, carbs: 0)
            ->create(['name' => 'Grilled chicken breast']);

        $this->signIn(User::factory()->create());

        $resolution = app(FoodResolver::class)->resolve(new SearchTerms('Grilled chicken breast'));

        $this->assertFalse(
            $resolution->hasLibraryMatch(),
            "Somebody else's library answered the first tier.",
        );

        // And the same lookup, made by the person who owns that item, does.
        $this->signIn($stranger);

        $this->assertTrue(
            app(FoodResolver::class)->resolve(new SearchTerms('Grilled chicken breast'))->hasLibraryMatch(),
            'Nobody could reach that item at all, so the check above proved nothing.',
        );
    }
}
