<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\FoodItem;
use App\Models\RecipeIngredient;
use App\Nutrition\Exceptions\RecipeCycleException;
use App\Nutrition\Exceptions\RecipeIncompleteException;
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

    public function test_a_recipe_profile_is_per_100g_of_the_cooked_dish(): void
    {
        $rice = FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)->create();
        $chicken = FoodItem::factory()->direct(kcal: 165, protein: 31, fat: 3.6, carbs: 0)->create();

        // 200 g rice + 100 g chicken = 300 g of raw ingredients, but the dish
        // absorbs water and weighs 250 g cooked. The divisor is 250, not 300 —
        // that difference is the whole point of this change.
        $recipe = FoodItem::factory()->recipe(cookedWeightG: 250)->create();
        RecipeIngredient::factory()->create(['recipe_id' => $recipe->id, 'ingredient_id' => $rice->id, 'grams' => 200]);
        RecipeIngredient::factory()->create(['recipe_id' => $recipe->id, 'ingredient_id' => $chicken->id, 'grams' => 100]);

        $profile = (new RecipeCalculator)->profileFor($recipe);

        // The batch totals are unchanged — 200 g rice and 100 g chicken:
        //   kcal    = 130*2 + 165*1 = 425
        //   protein = 2.7*2 + 31    = 36.4
        //   fat     = 0.3*2 + 3.6   = 4.2
        //   carbs   = 28*2 + 0      = 56
        // Per 100 g of the 250 g COOKED dish (× 100/250 = × 0.4):
        //   kcal    = 425  * 0.4 = 170
        //   protein = 36.4 * 0.4 =  14.56
        //   fat     = 4.2  * 0.4 =   1.68
        //   carbs   = 56   * 0.4 =  22.4
        $this->assertEqualsWithDelta(170.0, $profile->kcal, 0.001);
        $this->assertEqualsWithDelta(14.56, $profile->proteinG, 0.001);
        $this->assertEqualsWithDelta(1.68, $profile->fatG, 0.001);
        $this->assertEqualsWithDelta(22.4, $profile->carbsG, 0.001);
        $this->assertSame(NutrientSource::PersonalLibrary, $profile->source);
    }

    public function test_the_cooked_weight_is_what_divides_not_the_raw_sum(): void
    {
        // Same ingredients, two cooked weights: a dish that shed water to 200 g
        // is more energy-dense per 100 g than the same batch left at 400 g. If
        // the divisor were still the raw sum, these would be identical — so this
        // is the regression guard for the bug itself.
        $beef = FoodItem::factory()->direct(kcal: 250, protein: 26, fat: 15, carbs: 0)->create();

        $dense = FoodItem::factory()->recipe(cookedWeightG: 200)->create();
        RecipeIngredient::factory()->create(['recipe_id' => $dense->id, 'ingredient_id' => $beef->id, 'grams' => 400]);

        $loose = FoodItem::factory()->recipe(cookedWeightG: 400)->create();
        RecipeIngredient::factory()->create(['recipe_id' => $loose->id, 'ingredient_id' => $beef->id, 'grams' => 400]);

        $denseProfile = (new RecipeCalculator)->profileFor($dense);
        $looseProfile = (new RecipeCalculator)->profileFor($loose);

        // 400 g beef = 1000 kcal. Over 200 g cooked that is 500/100 g; over
        // 400 g cooked, 250/100 g. Exactly double, because the divisor differs.
        $this->assertEqualsWithDelta(500.0, $denseProfile->kcal, 0.001);
        $this->assertEqualsWithDelta(250.0, $looseProfile->kcal, 0.001);
    }

    public function test_a_recipe_with_no_cooked_weight_yields_no_number(): void
    {
        $rice = FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)->create();

        // A recipe from before the cooked weight existed, or one whose owner has
        // not supplied it. It must refuse, not fall back to the raw sum.
        $recipe = FoodItem::factory()->recipe(cookedWeightG: null)->create();
        RecipeIngredient::factory()->create(['recipe_id' => $recipe->id, 'ingredient_id' => $rice->id, 'grams' => 200]);

        try {
            (new RecipeCalculator)->profileFor($recipe);
            $this->fail('An incomplete recipe returned a profile instead of refusing.');
        } catch (RecipeIncompleteException $e) {
            $this->assertSame($recipe->id, $e->offendingItemId);
        }
    }

    public function test_a_recipe_profile_matches_hand_arithmetic_through_nesting(): void
    {
        $rice = FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)->create();
        $chicken = FoodItem::factory()->direct(kcal: 165, protein: 31, fat: 3.6, carbs: 0)->create();

        // Inner: 200 g rice + 100 g chicken, cooked to 250 g — the profile from
        // the first test above.
        $inner = FoodItem::factory()->recipe(cookedWeightG: 250)->create(['name' => 'Rice and chicken']);
        RecipeIngredient::factory()->create(['recipe_id' => $inner->id, 'ingredient_id' => $rice->id, 'grams' => 200]);
        RecipeIngredient::factory()->create(['recipe_id' => $inner->id, 'ingredient_id' => $chicken->id, 'grams' => 100]);

        $innerProfile = (new RecipeCalculator)->profileFor($inner);

        // Outer: 250 g of the inner dish (a whole batch of it), cooked weight
        // 250 g — nothing added, nothing lost. Its per-100 g profile must equal
        // the inner one exactly: the inner profile is per 100 g cooked, and
        // 250 g of it over a 250 g cooked weight is the same density.
        $outer = FoodItem::factory()->recipe(cookedWeightG: 250)->create(['name' => 'Bowl']);
        RecipeIngredient::factory()->create(['recipe_id' => $outer->id, 'ingredient_id' => $inner->id, 'grams' => 250]);

        $outerProfile = (new RecipeCalculator)->profileFor($outer);

        $this->assertEqualsWithDelta($innerProfile->kcal, $outerProfile->kcal, 0.0001);
        $this->assertEqualsWithDelta($innerProfile->proteinG, $outerProfile->proteinG, 0.0001);
        $this->assertEqualsWithDelta($innerProfile->carbsG, $outerProfile->carbsG, 0.0001);
    }

    public function test_an_incomplete_recipe_nested_inside_another_names_itself(): void
    {
        $rice = FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)->create();

        // The inner recipe has no cooked weight; the outer one does.
        $inner = FoodItem::factory()->recipe(cookedWeightG: null)->create(['name' => 'unfinished base']);
        RecipeIngredient::factory()->create(['recipe_id' => $inner->id, 'ingredient_id' => $rice->id, 'grams' => 200]);

        $outer = FoodItem::factory()->recipe(cookedWeightG: 300)->create(['name' => 'built on it']);
        RecipeIngredient::factory()->create(['recipe_id' => $outer->id, 'ingredient_id' => $inner->id, 'grams' => 150]);

        try {
            (new RecipeCalculator)->profileFor($outer);
            $this->fail('A recipe built on an incomplete one returned a profile.');
        } catch (RecipeIncompleteException $e) {
            // The recipe actually missing its weight is named, not the one that
            // referred to it — so the interface can point at the right fix.
            $this->assertSame($inner->id, $e->offendingItemId);
        }
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
