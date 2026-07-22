<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MealEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * History — a window over the diary. The summary tiles here divide by the whole
 * range, so days without entries pull the average towards zero rather than being
 * skipped. The kcal-per-day bars, the weight line and the custom date range are
 * added with the charts pass; this screen carries the filters, the tiles and the
 * empty state.
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

        return view('history.index', [
            'range' => $range,
            'hasEntries' => MealEntry::query()->exists(),
            'avgKcalPerDay' => (int) round($totalKcal / $days),
            'entryCount' => $entries->count(),
            'protein' => (float) $entries->sum('protein_g'),
            'fat' => (float) $entries->sum('fat_g'),
            'carbs' => (float) $entries->sum('carbs_g'),
        ]);
    }
}
