<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MealEntry;
use App\Nutrition\MealType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Editing and deleting logged entries — easy and unpenalised. Editing an entry
 * changes only that entry; it is the user correcting their own record, which is
 * a different thing from a library correction silently rewriting the past.
 */
class MealEntryController extends Controller
{
    public function edit(MealEntry $entry): View
    {
        return view('entries.edit', [
            'entry' => $entry,
            'mealTypes' => MealType::cases(),
        ]);
    }

    public function update(Request $request, MealEntry $entry): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'meal' => ['required', Rule::enum(MealType::class)],
            'grams' => ['required', 'numeric', 'min:0.1', 'max:5000'],
            'kcal' => ['required', 'numeric', 'min:0'],
            'protein_g' => ['required', 'numeric', 'min:0'],
            'fat_g' => ['required', 'numeric', 'min:0'],
            'carbs_g' => ['required', 'numeric', 'min:0'],
        ]);

        $entry->update($validated);

        return redirect()
            ->route('diary.index', ['date' => $entry->logged_at->toDateString()])
            ->with('status', 'Entry updated.');
    }

    public function destroy(MealEntry $entry): RedirectResponse
    {
        $date = $entry->logged_at->toDateString();
        $entry->delete();

        return redirect()
            ->route('diary.index', ['date' => $date])
            ->with('status', 'Entry deleted.');
    }
}
