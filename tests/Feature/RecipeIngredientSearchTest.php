<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Nutrition\FoodItemKind;
use App\Nutrition\MealLogService;
use App\Nutrition\NutrientSource;
use App\Nutrition\ProfileOrigin;
use App\Nutrition\SearchTerms;
use App\Support\RecipeDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Building a recipe from database ingredients, so an empty library is no longer
 * a wall in front of the first recipe.
 *
 * The flow is a round trip through the session — search, choose, add — and two
 * things matter beyond "it works": the chosen ingredient becomes a real library
 * row (a recipe cannot point at anything else), and its numbers come from the
 * source's record, never from the form.
 */
class RecipeIngredientSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    private function fakeUsdaReturns(string $description, float $kcal, float $protein, float $fat, float $carbs, int $fdcId = 1234): void
    {
        Http::fake([
            'api.nal.usda.gov/*' => Http::response(['foods' => [[
                'description' => $description,
                'fdcId' => $fdcId,
                'foodNutrients' => [
                    ['nutrientNumber' => '208', 'value' => $kcal],
                    ['nutrientNumber' => '203', 'value' => $protein],
                    ['nutrientNumber' => '204', 'value' => $fat],
                    ['nutrientNumber' => '205', 'value' => $carbs],
                ],
            ]]]),
            'world.openfoodfacts.org/*' => Http::response(['products' => []]),
        ]);
    }

    private function search(string $query): void
    {
        $this->post(route('library.recipe.ingredient.search'), ['query' => $query])
            ->assertRedirect(route('library.recipe.ingredient.choose'));
    }

    /** @return array<string, mixed> */
    private function usdaFood(string $description, float $kcal, float $protein, float $fat, float $carbs, int $fdcId): array
    {
        return [
            'description' => $description,
            'fdcId' => $fdcId,
            'foodNutrients' => [
                ['nutrientNumber' => '208', 'value' => $kcal],
                ['nutrientNumber' => '203', 'value' => $protein],
                ['nutrientNumber' => '204', 'value' => $fat],
                ['nutrientNumber' => '205', 'value' => $carbs],
            ],
        ];
    }

    /**
     * A fake FoodData Central that behaves the way the live one does: filtered to
     * raw types (Foundation / SR Legacy) it returns the raw ingredient; with no
     * dataType it returns FNDDS survey dishes on top, above the raw food. So the
     * candidates depend on the dataType the search actually sends.
     */
    private function fakeUsdaByDataType(): void
    {
        Http::fake([
            'api.nal.usda.gov/*' => function (Request $request) {
                $dataType = $request->data()['dataType'] ?? '';
                $dataType = is_string($dataType) ? $dataType : '';

                $foods = str_contains($dataType, 'Foundation')
                    ? [
                        $this->usdaFood('Potatoes, boiled, without salt', 87, 1.9, 0.1, 20, 170026),
                        $this->usdaFood('Potatoes, raw', 77, 2.0, 0.1, 17, 170093),
                    ]
                    : [
                        $this->usdaFood('Potato patty', 210, 4.0, 12, 22, 1102570),
                        $this->usdaFood('Potato soup, NFS', 70, 2.0, 3.0, 9, 1102600),
                        $this->usdaFood('Potatoes, raw', 77, 2.0, 0.1, 17, 170093),
                    ];

                return Http::response(['foods' => $foods]);
            },
            'world.openfoodfacts.org/*' => Http::response(['products' => []]),
        ]);
    }

    public function test_a_usda_ingredient_can_be_searched_chosen_and_added(): void
    {
        $this->fakeUsdaReturns('Rice, white, long-grain, cooked', 130, 2.7, 0.3, 28);

        $this->search('rice');

        // The candidate screen shows the result with its source.
        $this->get(route('library.recipe.ingredient.choose'))
            ->assertOk()
            ->assertSee('Rice, white, long-grain, cooked')
            ->assertSee(__('source.usda'));

        $this->post(route('library.recipe.ingredient.add'), ['candidate' => 0, 'grams' => 200])
            ->assertRedirect(route('library.recipe.create'));

        // It is now a real library item — a recipe ingredient must be one.
        $item = FoodItem::query()->where('name', 'Rice, white, long-grain, cooked')->sole();
        $this->assertSame(FoodItemKind::Direct, $item->kind);
        $this->assertSame(ProfileOrigin::Usda, $item->origin);
        $this->assertSame(130.0, $item->kcal_per_100g);

        // And it is on the draft, ready to be part of the recipe, at the weight
        // that was given.
        $draft = RecipeDraft::fromSession(session(RecipeDraft::SESSION_KEY));
        $this->assertNotNull($draft);
        $this->assertSame([['item_id' => $item->id, 'grams' => 200.0]], $draft->ingredients);
    }

    public function test_the_usda_search_returns_raw_foods_not_survey_dishes(): void
    {
        // The point of the dataType filter: an ingredient search wants the raw
        // food a recipe is built from, not the mixed dishes FNDDS codes.
        $this->fakeUsdaByDataType();

        $this->search('potato');

        $this->get(route('library.recipe.ingredient.choose'))
            ->assertOk()
            // The raw ingredient is offered...
            ->assertSee('Potatoes, boiled, without salt')
            // ...and the survey dishes are not among the candidates at all, so
            // they cannot sit on top of the one the person wants.
            ->assertDontSee('Potato patty')
            ->assertDontSee('Potato soup, NFS');

        // USDA was asked for raw types only.
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'nal.usda.gov')
            && str_contains((string) ($request->data()['dataType'] ?? ''), 'Foundation')
            && str_contains((string) ($request->data()['dataType'] ?? ''), 'SR Legacy'));
    }

    public function test_the_numbers_come_from_the_source_not_the_form(): void
    {
        $this->fakeUsdaReturns('Butter', 717, 0.85, 81, 0.06);

        $this->search('butter');

        // The add request carries forged nutrient fields alongside the choice.
        // They must be ignored: the promoted item carries USDA's numbers.
        $this->post(route('library.recipe.ingredient.add'), [
            'candidate' => 0,
            'grams' => 50,
            'kcal' => 1,
            'protein' => 999,
            'fat' => 999,
            'carbs' => 999,
            'source' => 'manual',
        ])->assertRedirect();

        $item = FoodItem::query()->where('name', 'Butter')->sole();
        $this->assertSame(717.0, $item->kcal_per_100g);
        $this->assertSame(81.0, $item->fat_g_per_100g);
        $this->assertSame(ProfileOrigin::Usda, $item->origin);
    }

    public function test_a_library_ingredient_is_added_without_being_duplicated(): void
    {
        Http::fake([
            'api.nal.usda.gov/*' => Http::response(['foods' => []]),
            'world.openfoodfacts.org/*' => Http::response(['products' => []]),
        ]);

        $existing = FoodItem::factory()->direct(kcal: 89, protein: 1.1, fat: 0.3, carbs: 23)
            ->create(['name' => 'Banana']);

        $this->search('Banana');
        $this->post(route('library.recipe.ingredient.add'), ['candidate' => 0, 'grams' => 120])
            ->assertRedirect();

        // The library item is reused, not promoted a second time.
        $this->assertSame(1, FoodItem::query()->where('name', 'Banana')->count());

        $draft = RecipeDraft::fromSession(session(RecipeDraft::SESSION_KEY));
        $this->assertSame([['item_id' => $existing->id, 'grams' => 120.0]], $draft?->ingredients);
    }

    public function test_searching_carries_the_recipe_so_far_into_the_draft(): void
    {
        $this->fakeUsdaReturns('Carrot, raw', 41, 0.9, 0.2, 10);

        $existing = FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)->create();

        // The person had typed a name, a cooked weight, and one row before
        // searching. Those must not be lost.
        $this->post(route('library.recipe.ingredient.search'), [
            'query' => 'carrot',
            'name' => 'Winter stew',
            'cooked_weight_g' => '800',
            'ingredients' => [['item_id' => $existing->id, 'grams' => 300]],
        ])->assertRedirect(route('library.recipe.ingredient.choose'));

        $draft = RecipeDraft::fromSession(session(RecipeDraft::SESSION_KEY));
        $this->assertSame('Winter stew', $draft?->name);
        $this->assertSame('800', $draft?->cookedWeight);
        $this->assertSame([['item_id' => $existing->id, 'grams' => 300.0]], $draft?->ingredients);
    }

    public function test_the_recipe_form_shows_the_ingredient_added_through_search(): void
    {
        $this->fakeUsdaReturns('Rice, white', 130, 2.7, 0.3, 28);

        $this->search('rice');
        $this->post(route('library.recipe.ingredient.add'), ['candidate' => 0, 'grams' => 200]);

        // Back on the recipe form, the promoted ingredient is selectable and
        // selected in a row.
        $item = FoodItem::query()->where('name', 'Rice, white')->sole();
        $this->get(route('library.recipe.create'))
            ->assertOk()
            ->assertSee('Rice, white')
            ->assertSee('value="'.$item->id.'"', false);
    }

    public function test_saving_the_recipe_clears_the_draft(): void
    {
        $this->fakeUsdaReturns('Rice, white', 130, 2.7, 0.3, 28);

        $this->search('rice');
        $this->post(route('library.recipe.ingredient.add'), ['candidate' => 0, 'grams' => 200]);
        $item = FoodItem::query()->where('name', 'Rice, white')->sole();

        $this->post(route('library.recipe.store'), [
            'name' => 'Plain rice',
            'cooked_weight_g' => 250,
            'ingredients' => [['item_id' => $item->id, 'grams' => 200]],
        ])->assertRedirect(route('library.index'));

        $this->assertNull(session(RecipeDraft::SESSION_KEY));
        $recipe = FoodItem::query()->where('name', 'Plain rice')->sole();
        $this->assertSame(1, $recipe->ingredients()->count());
    }

    public function test_an_estimate_cannot_be_promoted_as_an_ingredient(): void
    {
        // The normal flow never offers an estimate — the search passes no
        // fallback and the choose screen filters them — so this exercises the
        // last-ditch guard in promoteCandidate directly: an estimate has no
        // honest number and must not become an ingredient.
        $log = app(MealLogService::class);

        $estimate = [
            'source' => NutrientSource::Estimated->value,
            'kcal' => 200, 'protein' => 10, 'fat' => 8, 'carbs' => 22,
            'external_id' => null, 'food_item_id' => null, 'label' => 'a guess',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $log->promoteCandidate($estimate, new SearchTerms('a guess'));
    }

    public function test_a_search_that_finds_nothing_says_so_and_adds_nothing(): void
    {
        Http::fake([
            'api.nal.usda.gov/*' => Http::response(['foods' => []]),
            'world.openfoodfacts.org/*' => Http::response(['products' => []]),
        ]);

        $this->search('nonexistent unicorn meat');

        $this->get(route('library.recipe.ingredient.choose'))
            ->assertOk()
            ->assertSee(__('library.ingredient_none', ['query' => 'nonexistent unicorn meat']));

        $this->assertSame(0, FoodItem::query()->count());
    }
}
