<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\WeightEntry;
use App\Support\WeightSeries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'chart' => WeightSeries::points($entries),
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
}
