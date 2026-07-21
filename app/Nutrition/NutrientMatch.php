<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * One candidate answer for a food name: a human-readable description, the
 * profile behind it, and a stable external identifier where the source has one
 * (USDA fdcId, Open Food Facts barcode). The source is carried by the profile,
 * so a match is always self-describing when shown in a list for the user to
 * choose from.
 */
final readonly class NutrientMatch
{
    public function __construct(
        public string $description,
        public NutrientProfile $profile,
        public ?string $externalId = null,
    ) {}

    public function source(): NutrientSource
    {
        return $this->profile->source;
    }
}
