<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * A note that a source could not be consulted this time — a rate limit, a
 * timeout, an error. Surfaced to the user so the app says what happened rather
 * than silently returning a shorter list.
 */
final readonly class ResolutionNotice
{
    public function __construct(
        public NutrientSource $source,
        public string $message,
    ) {}
}
