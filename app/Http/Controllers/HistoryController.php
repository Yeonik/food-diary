<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Models\MealEntry;
use App\Models\WeightEntry;
use App\Nutrition\HistoryAggregator;
use App\Support\WeightSeries;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * History — kcal-per-day bars, the weight line and a macro split over a chosen
 * window. The arithmetic lives in {@see HistoryAggregator}; this controller only
 * resolves the range (week, month, or a custom pair of dates) and hands the
 * figures to the hand-rolled SVG charts.
 */
class HistoryController extends Controller
{
    /** A custom range is capped so one request cannot draw thousands of bars. */
    private const MAX_RANGE_DAYS = 366;

    public function index(Request $request, HistoryAggregator $aggregator): View
    {
        $range = in_array($request->query('range'), ['month', 'range'], true)
            ? (string) $request->query('range')
            : 'week';

        [$from, $to] = $this->resolveRange($request, $range);

        $entries = MealEntry::query()
            ->whereBetween('logged_at', [$from->startOfDay(), $to->endOfDay()])
            ->get();

        $weightEntries = WeightEntry::query()
            ->whereBetween('recorded_on', [$from->toDateString(), $to->toDateString()])
            ->get();

        $goal = Goal::query()->latest('id')->first();

        return view('history.index', [
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'hasEntries' => MealEntry::query()->exists(),
            'summary' => $aggregator->summarise($entries, $from, $to),
            'goalKcal' => $goal?->daily_kcal !== null ? (int) round($goal->daily_kcal) : null,
            'weightPoints' => WeightSeries::points($weightEntries),
            // The most recent reading in the window, for the weight card's header.
            'latestWeight' => $weightEntries->sortBy('recorded_on')->last()?->weight_kg,
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(Request $request, string $range): array
    {
        $today = CarbonImmutable::now()->startOfDay();

        if ($range === 'range') {
            $from = $this->parseDate($request->query('from')) ?? $today->subDays(6);
            $to = $this->parseDate($request->query('to')) ?? $today;

            if ($from->greaterThan($to)) {
                [$from, $to] = [$to, $from];
            }

            // Clamp an over-long span to the most recent MAX_RANGE_DAYS.
            if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
                $from = $to->subDays(self::MAX_RANGE_DAYS);
            }

            return [$from, $to];
        }

        $days = $range === 'month' ? 30 : 7;

        return [$today->subDays($days - 1), $today];
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return CarbonImmutable::parse($value)->startOfDay();
        }

        return null;
    }
}
