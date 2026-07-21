<?php

declare(strict_types=1);

namespace App\Nutrition\Sources;

use App\Models\FoodItem;
use App\Nutrition\Contracts\NutritionSource;
use App\Nutrition\NutrientMatch;
use App\Nutrition\NutrientSource;
use App\Nutrition\RecipeCalculator;

/**
 * Tier one, always consulted first: foods the user has already confirmed,
 * corrected, or defined as recipes. These are trusted above any external
 * database because the user verified them.
 *
 * A recipe's profile is computed on demand from its ingredients, so it behaves
 * like any direct item once matched.
 */
class PersonalLibrarySource implements NutritionSource
{
    public function __construct(private readonly RecipeCalculator $calculator) {}

    /**
     * @return list<NutrientMatch>
     */
    public function search(string $name): array
    {
        // Parameterised LIKE — user wildcards only broaden the search, they
        // never reach the SQL as anything but a bound value.
        $items = FoodItem::query()
            ->where('name', 'like', '%'.$name.'%')
            ->orderBy('name')
            ->limit(25)
            ->get();

        $matches = [];

        foreach ($items as $item) {
            $profile = $item->isRecipe()
                ? $this->calculator->profileFor($item)
                : $item->storedProfile();

            $matches[] = new NutrientMatch(
                description: $item->name,
                profile: $profile,
                externalId: (string) $item->id,
            );
        }

        return $matches;
    }

    public function source(): NutrientSource
    {
        return NutrientSource::PersonalLibrary;
    }
}
