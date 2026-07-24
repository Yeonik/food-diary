<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the owner sees for a recipe with no cooked weight — the state every
 * recipe defined before this release arrives in after the deploy.
 *
 * The number is already withheld (RecipeCalculatorTest, LibraryMatchingTest);
 * this is about the interface saying why, so the withholding reads as "finish
 * this" rather than as a recipe that mysteriously lost its figures.
 */
class IncompleteRecipeInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    public function test_the_library_marks_an_incomplete_recipe_and_does_not_check_it(): void
    {
        $rice = FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)->create();
        $recipe = FoodItem::factory()->recipe(cookedWeightG: null)->create(['name' => 'Old plov']);
        $recipe->ingredients()->create(['ingredient_id' => $rice->id, 'grams' => 200]);

        $html = (string) $this->get(route('library.index'))->assertOk()->getContent();

        // It is on the shelf, said to need a weight, and not wearing a check.
        $this->assertStringContainsString('Old plov', $html);
        $this->assertStringContainsString(__('library.needs_cooked_weight'), $html);

        // The check belongs only to a recipe whose number is honest. Isolate the
        // incomplete recipe's row and assert no check sits in it.
        $this->assertMatchesRegularExpression(
            '/Old plov.*?≈.*?'.preg_quote(__('library.needs_cooked_weight'), '/').'/s',
            $html,
        );
    }

    public function test_a_complete_recipe_still_shows_its_number_and_check(): void
    {
        $rice = FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)->create();
        $recipe = FoodItem::factory()->recipe(cookedWeightG: 200)->create(['name' => 'New plov']);
        $recipe->ingredients()->create(['ingredient_id' => $rice->id, 'grams' => 200]);

        $this->get(route('library.index'))
            ->assertOk()
            ->assertSee('New plov')
            ->assertSee('✓')
            ->assertDontSee(__('library.needs_cooked_weight'));
    }

    public function test_the_edit_screen_explains_the_missing_weight_instead_of_a_blank_total(): void
    {
        $rice = FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)->create();
        $recipe = FoodItem::factory()->recipe(cookedWeightG: null)->create(['name' => 'Old borsch']);
        $recipe->ingredients()->create(['ingredient_id' => $rice->id, 'grams' => 200]);

        $this->get(route('library.recipe.edit', $recipe))
            ->assertOk()
            ->assertSee(__('library.incomplete_notice'))
            // The field is there, empty, ready to be filled — not a total.
            ->assertSee(__('library.cooked_weight'))
            ->assertDontSee('total-bar');
    }

    public function test_the_edit_screen_of_a_complete_recipe_shows_its_total(): void
    {
        $rice = FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)->create();
        $recipe = FoodItem::factory()->recipe(cookedWeightG: 200)->create(['name' => 'New borsch']);
        $recipe->ingredients()->create(['ingredient_id' => $rice->id, 'grams' => 200]);

        $this->get(route('library.recipe.edit', $recipe))
            ->assertOk()
            ->assertSee('total-bar')
            ->assertDontSee(__('library.incomplete_notice'));
    }

    public function test_completing_a_recipe_makes_it_verified_again(): void
    {
        $rice = FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)->create();
        $recipe = FoodItem::factory()->recipe(cookedWeightG: null)->create(['name' => 'Filled in']);
        $recipe->ingredients()->create(['ingredient_id' => $rice->id, 'grams' => 200]);

        // The owner supplies the weight through the ordinary edit form.
        $this->patch(route('library.recipe.update', $recipe), [
            'name' => 'Filled in',
            'cooked_weight_g' => 250,
            'ingredients' => [['item_id' => $rice->id, 'grams' => 200]],
        ])->assertRedirect(route('library.index'));

        $this->assertFalse($recipe->fresh()?->needsCookedWeight());

        $this->get(route('library.index'))
            ->assertOk()
            ->assertSee('✓')
            ->assertDontSee(__('library.needs_cooked_weight'));
    }
}
