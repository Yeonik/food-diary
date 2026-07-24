<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Models\RecipeIngredient;
use App\Nutrition\MealType;
use App\Nutrition\NutrientSource;
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

    public function test_changing_a_recipes_cooked_weight_does_not_change_a_past_entry(): void
    {
        // The v0.5.0 case specifically: the divisor itself is corrected. An
        // entry logged when the recipe read one way must not move when the
        // cooked weight — and so every per-100 g figure — is changed afterwards.
        $rice = FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)->create();

        $recipe = FoodItem::factory()->recipe(cookedWeightG: 400)->create(['name' => 'Plov']);
        RecipeIngredient::factory()->create(['recipe_id' => $recipe->id, 'ingredient_id' => $rice->id, 'grams' => 300]);

        $calculator = new RecipeCalculator;

        // Log 200 g of the dish as it stands, at a 400 g cooked weight.
        $entry = MealEntry::fromPortion(
            $calculator->profileFor($recipe)->forGrams(200),
            'Plov',
            MealType::Dinner,
            CarbonImmutable::parse('2026-06-01 19:00'),
            $recipe->id,
        );
        $entry->save();
        $loggedKcal = $entry->kcal;

        // The owner later corrects the cooked weight to 250 g — a denser dish,
        // so recomputing today gives a different number...
        $recipe->update(['cooked_weight_g' => 250]);
        $this->assertNotEqualsWithDelta(
            $loggedKcal,
            $calculator->profileFor($recipe->fresh())->forGrams(200)->kcal,
            0.01,
        );

        // ...but the entry logged in June holds its snapshot.
        $entry->refresh();
        $this->assertSame($loggedKcal, $entry->kcal);
    }

    public function test_completing_an_old_recipe_does_not_move_entries_logged_before_it(): void
    {
        // The migration case end to end. Before this release a recipe had no
        // cooked weight and its numbers came from the raw sum; whatever was
        // logged then is a snapshot on the entry. Supplying the cooked weight
        // now recomputes the recipe going forward and must not reach back.
        $rice = FoodItem::factory()->direct(kcal: 130, protein: 2.7, fat: 0.3, carbs: 28)->create();

        $recipe = FoodItem::factory()->recipe(cookedWeightG: null)->create(['name' => 'Old borsch']);
        RecipeIngredient::factory()->create(['recipe_id' => $recipe->id, 'ingredient_id' => $rice->id, 'grams' => 300]);

        // An entry that was logged against this recipe before the field existed.
        // Its numbers are whatever they were; here, a plain snapshot.
        $entry = MealEntry::query()->create([
            'name' => 'Old borsch',
            'meal' => MealType::Dinner->value,
            'logged_at' => CarbonImmutable::parse('2026-05-01 19:00'),
            'grams' => 300,
            'kcal' => 390,
            'protein_g' => 8.1,
            'fat_g' => 0.9,
            'carbs_g' => 84,
            'source' => NutrientSource::PersonalLibrary->value,
            'food_item_id' => $recipe->id,
        ]);

        // The owner supplies the cooked weight through the ordinary form.
        $this->patch(route('library.recipe.update', $recipe), [
            'name' => 'Old borsch',
            'cooked_weight_g' => 250,
            'ingredients' => [['item_id' => $rice->id, 'grams' => 300]],
        ])->assertRedirect(route('library.index'));

        // The recipe is now complete and computes going forward, but May's entry
        // is exactly as it was logged.
        $this->assertFalse($recipe->fresh()?->needsCookedWeight());
        $entry->refresh();
        $this->assertSame(390.0, $entry->kcal);
        $this->assertSame(84.0, $entry->carbs_g);
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

    public function test_deleting_an_item_does_not_change_a_past_entry(): void
    {
        $chicken = FoodItem::factory()->direct(kcal: 165, protein: 31, fat: 3.6, carbs: 0)
            ->create(['name' => 'Chicken breast']);

        $entry = MealEntry::fromPortion(
            $chicken->storedProfile()->forGrams(200),
            'Chicken breast',
            MealType::Lunch,
            CarbonImmutable::parse('2026-05-10 13:00'),
            $chicken->id,
        );
        $entry->save();

        // Removing something from the library does not edit history. The
        // database used to unlink the entry itself, with ON DELETE SET NULL;
        // now that the link is half of a key naming the owner, the delete route
        // has to unlink first — and this is what says it still does.
        $this->delete(route('library.destroy', $chicken))
            ->assertRedirect(route('library.index'));

        $entry->refresh();

        $this->assertSame(330.0, $entry->kcal);
        $this->assertSame(62.0, $entry->protein_g);
        $this->assertNull($entry->food_item_id, 'The entry still points at an item that is gone.');
        $this->assertModelMissing($chicken);
    }
}
