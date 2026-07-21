<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * A personal-library item is one of two things: a direct nutrient profile
 * (values per 100 g) or a recipe (a list of other items with weights, from
 * which the profile is computed). Everything downstream sees a NutrientProfile
 * and does not care which kind it was.
 */
enum FoodItemKind: string
{
    case Direct = 'direct';
    case Recipe = 'recipe';
}
