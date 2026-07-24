<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The cooked weight as it sits on the model, before anything computes with it.
 *
 * The behaviour that reads it — refusing a number, marking a recipe incomplete —
 * is in RecipeCalculatorTest and the interface tests. This class only pins the
 * column and the one question the model answers about it.
 */
class RecipeCookedWeightColumnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    public function test_a_recipe_with_a_cooked_weight_is_complete(): void
    {
        $recipe = FoodItem::factory()->recipe(cookedWeightG: 250.0)->create();

        $this->assertSame(250.0, $recipe->cooked_weight_g);
        $this->assertFalse($recipe->needsCookedWeight());
    }

    public function test_a_recipe_without_a_cooked_weight_needs_one(): void
    {
        // The state every recipe defined before this column existed arrives in.
        $recipe = FoodItem::factory()->recipe(cookedWeightG: null)->create();

        $this->assertNull($recipe->cooked_weight_g);
        $this->assertTrue($recipe->needsCookedWeight());
    }

    public function test_a_direct_item_never_needs_a_cooked_weight(): void
    {
        // The question is only meaningful for recipes; a direct item carries its
        // own numbers and is not divided by anything.
        $direct = FoodItem::factory()->create();

        $this->assertNull($direct->cooked_weight_g);
        $this->assertFalse($direct->needsCookedWeight());
    }

    public function test_the_column_is_a_nullable_double_so_existing_recipes_upgrade_cleanly(): void
    {
        // Additive: the recipes that already exist come through with null here,
        // which is the "needs a cooked weight" state, not a made-up number.
        $this->assertTrue(Schema::hasColumn('food_items', 'cooked_weight_g'));

        $column = collect(Schema::getColumns('food_items'))->firstWhere('name', 'cooked_weight_g');

        $this->assertIsArray($column);
        $this->assertTrue($column['nullable'], 'cooked_weight_g must be nullable.');
        $this->assertNull($column['default'], 'cooked_weight_g must have no default.');
    }
}
