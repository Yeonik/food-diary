<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\WeightEntry;
use Illuminate\Support\Collection;

/**
 * Normalises weight readings into SVG coordinates in a 600×200 box, so the same
 * weight-line component can be drawn from the Weight screen and from History
 * without either owning the maths.
 */
final class WeightSeries
{
    /**
     * @param  Collection<int, WeightEntry>  $entries
     * @return list<array{x: float, y: float, label: string}>
     */
    public static function points(Collection $entries): array
    {
        $entries = $entries->sortBy('recorded_on')->values();

        if ($entries->isEmpty()) {
            return [];
        }

        $weights = $entries->pluck('weight_kg');
        $min = (float) $weights->min();
        $max = (float) $weights->max();
        $span = $max - $min;
        $count = $entries->count();

        $points = [];
        foreach ($entries as $i => $entry) {
            $x = $count > 1 ? ($i / ($count - 1)) * 580 + 10 : 300.0;
            // Flat line when every reading is equal; otherwise scale into the box.
            $y = $span > 0 ? 190 - (($entry->weight_kg - $min) / $span) * 180 : 100.0;

            $points[] = [
                'x' => round($x, 1),
                'y' => round($y, 1),
                'label' => $entry->recorded_on->format('Y-m-d').': '.$entry->weight_kg.' kg',
            ];
        }

        return $points;
    }
}
