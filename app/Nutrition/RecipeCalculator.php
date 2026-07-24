<?php

declare(strict_types=1);

namespace App\Nutrition;

use App\Models\FoodItem;
use App\Nutrition\Exceptions\RecipeCycleException;
use App\Nutrition\Exceptions\RecipeIncompleteException;
use RuntimeException;

/**
 * Turns a recipe — a list of ingredient items with weights — into a per-100 g
 * NutrientProfile, so that downstream code can treat a home-cooked dish exactly
 * like any direct item.
 *
 * The divisor is the weight of the cooked dish, which the person supplies: the
 * numbers are per 100 g of what they actually eat, not per 100 g of raw
 * ingredients. A recipe with no cooked weight has no honest divisor, so it is
 * refused — a `RecipeIncompleteException` — rather than divided by the raw sum.
 *
 * Ingredients may themselves be recipes; the computation recurses. Two things
 * make it stop rather than return a wrong answer: a recipe that references
 * itself is a cycle, and a recipe (this one or any nested below it) with no
 * cooked weight is incomplete. Either is thrown, not swallowed.
 */
class RecipeCalculator
{
    /**
     * @throws RecipeCycleException
     * @throws RecipeIncompleteException
     */
    public function profileFor(FoodItem $item): NutrientProfile
    {
        return $this->compute($item, []);
    }

    /**
     * @param  list<int>  $ancestry  recipe ids currently being resolved above this one
     *
     * @throws RecipeCycleException
     * @throws RecipeIncompleteException
     */
    private function compute(FoodItem $item, array $ancestry): NutrientProfile
    {
        // A direct item already carries its numbers — the base case.
        if (! $item->isRecipe()) {
            return $item->storedProfile();
        }

        // Seeing this recipe again while still resolving it means a cycle.
        if (in_array($item->id, $ancestry, true)) {
            throw new RecipeCycleException($item->id, $ancestry);
        }

        // No cooked weight, no divisor. Checked before the ingredients are
        // summed so a recipe nested inside another stops the whole computation
        // here, naming itself rather than the recipe that referred to it.
        if ($item->cooked_weight_g === null || $item->cooked_weight_g <= 0.0) {
            throw new RecipeIncompleteException($item->id);
        }

        $ancestry[] = $item->id;

        $item->loadMissing('ingredients.ingredient');

        $totalGrams = 0.0;
        $kcal = 0.0;
        $proteinG = 0.0;
        $fatG = 0.0;
        $carbsG = 0.0;

        foreach ($item->ingredients as $line) {
            // Recurse with this recipe now on the ancestry path.
            $portion = $this->compute($line->ingredient, $ancestry)->forGrams($line->grams);

            $kcal += $portion->kcal;
            $proteinG += $portion->proteinG;
            $fatG += $portion->fatG;
            $carbsG += $portion->carbsG;
            $totalGrams += $line->grams;
        }

        if ($totalGrams <= 0.0) {
            throw new RuntimeException("Recipe {$item->id} has no ingredient mass to divide by.");
        }

        // Re-express the batch totals per 100 g of the COOKED dish. The raw
        // ingredient sum above is not the divisor — it was, and that was the
        // bug: the person weighs the cooked dish, so its weight is what 100 g
        // has to be 100 g of. Guaranteed non-null and positive by the check at
        // the top of this method. Source is the personal library: a recipe is a
        // library item the user defined and verified, cooked weight included.
        $per100 = 100.0 / $item->cooked_weight_g;

        return new NutrientProfile(
            kcal: $kcal * $per100,
            proteinG: $proteinG * $per100,
            fatG: $fatG * $per100,
            carbsG: $carbsG * $per100,
            source: NutrientSource::PersonalLibrary,
        );
    }
}
