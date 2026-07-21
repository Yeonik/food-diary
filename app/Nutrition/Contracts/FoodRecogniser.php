<?php

declare(strict_types=1);

namespace App\Nutrition\Contracts;

use App\Nutrition\PreparedPhoto;
use App\Nutrition\RecognisedItem;

/**
 * The recognition seam. An implementation names the dishes in a photo and
 * guesses rough portions — and nothing more. Nutrient numbers are not its job;
 * those come from the resolution ladder.
 *
 * Swapping the real Gemini implementation for {@see FakeRecogniser} is what lets
 * the whole flow be tested with no network call and no API key.
 */
interface FoodRecogniser
{
    /**
     * @return list<RecognisedItem>
     */
    public function recognise(PreparedPhoto $photo): array;
}
