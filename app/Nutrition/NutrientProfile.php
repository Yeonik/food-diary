<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * Nutrient values per 100 g, plus the source that vouches for them. Immutable:
 * once built it is never mutated, which is what lets a logged entry keep a
 * faithful snapshot of the numbers as they were at the time.
 *
 * Everything downstream — logging, totals, the diary — consumes this type and
 * does not care whether it came from a database row, a recipe computation, or
 * the model's estimate.
 */
final readonly class NutrientProfile
{
    public function __construct(
        public float $kcal,
        public float $proteinG,
        public float $fatG,
        public float $carbsG,
        public NutrientSource $source,
    ) {}

    /**
     * Absolute totals for a given portion mass. This is what gets snapshotted
     * onto a MealEntry — the per-100 g values scaled to what was actually eaten.
     */
    public function forGrams(float $grams): PortionTotals
    {
        $factor = $grams / 100.0;

        return new PortionTotals(
            kcal: $this->kcal * $factor,
            proteinG: $this->proteinG * $factor,
            fatG: $this->fatG * $factor,
            carbsG: $this->carbsG * $factor,
            grams: $grams,
            source: $this->source,
        );
    }

    public function isVerified(): bool
    {
        return $this->source->isVerified();
    }
}
