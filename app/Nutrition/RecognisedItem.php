<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * One dish the recogniser named in a photo. The model is trusted to do exactly
 * this much: give a name, a rough portion, and how sure it is. It is not
 * trusted for nutrient numbers.
 *
 * The optional {@see $estimatedProfile} carries the model's own macro guess. It
 * is used only as the last-resort tier when no real source can answer, and it
 * is always tagged {@see NutrientSource::Estimated} so it can never be mistaken
 * for a verified value.
 */
final readonly class RecognisedItem
{
    public function __construct(
        public string $name,
        public float $estimatedGrams,
        public float $confidence,
        public ?NutrientProfile $estimatedProfile = null,
    ) {}
}
