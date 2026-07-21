<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * Absolute nutrient totals for one portion — the per-100 g profile scaled to a
 * real weight. These are the numbers copied verbatim onto a MealEntry, where
 * they must stay frozen even if the underlying library item changes later.
 */
final readonly class PortionTotals
{
    public function __construct(
        public float $kcal,
        public float $proteinG,
        public float $fatG,
        public float $carbsG,
        public float $grams,
        public NutrientSource $source,
    ) {}
}
