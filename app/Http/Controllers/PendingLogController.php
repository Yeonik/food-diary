<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Nutrition\MealLogService;
use App\Nutrition\MealType;
use App\Nutrition\NutrientProfile;
use App\Nutrition\NutrientSource;
use App\Nutrition\SearchTerms;
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
            // Either a numeric candidate index or the "manual" sentinel — the
            // package-label path, where the numbers below are used instead.
            'items.*.candidate' => ['nullable', 'regex:/^(manual|\d+)$/'],
            'items.*.grams' => ['nullable', 'numeric', 'min:0.1', 'max:5000'],
            // Hand-entered values, per 100 g: macros cap at 100, energy a little
            // above pure fat (9 kcal/g). Required only when "manual" is chosen,
            // enforced per included item below so an incomplete row is rejected.
            'items.*.manual.name' => ['nullable', 'string', 'max:255'],
            'items.*.manual.kcal' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'items.*.manual.protein' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.manual.fat' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.manual.carbs' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $pending = $request->session()->get(self::SESSION_KEY);
        if (! is_array($pending)) {
            return redirect()->route('diary.index');
        }

        $meal = MealType::from((string) $validated['meal']);
        $loggedAt = isset($validated['date'])
            ? CarbonImmutable::parse((string) $validated['date'])->setTimeFrom(CarbonImmutable::now())
            : CarbonImmutable::now();

        // First pass: reject an incomplete manual row before anything is
        // written, so a bad entry never leaves a half-logged meal behind.
        foreach ($validated['items'] as $index => $choice) {
            if (empty($choice['include']) || ($choice['candidate'] ?? null) !== 'manual') {
                continue;
            }

            $manual = is_array($choice['manual'] ?? null) ? $choice['manual'] : [];

            foreach (['kcal', 'protein', 'fat', 'carbs'] as $field) {
                if (($manual[$field] ?? null) === null || $manual[$field] === '') {
                    return back()
                        ->withErrors(["items.{$index}.manual.{$field}" => 'Fill in every value per 100 g from the label.'])
                        ->withInput();
                }
            }
        }

        // Second pass: commit. Manual numbers come from the form by design (the
        // user is the source, entering label values on purpose, attributed to
        // NutrientSource::Manual); candidate numbers still come only from the
        // server-built payload — never the form.
        $logged = 0;

        foreach ($validated['items'] as $index => $choice) {
            if (empty($choice['include'])) {
                continue;
            }

            $item = $pending['items'][$index] ?? null;
            if (! is_array($item)) {
                continue;
            }

            $grams = (float) ($choice['grams'] ?? $item['grams']);

            if (($choice['candidate'] ?? null) === 'manual') {
                $log->commitManual(
                    $this->manualName($choice, (string) $item['name']),
                    $this->manualProfile($choice),
                    $grams,
                    $meal,
                    $loggedAt,
                );
                $logged++;

                continue;
            }

            $candidateIndex = (int) ($choice['candidate'] ?? 0);
            $candidate = $item['candidates'][$candidateIndex] ?? null;
            if ($candidate === null) {
                continue;
            }

            // Both recognised names travel from the pending payload so the commit
            // can store or backfill them — the entry itself is named by display().
            $terms = new SearchTerms(
                (string) ($item['english'] ?? $item['name']),
                isset($item['native']) && is_string($item['native']) ? $item['native'] : null,
            );

            $log->commit($candidate, $terms, $grams, $meal, $loggedAt);
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
     * The edited name for a hand-entered row, falling back to the recognised
     * name when the user left it untouched or blank.
     *
     * @param  array<string, mixed>  $choice
     */
    private function manualName(array $choice, string $fallback): string
    {
        $manual = is_array($choice['manual'] ?? null) ? $choice['manual'] : [];
        $name = trim((string) ($manual['name'] ?? ''));

        return $name !== '' ? $name : $fallback;
    }

    /**
     * A per-100 g profile from the label values the user typed. Tagged
     * {@see NutrientSource::Manual} — verified, and honestly the person's, never
     * a database source. Completeness is enforced before this is reached.
     *
     * @param  array<string, mixed>  $choice
     */
    private function manualProfile(array $choice): NutrientProfile
    {
        $manual = is_array($choice['manual'] ?? null) ? $choice['manual'] : [];

        return new NutrientProfile(
            kcal: (float) ($manual['kcal'] ?? 0),
            proteinG: (float) ($manual['protein'] ?? 0),
            fatG: (float) ($manual['fat'] ?? 0),
            carbsG: (float) ($manual['carbs'] ?? 0),
            source: NutrientSource::Manual,
        );
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
