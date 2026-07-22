<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MealEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * History — a window over the diary. The average-per-day divides by the days
 * that actually have entries, not by the length of the range: a day with no
 * entries means "not logged", not "ate zero", and averaging in invented zeros
 * would understate the real days. (The kcal bars still draw an empty day as a
 * zero — a zero on a bar is honest; a zero in the denominator is not.) The bars,
 * the weight line and the custom date range are added with the charts pass; this
 * screen carries the filters, the tiles and the empty state.
 */
class HistoryController extends Controller
{
    public function index(Request $request): View
    {
        $range = $request->query('range') === 'month' ? 'month' : 'week';
        $days = $range === 'month' ? 30 : 7;
        $from = CarbonImmutable::now()->startOfDay()->subDays($days - 1);

        $entries = MealEntry::query()
            ->where('logged_at', '>=', $from->toDateTimeString())
            ->get();

        // Rounded per entry, like every other total the user sees.
        $totalKcal = (int) $entries->sum(fn (MealEntry $entry): float => round($entry->kcal));

        // Divide by days that have entries, never by the whole range.
        $daysWithEntries = $entries
            ->map(fn (MealEntry $entry): string => $entry->logged_at->toDateString())
            ->unique()
            ->count();

        return view('history.index', [
            'range' => $range,
            'hasEntries' => MealEntry::query()->exists(),
            'avgKcalPerDay' => $daysWithEntries > 0 ? (int) round($totalKcal / $daysWithEntries) : 0,
            'entryCount' => $entries->count(),
            'protein' => (float) $entries->sum('protein_g'),
            'fat' => (float) $entries->sum('fat_g'),
            'carbs' => (float) $entries->sum('carbs_g'),
        ]);
    }
}
