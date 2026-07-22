<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Nutrition\MealLogService;
use App\Nutrition\MealType;
use App\Nutrition\SearchTerms;
use App\Nutrition\Sources\OpenFoodFactsSource;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The barcode path: a code — scanned in the browser from a captured frame, or
 * typed by hand — resolves to a single Open Food Facts product, which the user
 * confirms a weight for and logs. It is deliberately separate from the photo
 * confirm screen: one code, one product, no multi-candidate list and nothing
 * pre-selected there to worry about.
 *
 * The looked-up product lives in the session between the two steps, so the
 * commit reads its numbers from the server payload, never from the form.
 */
class BarcodeController extends Controller
{
    public const SESSION_KEY = 'barcode_pending';

    public function create(): View
    {
        return view('log.barcode');
    }

    public function lookup(Request $request, OpenFoodFactsSource $off, MealLogService $log): RedirectResponse
    {
        $validated = $request->validate([
            // Digits and hyphens only — a barcode, never a free-text search.
            'code' => ['required', 'string', 'max:64', 'regex:/^[0-9-]+$/'],
        ]);

        $match = $off->productByCode($validated['code']);

        if ($match === null) {
            // Name the real cause: the code is not in Open Food Facts. Do not
            // dress a miss up as a scan failure.
            return back()
                ->withInput()
                ->with('barcode_status', __('barcode.not_found', ['code' => $validated['code']]));
        }

        $request->session()->put(self::SESSION_KEY, $log->pendingForProduct($match));

        return redirect()->route('log.barcode.confirm');
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! is_array($pending) || ! is_array($pending['candidate'] ?? null)) {
            return redirect()->route('log.barcode');
        }

        return view('log.barcode-confirm', [
            'product' => $pending,
            'mealTypes' => MealType::cases(),
        ]);
    }

    public function store(Request $request, MealLogService $log): RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        $candidate = is_array($pending) ? ($pending['candidate'] ?? null) : null;

        if (! is_array($candidate)) {
            return redirect()->route('log.barcode');
        }

        $validated = $request->validate([
            'meal' => ['required', Rule::enum(MealType::class)],
            'date' => ['nullable', 'date'],
            'grams' => ['required', 'numeric', 'min:0.1', 'max:5000'],
        ]);

        $meal = MealType::from((string) $validated['meal']);
        $loggedAt = isset($validated['date'])
            ? CarbonImmutable::parse((string) $validated['date'])->setTimeFrom(CarbonImmutable::now())
            : CarbonImmutable::now();

        // Numbers come from the session payload the lookup built, never the form;
        // committing an Open Food Facts match promotes it to the library with the
        // barcode as its stable id.
        $name = is_string($pending['name'] ?? null) ? $pending['name'] : '';
        $log->commit($candidate, new SearchTerms($name), (float) $validated['grams'], $meal, $loggedAt);

        $request->session()->forget(self::SESSION_KEY);

        return redirect()
            ->route('diary.index', ['date' => $loggedAt->toDateString()])
            ->with('status', __('barcode.logged'));
    }
}
