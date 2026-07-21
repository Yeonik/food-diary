<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * Which source produced a nutrient value. This is recorded on every match and
 * snapshotted onto a logged entry, because the whole point of the project is
 * that the user can see where a number came from — and that the model is never
 * one of the trusted sources.
 */
enum NutrientSource: string
{
    case PersonalLibrary = 'personal_library';
    case Usda = 'usda';
    case OpenFoodFacts = 'open_food_facts';

    // The model's own guess. Visually distinct, never counted as verified.
    case Estimated = 'estimated';

    public function label(): string
    {
        return match ($this) {
            self::PersonalLibrary => 'Personal library',
            self::Usda => 'USDA FoodData Central',
            self::OpenFoodFacts => 'Open Food Facts',
            self::Estimated => 'Estimated (unverified)',
        };
    }

    /**
     * A value is verified when it came from a real source rather than the
     * model's estimate. Totals and the diary treat estimates differently.
     */
    public function isVerified(): bool
    {
        return $this !== self::Estimated;
    }
}
