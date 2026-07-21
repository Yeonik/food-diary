<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * Where a direct library item's numbers originally came from. Provenance shown
 * to the user ("originally from USDA"), kept separate from {@see NutrientSource}
 * because a stored library item's resolution tier is always the personal
 * library — this only records how it first got there.
 */
enum ProfileOrigin: string
{
    case Manual = 'manual';
    case Usda = 'usda';
    case OpenFoodFacts = 'open_food_facts';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Entered by hand',
            self::Usda => 'From USDA',
            self::OpenFoodFacts => 'From Open Food Facts',
        };
    }
}
