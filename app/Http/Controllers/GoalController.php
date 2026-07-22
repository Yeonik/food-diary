<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Goal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The daily target. Entirely optional and freely editable. The app never
 * suggests lowering it; it only stores what the user chose to aim at.
 */
class GoalController extends Controller
{
    public function edit(): View
    {
        return view('goal.edit', [
            'goal' => Goal::query()->latest('id')->first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'daily_kcal' => ['nullable', 'numeric', 'min:0'],
            'protein_g' => ['nullable', 'numeric', 'min:0'],
            'fat_g' => ['nullable', 'numeric', 'min:0'],
            'carbs_g' => ['nullable', 'numeric', 'min:0'],
        ]);

        $goal = Goal::query()->latest('id')->first() ?? new Goal;

        // The goal switch: off means no targets at all, not a target of zero.
        $enabled = $request->boolean('goal_enabled');
        $goal->daily_kcal = $enabled ? ($validated['daily_kcal'] ?? null) : null;
        $goal->protein_g = $enabled ? ($validated['protein_g'] ?? null) : null;
        $goal->fat_g = $enabled ? ($validated['fat_g'] ?? null) : null;
        $goal->carbs_g = $enabled ? ($validated['carbs_g'] ?? null) : null;

        // Meal visibility is display-only; an unchecked box hides that meal on
        // the Day screen and never touches the entries logged in it.
        $goal->show_breakfast = $request->boolean('show_breakfast');
        $goal->show_lunch = $request->boolean('show_lunch');
        $goal->show_dinner = $request->boolean('show_dinner');
        $goal->show_snack = $request->boolean('show_snack');

        $goal->save();

        return redirect()->route('goal.edit')->with('status', __('settings.saved'));
    }
}
