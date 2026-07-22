<?php

declare(strict_types=1);

namespace App\Nutrition;

use App\Models\Goal;
use App\Models\MealEntry;

/**
 * Adds up a day's entries and, if a goal exists, works out what remains. This
 * is the one place the day's arithmetic lives, so both the diary view and its
 * test see the same rule:
 *
 *   remaining = target − logged, and with no goal there is no remaining.
 */
class DailyTotals
{
    /**
     * @param  iterable<MealEntry>  $entries
     */
    public function summarise(iterable $entries, ?Goal $goal): DaySummary
    {
        $kcal = 0.0;
        $proteinG = 0.0;
        $fatG = 0.0;
        $carbsG = 0.0;
        $hasEstimates = false;

        foreach ($entries as $entry) {
            // Sum the calories the user actually sees — each entry rounded to a
            // whole number — so the on-screen entries, the meal subtotals and
            // this day total always reconcile. Summing the exact values first
            // and rounding once makes the visible arithmetic fail to add up.
            $kcal += round($entry->kcal);
            $proteinG += $entry->protein_g;
            $fatG += $entry->fat_g;
            $carbsG += $entry->carbs_g;

            if (! $entry->isVerified()) {
                $hasEstimates = true;
            }
        }

        return new DaySummary(
            kcal: $kcal,
            proteinG: $proteinG,
            fatG: $fatG,
            carbsG: $carbsG,
            // No goal, or a goal with no kcal target, means nothing to show.
            remainingKcal: $goal?->remainingKcal($kcal),
            hasEstimates: $hasEstimates,
        );
    }
}
