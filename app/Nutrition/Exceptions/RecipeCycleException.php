<?php

declare(strict_types=1);

namespace App\Nutrition\Exceptions;

use RuntimeException;

/**
 * Thrown when a recipe references itself, directly or through a chain of other
 * recipes. Computing its profile would otherwise loop forever, so the cycle is
 * rejected rather than followed.
 */
final class RecipeCycleException extends RuntimeException
{
    /**
     * @param  list<int>  $chain  the recipe ids visited when the cycle was found
     */
    public function __construct(
        public readonly int $offendingItemId,
        public readonly array $chain,
    ) {
        $path = implode(' -> ', [...$chain, $offendingItemId]);

        parent::__construct("Recipe cycle detected: {$path}.");
    }
}
