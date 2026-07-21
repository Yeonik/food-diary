<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Nutrition\MealLogService;
use App\Nutrition\MealType;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The shared confirm screen for both the photo and manual paths. It shows the
 * candidate matches — each labelled with its source — and lets the user pick,
 * adjust the weight, and log. The nutrient numbers come from the session
 * payload the server built; the form only carries the choice.
 */
class PendingLogController extends Controller
{
    public const SESSION_KEY = 'pending_log';

    public function show(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! is_array($pending) || $pending['items'] === []) {
            return redirect()->route('diary.index');
        }

        return view('log.confirm', [
            'items' => $pending['items'],
            'hasPhoto' => ($pending['photo'] ?? null) !== null,
            'mealTypes' => MealType::cases(),
        ]);
    }

    public function store(Request $request, MealLogService $log): RedirectResponse
    {
        $validated = $request->validate([
            'meal' => ['required', Rule::enum(MealType::class)],
            'date' => ['nullable', 'date'],
            'items' => ['required', 'array'],
            'items.*.include' => ['nullable', 'boolean'],
            'items.*.candidate' => ['nullable', 'integer', 'min:0'],
            'items.*.grams' => ['nullable', 'numeric', 'min:0.1', 'max:5000'],
        ]);

        $pending = $request->session()->get(self::SESSION_KEY);
        if (! is_array($pending)) {
            return redirect()->route('diary.index');
        }

        $meal = MealType::from((string) $validated['meal']);
        $loggedAt = isset($validated['date'])
            ? CarbonImmutable::parse((string) $validated['date'])->setTimeFrom(CarbonImmutable::now())
            : CarbonImmutable::now();

        $logged = 0;

        foreach ($validated['items'] as $index => $choice) {
            if (empty($choice['include'])) {
                continue;
            }

            $item = $pending['items'][$index] ?? null;
            $candidateIndex = (int) ($choice['candidate'] ?? 0);
            $candidate = is_array($item) ? ($item['candidates'][$candidateIndex] ?? null) : null;

            if ($candidate === null) {
                continue;
            }

            $grams = (float) ($choice['grams'] ?? $item['grams']);
            $log->commit($candidate, (string) $item['name'], $grams, $meal, $loggedAt);
            $logged++;
        }

        $this->cleanUp($pending);
        $request->session()->forget(self::SESSION_KEY);

        if ($logged === 0) {
            return redirect()->route('diary.index')->with('status', 'Nothing was selected, so nothing was logged.');
        }

        return redirect()
            ->route('diary.index', ['date' => $loggedAt->toDateString()])
            ->with('status', $logged === 1 ? 'Logged one item.' : "Logged {$logged} items.");
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function cleanUp(array $pending): void
    {
        $photo = $pending['photo'] ?? null;

        // The photo is deleted once the entry is confirmed (configurable).
        if (is_string($photo) && config('nutrition.photo.delete_after_confirm') === true && is_file($photo)) {
            @unlink($photo);
        }
    }
}
