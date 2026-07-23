<?php

declare(strict_types=1);

namespace Tests\Feature;

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
}
