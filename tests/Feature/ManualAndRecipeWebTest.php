<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Nutrition\FoodItemKind;
use App\Nutrition\NutrientSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ManualAndRecipeWebTest extends TestCase
{
    use RefreshDatabase;

    private function fakeEmptyApis(): void
    {
        Http::fake([
            'api.nal.usda.gov/*' => Http::response(['foods' => []]),
            'world.openfoodfacts.org/*' => Http::response(['products' => []]),
        ]);
    }

    public function test_a_recipe_can_be_defined_and_then_logged(): void
    {
        $rice = FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)->create(['name' => 'Rice']);
        $chicken = FoodItem::factory()->direct(kcal: 165, protein: 31, fat: 3.6, carbs: 0)->create(['name' => 'Chicken']);

        // Define the recipe through the form.
        $this->post(route('library.recipe.store'), [
            'name' => 'Plov',
            'ingredients' => [
                ['item_id' => $rice->id, 'grams' => 200],
                ['item_id' => $chicken->id, 'grams' => 100],
            ],
        ])->assertRedirect(route('library.index'));

        $recipe = FoodItem::query()->where('name', 'Plov')->firstOrFail();
        $this->assertSame(FoodItemKind::Recipe, $recipe->kind);
        $this->assertSame(2, $recipe->ingredients()->count());

        // Log it via the manual path.
        $this->fakeEmptyApis();
        $this->post(route('log.manual.store'), ['name' => 'Plov'])->assertRedirect(route('log.confirm'));
        $this->get(route('log.confirm'))->assertOk()->assertSee('Plov');

        $this->post(route('log.confirm.store'), [
            'meal' => 'dinner',
            'items' => [['candidate' => 0, 'grams' => 300]],
        ])->assertRedirect();

        $entry = MealEntry::query()->where('name', 'Plov')->firstOrFail();
        $this->assertSame(NutrientSource::PersonalLibrary, $entry->source);
        // 300 g of the recipe ≈ its whole 300 g batch: 425 kcal.
        $this->assertEqualsWithDelta(425.0, $entry->kcal, 0.5);
    }

    public function test_a_recipe_update_that_forms_a_cycle_is_rejected(): void
    {
        $flour = FoodItem::factory()->direct(kcal: 364, protein: 10, fat: 1, carbs: 76)->create();
        $recipe = FoodItem::factory()->recipe()->create(['name' => 'Dough']);
        $recipe->ingredients()->create(['ingredient_id' => $flour->id, 'grams' => 100]);

        // A crafted request that makes the recipe reference itself.
        $this->from(route('library.recipe.edit', $recipe))
            ->patch(route('library.recipe.update', $recipe), [
                'name' => 'Dough',
                'ingredients' => [['item_id' => $recipe->id, 'grams' => 100]],
            ])
            ->assertRedirect(route('library.recipe.edit', $recipe))
            ->assertSessionHasErrors('ingredients');

        // The original ingredient is untouched.
        $this->assertSame($flour->id, $recipe->ingredients()->first()?->ingredient_id);
    }

    public function test_a_manual_search_without_a_photo_can_log_a_library_item(): void
    {
        FoodItem::factory()->direct(kcal: 52, protein: 0.3, fat: 0.2, carbs: 14)->create(['name' => 'Apple']);
        $this->fakeEmptyApis();

        // No photo: the name search alone resolves and reaches the confirm screen,
        // where the library match is chosen and logged.
        $this->post(route('log.manual.store'), ['name' => 'Apple'])->assertRedirect(route('log.confirm'));
        $this->post(route('log.confirm.store'), [
            'meal' => 'snack',
            'items' => [['candidate' => 0, 'grams' => 150]],
        ])->assertRedirect();

        $entry = MealEntry::query()->where('name', 'Apple')->firstOrFail();
        $this->assertSame(NutrientSource::PersonalLibrary, $entry->source);
        $this->assertEqualsWithDelta(78.0, $entry->kcal, 0.1); // 52 * 1.5
    }
}
