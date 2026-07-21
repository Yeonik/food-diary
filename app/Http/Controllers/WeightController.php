<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\WeightEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * A weight log and a line — nothing more. No BMI verdict, no target-weight
 * nagging, no commentary on the trend. The chart is a plain inline SVG so it
 * needs no client library and works offline.
 */
class WeightController extends Controller
{
    public function index(): View
    {
        $entries = WeightEntry::query()->orderBy('recorded_on')->get();

        return view('weight.index', [
            'entries' => $entries->sortByDesc('recorded_on')->values(),
            'chart' => $this->chartPoints($entries),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'recorded_on' => ['required', 'date'],
            'weight_kg' => ['required', 'numeric', 'min:1', 'max:600'],
        ]);

        // One reading per day: a second entry for a date replaces the first.
        WeightEntry::updateOrCreate(
            ['recorded_on' => $validated['recorded_on']],
            ['weight_kg' => $validated['weight_kg']],
        );

        return redirect()->route('weight.index')->with('status', 'Weight recorded.');
    }

    public function destroy(WeightEntry $entry): RedirectResponse
    {
        $entry->delete();

        return redirect()->route('weight.index')->with('status', 'Reading removed.');
    }

    /**
     * Normalise the readings into SVG coordinates in a 600×200 box.
     *
     * @param  Collection<int, WeightEntry>  $entries
     * @return list<array{x: float, y: float, label: string}>
     */
    private function chartPoints($entries): array
    {
        if ($entries->count() < 1) {
            return [];
        }

        $weights = $entries->pluck('weight_kg');
        $min = (float) $weights->min();
        $max = (float) $weights->max();
        $span = $max - $min;

        $count = $entries->count();
        $points = [];

        foreach ($entries->values() as $i => $entry) {
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
