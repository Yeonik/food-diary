<?php

declare(strict_types=1);

namespace App\Nutrition;

use App\Models\FoodItem;
use App\Nutrition\Exceptions\RecipeCycleException;
use RuntimeException;

/**
 * Turns a recipe — a list of ingredient items with weights — into a per-100 g
 * NutrientProfile, so that downstream code can treat a home-cooked dish exactly
 * like any direct item.
 *
 * Ingredients may themselves be recipes; the computation recurses. A recipe
 * that references itself (directly or through a chain) is rejected rather than
 * followed into an infinite loop.
 */
class RecipeCalculator
{
    /**
     * @throws RecipeCycleException
     */
    public function profileFor(FoodItem $item): NutrientProfile
    {
        return $this->compute($item, []);
    }

    /**
     * @param  list<int>  $ancestry  recipe ids currently being resolved above this one
     *
     * @throws RecipeCycleException
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

        // Re-express the batch totals per 100 g. Source is the personal library:
        // a recipe is a library item the user defined and verified.
        $per100 = 100.0 / $totalGrams;

        return new NutrientProfile(
            kcal: $kcal * $per100,
            proteinG: $proteinG * $per100,
            fatG: $fatG * $per100,
            carbsG: $carbsG * $per100,
            source: NutrientSource::PersonalLibrary,
        );
    }
}
