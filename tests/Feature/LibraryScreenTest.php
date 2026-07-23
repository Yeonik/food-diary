<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\FoodItem;
use App\Models\RecipeIngredient;
use App\Nutrition\ProfileOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The library index with items actually in it. Rendering it empty exercises none
 * of the per-item markup — the provenance badge, the edit link, the delete form
 * — so an empty-library check would pass while the populated screen broke.
 */
class LibraryScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_index_lists_a_direct_item_of_every_origin(): void
    {
        foreach (ProfileOrigin::cases() as $i => $origin) {
            FoodItem::factory()->create([
                'name' => 'Item '.$i,
                'origin' => $origin->value,
            ]);
        }

        $response = $this->get(route('library.index'));

        $response->assertOk();
        foreach (ProfileOrigin::cases() as $i => $origin) {
            $response->assertSee('Item '.$i);
            $response->assertSee(__('source.'.$origin->value));
        }
    }

    public function test_the_index_lists_a_recipe_beside_the_direct_items(): void
    {
        $ingredient = FoodItem::factory()->create(['name' => 'Buckwheat']);
        $recipe = FoodItem::factory()->recipe()->create(['name' => 'Buckwheat with mushrooms']);
        RecipeIngredient::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
        ]);

        $this->get(route('library.index'))
            ->assertOk()
            ->assertSee('Buckwheat with mushrooms')
            ->assertSee(__('library.recipe'));
    }

    /**
     * A recipe stores no per-100 g figures; the list computes them from the
     * ingredients, so the row reads like every other one.
     */
    public function test_a_recipe_row_carries_the_figure_computed_from_its_ingredients(): void
    {
        $ingredient = FoodItem::factory()->create([
            'name' => 'Buckwheat',
            'kcal_per_100g' => 110,
            'protein_g_per_100g' => 4,
            'fat_g_per_100g' => 1.1,
            'carbs_g_per_100g' => 21,
        ]);
        $recipe = FoodItem::factory()->recipe()->create(['name' => 'Plain buckwheat']);
        RecipeIngredient::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'grams' => 200,
        ]);

        // One ingredient, so the recipe's per-100 g figures are the ingredient's.
        $this->get(route('library.index'))
            ->assertOk()
            ->assertSee('110 '.__('nutrition.kcal'), false);
    }

    /**
     * The library never fetches Open Food Facts pictures: the list is opened
     * often, and each fetch would tell them what is in it.
     */
    public function test_the_index_loads_no_remote_images(): void
    {
        FoodItem::factory()->create(['name' => 'Item', 'origin' => ProfileOrigin::OpenFoodFacts->value]);

        $html = (string) $this->get(route('library.index'))->getContent();

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('openfoodfacts.org', $html);
    }

    public function test_a_second_page_is_reachable_and_reads_in_the_chosen_locale(): void
    {
        FoodItem::factory()->count(31)->create();

        $this->withCookie(SetLocale::COOKIE, 'ru')->get(route('library.index'))
            ->assertOk()
            ->assertSee('Страница 1 из 2')
            ->assertSee('page=2');

        $this->withCookie(SetLocale::COOKIE, 'ru')->get(route('library.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Страница 2 из 2');
    }
}
