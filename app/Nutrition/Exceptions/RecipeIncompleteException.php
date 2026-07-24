<?php

declare(strict_types=1);

namespace App\Nutrition\Exceptions;

use RuntimeException;

/**
 * Thrown when a recipe cannot be turned into a number because a cooked weight is
 * missing — its own, or that of another recipe it is built on.
 *
 * This is not a failure to compute so much as a refusal to guess. Without the
 * cooked weight there is no honest divisor, and the alternative — the sum of the
 * raw ingredients — is the wrong one this whole change exists to stop using. So
 * the profile is withheld and the caller is told which recipe still needs a
 * weight, rather than a plausible-looking number being returned.
 *
 * {@see $offendingItemId} is the recipe actually missing its weight, which may
 * be one nested below the recipe whose profile was asked for. The interface uses
 * it to name the recipe to fix.
 */
final class RecipeIncompleteException extends RuntimeException
{
    public function __construct(
        public readonly int $offendingItemId,
    ) {
        parent::__construct("Recipe {$offendingItemId} has no cooked weight, so it has no profile.");
    }
}
