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
        $goal->fill($validated);
        $goal->save();

        return redirect()->route('goal.edit')->with('status', 'Goal saved.');
    }
}
