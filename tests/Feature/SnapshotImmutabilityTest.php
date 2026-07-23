<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Models\RecipeIngredient;
use App\Nutrition\MealType;
use App\Nutrition\RecipeCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A MealEntry is a snapshot. Correcting the source item later — a direct food, a
 * recipe, or merging a duplicate away — must change future entries only; last
 * month's totals must not move.
 */
class SnapshotImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    public function test_correcting_a_direct_item_does_not_change_a_past_entry(): void
    {
        $chicken = FoodItem::factory()->direct(kcal: 165, protein: 31, fat: 3.6, carbs: 0)
            ->create(['name' => 'Chicken breast']);

        // Log 200 g at the numbers as they were: 330 kcal.
        $entry = MealEntry::fromPortion(
            $chicken->storedProfile()->forGrams(200),
            'Chicken breast',
            MealType::Lunch,
            CarbonImmutable::parse('2026-06-01 13:00'),
            $chicken->id,
        );
        $entry->save();

        // Later correction: the item is now recorded as more energy-dense.
        $chicken->update(['kcal_per_100g' => 200]);

        $entry->refresh();
        $this->assertSame(330.0, $entry->kcal); // not 400
    }

    public function test_editing_a_recipe_does_not_change_a_past_entry(): void
    {
        $rice = FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)->create();
        $chicken = FoodItem::factory()->direct(kcal: 165, protein: 31, fat: 3.6, carbs: 0)->create();

        $recipe = FoodItem::factory()->recipe()->create(['name' => 'Plov']);
        RecipeIngredient::factory()->create(['recipe_id' => $recipe->id, 'ingredient_id' => $rice->id, 'grams' => 200]);
        $chickenLine = RecipeIngredient::factory()->create(['recipe_id' => $recipe->id, 'ingredient_id' => $chicken->id, 'grams' => 100]);

        $calculator = new RecipeCalculator;

        // Log 300 g of the recipe as it stands today.
        $entry = MealEntry::fromPortion(
            $calculator->profileFor($recipe)->forGrams(300),
            'Plov',
            MealType::Dinner,
            CarbonImmutable::parse('2026-06-01 19:00'),
            $recipe->id,
        );
        $entry->save();
        $loggedKcal = $entry->kcal;

        // Edit the recipe afterwards: double the chicken. Recomputing now yields
        // a different profile...
        $chickenLine->update(['grams' => 200]);
        $recipe->load('ingredients');
        $this->assertNotEqualsWithDelta($loggedKcal, $calculator->profileFor($recipe)->forGrams(300)->kcal, 0.01);

        // ...but the past entry is unmoved.
        $entry->refresh();
        $this->assertSame($loggedKcal, $entry->kcal);
    }

    public function test_merging_a_duplicate_does_not_change_a_past_entry(): void
    {
        // A duplicate and the survivor it will be merged into, with deliberately
        // different numbers so any accidental recomputation would show up.
        $duplicate = FoodItem::factory()->direct(kcal: 165, protein: 31, fat: 3.6, carbs: 0)
            ->create(['name' => 'Chicken breast (dup)']);
        $survivor = FoodItem::factory()->direct(kcal: 250, protein: 20, fat: 18, carbs: 0)
            ->create(['name' => 'Chicken breast']);

        // Log 200 g against the duplicate: 330 kcal, snapshotted.
        $entry = MealEntry::fromPortion(
            $duplicate->storedProfile()->forGrams(200),
            'Chicken breast',
            MealType::Lunch,
            CarbonImmutable::parse('2026-05-10 13:00'),
            $duplicate->id,
        );
        $entry->save();

        // Merge the duplicate into the survivor through the real route.
        $this->post(route('library.merge', $duplicate), ['target_id' => $survivor->id])
            ->assertRedirect(route('library.index'));

        $entry->refresh();

        // Numbers are untouched — not recomputed from the survivor's profile.
        $this->assertSame(330.0, $entry->kcal);
        $this->assertSame(62.0, $entry->protein_g);

        // Only the provenance link moved; the duplicate is gone.
        $this->assertSame($survivor->id, $entry->food_item_id);
        $this->assertModelMissing($duplicate);
    }
}
