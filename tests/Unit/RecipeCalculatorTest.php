<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\FoodItem;
use App\Models\RecipeIngredient;
use App\Nutrition\Exceptions\RecipeCycleException;
use App\Nutrition\NutrientSource;
use App\Nutrition\RecipeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A recipe reads its own ingredients back, and that read is scoped now.
        $this->signIn();
    }

    public function test_a_recipe_profile_matches_hand_arithmetic(): void
    {
        $rice = FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)->create();
        $chicken = FoodItem::factory()->direct(kcal: 165, protein: 31, fat: 3.6, carbs: 0)->create();

        // 200 g rice + 100 g chicken = 300 g batch.
        $recipe = FoodItem::factory()->recipe()->create();
        RecipeIngredient::factory()->create(['recipe_id' => $recipe->id, 'ingredient_id' => $rice->id, 'grams' => 200]);
        RecipeIngredient::factory()->create(['recipe_id' => $recipe->id, 'ingredient_id' => $chicken->id, 'grams' => 100]);

        $profile = (new RecipeCalculator)->profileFor($recipe);

        // Hand arithmetic, per 100 g of the 300 g batch:
        //   kcal    = (130*2 + 165*1) / 3 = 141.6667
        //   protein = (2.7*2 + 31)   / 3 =  12.1333
        //   fat     = (0.3*2 + 3.6)  / 3 =   1.4
        //   carbs   = (28*2 + 0)     / 3 =  18.6667
        $this->assertEqualsWithDelta(141.6667, $profile->kcal, 0.001);
        $this->assertEqualsWithDelta(12.1333, $profile->proteinG, 0.001);
        $this->assertEqualsWithDelta(1.4, $profile->fatG, 0.001);
        $this->assertEqualsWithDelta(18.6667, $profile->carbsG, 0.001);
        $this->assertSame(NutrientSource::PersonalLibrary, $profile->source);
    }

    public function test_a_recipe_referencing_a_recipe_resolves(): void
    {
        $rice = FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)->create();
        $chicken = FoodItem::factory()->direct(kcal: 165, protein: 31, fat: 3.6, carbs: 0)->create();

        $inner = FoodItem::factory()->recipe()->create(['name' => 'Rice and chicken']);
        RecipeIngredient::factory()->create(['recipe_id' => $inner->id, 'ingredient_id' => $rice->id, 'grams' => 200]);
        RecipeIngredient::factory()->create(['recipe_id' => $inner->id, 'ingredient_id' => $chicken->id, 'grams' => 100]);

        $innerProfile = (new RecipeCalculator)->profileFor($inner);

        // Outer recipe: 300 g of the inner recipe, nothing else. Its per-100 g
        // profile must therefore equal the inner recipe's exactly.
        $outer = FoodItem::factory()->recipe()->create(['name' => 'Bowl']);
        RecipeIngredient::factory()->create(['recipe_id' => $outer->id, 'ingredient_id' => $inner->id, 'grams' => 300]);

        $outerProfile = (new RecipeCalculator)->profileFor($outer);

        $this->assertEqualsWithDelta($innerProfile->kcal, $outerProfile->kcal, 0.0001);
        $this->assertEqualsWithDelta($innerProfile->proteinG, $outerProfile->proteinG, 0.0001);
        $this->assertEqualsWithDelta($innerProfile->carbsG, $outerProfile->carbsG, 0.0001);
    }

    public function test_a_cycle_is_rejected_rather_than_looping(): void
    {
        $a = FoodItem::factory()->recipe()->create(['name' => 'A']);
        $b = FoodItem::factory()->recipe()->create(['name' => 'B']);

        // A contains B, B contains A — a cycle.
        RecipeIngredient::factory()->create(['recipe_id' => $a->id, 'ingredient_id' => $b->id, 'grams' => 100]);
        RecipeIngredient::factory()->create(['recipe_id' => $b->id, 'ingredient_id' => $a->id, 'grams' => 100]);

        $this->expectException(RecipeCycleException::class);

        (new RecipeCalculator)->profileFor($a);
    }
}
