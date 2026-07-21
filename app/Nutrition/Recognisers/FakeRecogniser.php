<?php

declare(strict_types=1);

namespace App\Nutrition\Recognisers;

use App\Nutrition\Contracts\FoodRecogniser;
use App\Nutrition\NutrientProfile;
use App\Nutrition\NutrientSource;
use App\Nutrition\PreparedPhoto;
use App\Nutrition\RecognisedItem;

/**
 * The recogniser CI runs against. It returns canned dishes without touching the
 * network, so the whole photo → confirm → logged flow can be tested with no API
 * key. Each canned item also carries a model-style estimate, tagged
 * {@see NutrientSource::Estimated}, so the unresolved (tier 3) path is exercised
 * too.
 *
 * Tests may pass their own items; otherwise a sensible default meal is returned.
 */
class FakeRecogniser implements FoodRecogniser
{
    /**
     * @param  list<RecognisedItem>  $items
     */
    public function __construct(private readonly array $items = []) {}

    /**
     * @return list<RecognisedItem>
     */
    public function recognise(PreparedPhoto $photo): array
    {
        return $this->items !== [] ? $this->items : self::defaultMeal();
    }

    /**
     * @return list<RecognisedItem>
     */
    public static function defaultMeal(): array
    {
        return [
            new RecognisedItem(
                name: 'Grilled chicken breast',
                estimatedGrams: 180.0,
                confidence: 0.92,
                estimatedProfile: new NutrientProfile(165.0, 31.0, 3.6, 0.0, NutrientSource::Estimated),
            ),
            new RecognisedItem(
                name: 'Steamed rice',
                estimatedGrams: 150.0,
                confidence: 0.85,
                estimatedProfile: new NutrientProfile(130.0, 2.7, 0.3, 28.0, NutrientSource::Estimated),
            ),
        ];
    }
}
