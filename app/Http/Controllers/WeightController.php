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
            // This screen draws the line tall; History draws it short.
            'chart' => WeightSeries::points($entries, 820, 320),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // A Russian keyboard offers a comma for the decimal, and half the scales
        // in the world print one. Taking only a dot would reject a correct
        // reading over its punctuation, so the separator is normalised before the
        // value is validated as a number.
        $weight = $request->input('weight_kg');
        if (is_string($weight)) {
            $request->merge(['weight_kg' => str_replace(',', '.', trim($weight))]);
        }

        $validated = $request->validate([
            'recorded_on' => ['required', 'date'],
            'weight_kg' => ['required', 'numeric', 'min:1', 'max:600'],
        ]);

        // One reading per day: a second entry for a date replaces the first.
        WeightEntry::updateOrCreate(
            ['recorded_on' => $validated['recorded_on']],
            ['weight_kg' => $validated['weight_kg']],
        );

        return redirect()->route('weight.index')->with('status', __('weight.recorded'));
    }

    public function destroy(WeightEntry $entry): RedirectResponse
    {
        $entry->delete();

        return redirect()->route('weight.index')->with('status', __('weight.removed'));
    }
}
