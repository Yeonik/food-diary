<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * One dish the recogniser named in a photo. The model is trusted to do exactly
 * this much: give a name, a rough portion, and how sure it is. It is not
 * trusted for nutrient numbers.
 *
 * {@see $name} is the English name — what USDA indexes. {@see $nativeName} is
 * the name as printed on the packaging in its own language (Russian, say), when
 * the model could read one; it is what the user is shown and what Open Food
 * Facts is searched with in addition to the English name.
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
        public ?string $nativeName = null,
        public ?NutrientProfile $estimatedProfile = null,
    ) {}

    /**
     * The two names as search terms — English for USDA, both for Open Food Facts.
     */
    public function searchTerms(): SearchTerms
    {
        return new SearchTerms($this->name, $this->nativeName);
    }
}
