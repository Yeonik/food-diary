<?php

declare(strict_types=1);

namespace App\Nutrition\Contracts;

use App\Nutrition\NutrientMatch;
use App\Nutrition\NutrientSource;

/**
 * A place to look a food name up. Each source returns its own candidate
 * matches, labelled with itself — the resolver never merges them into a single
 * "answer", because a wrong automatic match is worse than a short list.
 */
interface NutritionSource
{
    /**
     * @return list<NutrientMatch>
     */
    public function search(string $name): array;

    /**
     * Which source this is, for labelling matches in the interface.
     */
    public function source(): NutrientSource;
}
