<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * A period's figures for the History screen. `days` is a continuous run over the
 * whole range — a day with no entries is a zero, not a gap, so the bar chart has
 * an unbroken time axis. The average, by contrast, divides by the days that
 * actually have entries (see {@see HistoryAggregator}); a zero on a bar is
 * honest, a zero in the denominator is not.
 */
final readonly class HistorySummary
{
    /**
     * @param  list<array{date: string, kcal: int}>  $days  one per calendar day in range
     */
    public function __construct(
        public array $days,
        public int $totalKcal,
        public int $avgKcalPerDay,
        public int $entryCount,
        public float $proteinG,
        public float $fatG,
        public float $carbsG,
    ) {}

    /** The tallest day, for scaling the bars. Zero when the range is empty. */
    public function maxKcal(): int
    {
        $max = 0;
        foreach ($this->days as $day) {
            $max = max($max, $day['kcal']);
        }

        return $max;
    }
}
