<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * One candidate answer for a food name: a human-readable description, the
 * profile behind it, and a stable external identifier where the source has one
 * (USDA fdcId, Open Food Facts barcode). The source is carried by the profile,
 * so a match is always self-describing when shown in a list for the user to
 * choose from.
 *
 * {@see $matchedVia} explains a loose personal-library match: the stored name or
 * alias that a recognised term matched, when it was not the item's own name. It
 * is shown alongside the full name, never in place of it, so the user can tell
 * which stored variant surfaced.
 */
final readonly class NutrientMatch
{
    public function __construct(
        public string $description,
        public NutrientProfile $profile,
        public ?string $externalId = null,
        public ?string $matchedVia = null,
    ) {}

    public function source(): NutrientSource
    {
        return $this->profile->source;
    }
}
