<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\RecipeIngredient;
use App\Nutrition\NutrientSource;
use App\Nutrition\ProfileOrigin;
use App\Nutrition\RecipeCalculator;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two ways to put something in the library by hand. Both screens are only
 * meaningful with data in them, so these render them filled and then submit.
 */
class LibraryFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_new_product_form_starts_empty_and_suggests_no_numbers(): void
    {
        $html = (string) $this->get(route('library.create'))->assertOk()->getContent();

        // Every nutrient field is blank: the model does not propose a figure here,
        // and neither does the app.
        foreach (['kcal_per_100g', 'protein_g_per_100g', 'fat_g_per_100g', 'carbs_g_per_100g'] as $field) {
            $this->assertMatchesRegularExpression(
                '/<input type="number"[^>]*name="'.$field.'"[^>]*value=""/',
                $html,
                "The {$field} field should start empty.",
            );
        }

        // And it says what hand entry means for provenance.
        $this->assertStringContainsString(__('library.manual_is_verified'), $html);
    }

    public function test_a_hand_entered_product_is_stored_as_manual(): void
    {
        $this->post(route('library.store'), [
            'name' => 'Buckwheat, boiled',
            'kcal_per_100g' => 110,
            'protein_g_per_100g' => 4,
            'fat_g_per_100g' => 1.1,
            'carbs_g_per_100g' => 21,
        ])->assertRedirect(route('library.index'));

        $item = FoodItem::query()->sole();
        $this->assertSame(ProfileOrigin::Manual, $item->origin);
        $this->assertSame(110.0, $item->kcal_per_100g);

        // And the profile it hands downstream is a verified one.
        $this->assertSame(NutrientSource::PersonalLibrary, $item->storedProfile()->source);
        $this->assertTrue($item->storedProfile()->source->isVerified());
    }

    public function test_the_recipe_editor_shows_what_the_ingredients_come_to(): void
    {
        $rice = FoodItem::factory()->create([
            'name' => 'Rice',
            'kcal_per_100g' => 130, 'protein_g_per_100g' => 2.7,
            'fat_g_per_100g' => 0.3, 'carbs_g_per_100g' => 28,
        ]);
        $recipe = FoodItem::factory()->recipe()->create(['name' => 'Plain rice']);
        RecipeIngredient::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $rice->id,
            'grams' => 400,
        ]);

        $expected = app(RecipeCalculator::class)->profileFor($recipe->fresh()->load('ingredients.ingredient'));

        $this->get(route('library.recipe.edit', $recipe))
            ->assertOk()
            ->assertSee(__('library.recipe_total'))
            ->assertSee('Rice')
            // The figure is the emphasised half of the pair, its unit the quiet one.
            ->assertSee('<b>'.Format::kcal($expected->kcal).'</b> '.__('nutrition.kcal'), false)
            ->assertSee('<b>'.Format::macro($expected->proteinG).'</b>', false);
    }

    public function test_the_add_ingredient_button_prints_its_glyph_once(): void
    {
        // The glyph used to sit in the phrase as well as in the markup, and the
        // button read "+ + Add ingredient".
        $html = (string) $this->get(route('library.recipe.create'))->assertOk()->getContent();

        preg_match('/<button[^>]*class="add-dashed"[^>]*>(.*?)<\/button>/s', $html, $button);

        $this->assertNotEmpty($button, 'The add-ingredient button is not on the page.');
        $this->assertStringContainsString(__('library.add_ingredient'), $button[1]);
        $this->assertSame(1, substr_count($button[1], '+'));
    }

    public function test_a_new_recipe_form_shows_no_total_because_there_is_nothing_to_total(): void
    {
        $this->get(route('library.recipe.create'))
            ->assertOk()
            ->assertDontSee('total-bar');
    }

    public function test_a_recipe_that_would_reference_itself_is_refused(): void
    {
        $recipe = FoodItem::factory()->recipe()->create(['name' => 'Loop']);

        $this->patch(route('library.recipe.update', $recipe), [
            'name' => 'Loop',
            'ingredients' => [['item_id' => $recipe->id, 'grams' => 100]],
        ])->assertSessionHasErrors('ingredients');

        $this->assertSame(0, RecipeIngredient::query()->count());
    }
}
