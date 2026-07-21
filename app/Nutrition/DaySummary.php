<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * The totals for one day, and — only if a target is set — the calories
 * remaining against it. Remaining is a plain number: it may be negative, and it
 * carries no verdict. When no goal is set, it is null and the interface shows no
 * "remaining" at all.
 */
final readonly class DaySummary
{
    public function __construct(
        public float $kcal,
        public float $proteinG,
        public float $fatG,
        public float $carbsG,
        public ?float $remainingKcal,
        public bool $hasEstimates,
    ) {}

    public function hasGoal(): bool
    {
        return $this->remainingKcal !== null;
    }
}
