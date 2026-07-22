<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Models\MealEntry;
use App\Nutrition\DailyTotals;
use App\Nutrition\MealType;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The daily view: entries grouped by meal, the day's totals, and — only if a
 * goal is set — what remains. The arithmetic is neutral; the view renders a
 * number, never a verdict.
 */
class DiaryController extends Controller
{
    public function index(Request $request, DailyTotals $totals): View
    {
        $date = $this->resolveDate($request->query('date'));

        $entries = MealEntry::query()
            ->whereDate('logged_at', $date->toDateString())
            ->orderBy('logged_at')
            ->get();

        $goal = Goal::query()->latest('id')->first();

        // Totals are computed from every entry — a hidden meal still counts.
        $summary = $totals->summarise($entries, $goal);

        // Display only the meals the user keeps visible (all, when no goal row
        // exists yet). Grouped in a stable order.
        $visibleMeals = array_values(array_filter(
            MealType::cases(),
            fn (MealType $meal): bool => $goal?->showsMeal($meal) ?? true,
        ));

        $byMeal = [];
        foreach ($visibleMeals as $meal) {
            $byMeal[$meal->value] = $entries->where('meal', $meal)->values();
        }

        return view('diary.index', [
            'date' => $date,
            'previous' => $date->subDay(),
            'next' => $date->addDay(),
            'goal' => $goal,
            'visibleMeals' => $visibleMeals,
            'entriesByMeal' => $byMeal,
            'summary' => $summary,
            'hasAnyEntry' => $entries->isNotEmpty(),
        ]);
    }

    private function resolveDate(mixed $input): CarbonImmutable
    {
        if (is_string($input) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $input) === 1) {
            return CarbonImmutable::parse($input)->startOfDay();
        }

        return CarbonImmutable::now()->startOfDay();
    }
}
