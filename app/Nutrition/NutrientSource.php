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

    // A value the user typed themselves — e.g. read off a package label. The
    // person vouches for it and the model invented nothing, so it is verified,
    // not an estimate. The label says only what the app can attest (a human
    // entered it), never that it came from a label — which it cannot verify.
    case Manual = 'manual';

    // The model's own guess. Visually distinct, never counted as verified.
    case Estimated = 'estimated';

    public function label(): string
    {
        return match ($this) {
            self::PersonalLibrary => 'Personal library',
            self::Usda => 'USDA FoodData Central',
            self::OpenFoodFacts => 'Open Food Facts',
            self::Manual => 'Entered by hand',
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
