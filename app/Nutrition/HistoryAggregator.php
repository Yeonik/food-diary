<?php

declare(strict_types=1);

namespace App\Nutrition;

use App\Models\MealEntry;
use Carbon\CarbonImmutable;

/**
 * Rolls a set of logged entries up into a {@see HistorySummary} over a date
 * range. Pure and offline: it takes the entries it is given and does no I/O, so
 * the arithmetic can be tested directly against hand figures.
 *
 * Two rules it must not confuse:
 *   - the per-day series is continuous — every calendar day in the range is
 *     present, a day without entries carrying zero, so the bars never skip;
 *   - the average divides by the days that have entries, not by the range, so
 *     unlogged days do not water it down with invented zeros.
 */
class HistoryAggregator
{
    /**
     * @param  iterable<MealEntry>  $entries
     */
    public function summarise(iterable $entries, CarbonImmutable $from, CarbonImmutable $to): HistorySummary
    {
        $from = $from->startOfDay();
        $to = $to->startOfDay();
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        /** @var array<string, int> $kcalByDate */
        $kcalByDate = [];
        /** @var array<string, int> $countByDate */
        $countByDate = [];
        $proteinG = 0.0;
        $fatG = 0.0;
        $carbsG = 0.0;

        foreach ($entries as $entry) {
            $date = $entry->logged_at->toDateString();

            // Ignore anything outside the window, so the result depends only on
            // the range asked for — never on stray entries handed in.
            if ($date < $fromDate || $date > $toDate) {
                continue;
            }

            // Rounded per entry, so the bars reconcile with the daily view.
            $kcalByDate[$date] = ($kcalByDate[$date] ?? 0) + (int) round($entry->kcal);
            $countByDate[$date] = ($countByDate[$date] ?? 0) + 1;
            $proteinG += $entry->protein_g;
            $fatG += $entry->fat_g;
            $carbsG += $entry->carbs_g;
        }

        // Continuous day series across the whole range.
        $days = [];
        $totalKcal = 0;
        for ($cursor = $from; $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addDay()) {
            $date = $cursor->toDateString();
            $kcal = $kcalByDate[$date] ?? 0;
            $days[] = ['date' => $date, 'kcal' => $kcal];
            $totalKcal += $kcal;
        }

        $daysWithEntries = count($countByDate);

        return new HistorySummary(
            days: $days,
            totalKcal: $totalKcal,
            avgKcalPerDay: $daysWithEntries > 0 ? (int) round($totalKcal / $daysWithEntries) : 0,
            entryCount: array_sum($countByDate),
            proteinG: $proteinG,
            fatG: $fatG,
            carbsG: $carbsG,
        );
    }
}
