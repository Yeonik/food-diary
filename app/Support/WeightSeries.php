<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\WeightEntry;
use Illuminate\Support\Collection;

/**
 * Normalises weight readings into SVG coordinates, so the same weight-line
 * component can be drawn from the Weight screen and from History without either
 * owning the maths. The box is the caller's, because the two screens draw the
 * line at different sizes; the default is History's (design/build).
 */
final class WeightSeries
{
    /** Keeps the end dots off the edge — they are drawn at r=5. */
    private const PAD = 10.0;

    /**
     * @param  Collection<int, WeightEntry>  $entries
     * @return list<array{x: float, y: float, label: string}>
     */
    public static function points(Collection $entries, float $width = 900.0, float $height = 200.0): array
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

        $left = self::PAD;
        $usableWidth = $width - self::PAD * 2;
        $bottom = $height - self::PAD;
        $usableHeight = $height - self::PAD * 2;

        $points = [];
        foreach ($entries as $i => $entry) {
            $x = $count > 1 ? ($i / ($count - 1)) * $usableWidth + $left : $width / 2;
            // Flat line when every reading is equal; otherwise scale into the box.
            $y = $span > 0 ? $bottom - (($entry->weight_kg - $min) / $span) * $usableHeight : $height / 2;

            $points[] = [
                'x' => round($x, 1),
                'y' => round($y, 1),
                'label' => $entry->recorded_on->format('Y-m-d').': '.$entry->weight_kg.' kg',
            ];
        }

        return $points;
    }
}
