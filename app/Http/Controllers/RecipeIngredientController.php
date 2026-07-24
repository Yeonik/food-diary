<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Nutrition\MealLogService;
use App\Nutrition\NutrientSource;
use App\Nutrition\SearchTerms;
use App\Support\RecipeDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Finding a recipe's ingredients in the databases, so a recipe can be built by
 * somebody whose own library is still nearly empty.
 *
 * A round trip, not a live search — there is no XHR in this application and this
 * does not add the first. The recipe being assembled is captured into the
 * session (a {@see RecipeDraft}), the person searches by name, the resolver
 * answers with candidates from the library, USDA and Open Food Facts each
 * labelled with its source, and the chosen one is promoted into the library and
 * added to the draft. The recipe form then re-renders from the draft with the
 * new ingredient in place.
 *
 * The numbers behind a candidate come from the resolver payload the search
 * built and stashed here, never from the form: the same rule the confirm and
 * barcode screens keep. The form only ever sends which candidate and how many
 * grams.
 */
class RecipeIngredientController extends Controller
{
    /** The resolved candidates for the current search, between the search and the pick. */
    public const CANDIDATES_KEY = 'recipe_ingredient_candidates';

    /**
     * Capture the form, resolve the typed name, and show the candidates.
     */
    public function search(Request $request, MealLogService $log): RedirectResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:255'],
        ], [], ['query' => __('library.ingredient_search')]);

        // Everything the person had typed so far, so searching does not lose it.
        $draft = RecipeDraft::capture(
            recipeId: $this->intOrNull($request->input('recipe_id')),
            name: $request->input('name'),
            cookedWeight: $request->input('cooked_weight_g'),
            rawIngredients: $request->input('ingredients'),
        );
        $request->session()->put(RecipeDraft::SESSION_KEY, $draft->toArray());

        // The same resolution the manual path uses: library first, then USDA and
        // Open Food Facts in parallel, nothing auto-selected. No estimate is
        // offered here — an ingredient must be a real number — so no fallback is
        // passed.
        $pending = $log->pendingForName($validated['query']);

        $request->session()->put(self::CANDIDATES_KEY, [
            'query' => $validated['query'],
            'candidates' => $pending['candidates'],
        ]);

        return redirect()->route('library.recipe.ingredient.choose');
    }

    /**
     * The candidate list for the current search.
     */
    public function choose(Request $request): View|RedirectResponse
    {
        $search = $request->session()->get(self::CANDIDATES_KEY);
        $draft = RecipeDraft::fromSession($request->session()->get(RecipeDraft::SESSION_KEY));

        if (! is_array($search) || $draft === null) {
            // Nothing in flight: go back to wherever the recipe form is.
            return $this->backToForm($draft);
        }

        // Estimates never reach here — the search passes no fallback — but filter
        // defensively so a future change cannot slip one onto an addable list.
        $candidates = array_values(array_filter(
            is_array($search['candidates']) ? $search['candidates'] : [],
            fn ($candidate): bool => is_array($candidate)
                && ($candidate['source'] ?? null) !== NutrientSource::Estimated->value,
        ));

        return view('library.recipe-ingredient', [
            'query' => is_string($search['query'] ?? null) ? $search['query'] : '',
            'candidates' => $candidates,
        ]);
    }

    /**
     * Promote the chosen candidate into the library and add it to the draft.
     */
    public function add(Request $request, MealLogService $log): RedirectResponse
    {
        $search = $request->session()->get(self::CANDIDATES_KEY);
        $draft = RecipeDraft::fromSession($request->session()->get(RecipeDraft::SESSION_KEY));

        if (! is_array($search) || $draft === null) {
            return $this->backToForm($draft);
        }

        /** @var list<array<string, mixed>> $candidates */
        $candidates = is_array($search['candidates']) ? array_values($search['candidates']) : [];

        $validated = $request->validate([
            'candidate' => ['required', 'integer', 'min:0', 'max:'.max(0, count($candidates) - 1)],
            'grams' => ['required', 'numeric', 'min:0.1', 'max:5000'],
        ]);

        // The candidate is read from the session payload, by index — its numbers
        // are the resolver's, and the form cannot substitute its own.
        $candidate = $candidates[(int) $validated['candidate']] ?? null;
        if (! is_array($candidate)) {
            return $this->backToForm($draft);
        }

        $label = is_string($candidate['label'] ?? null) ? $candidate['label'] : (string) ($search['query'] ?? '');

        try {
            $itemId = $log->promoteCandidate($candidate, new SearchTerms($label));
        } catch (InvalidArgumentException) {
            // An estimate slipped through; it has no honest number to add.
            return $this->backToForm($draft)->withErrors(['candidate' => __('library.ingredient_estimate_refused')]);
        }

        $draft = $draft->withIngredient($itemId, (float) $validated['grams']);
        $request->session()->put(RecipeDraft::SESSION_KEY, $draft->toArray());
        $request->session()->forget(self::CANDIDATES_KEY);

        return $this->backToForm($draft)->with('status', __('library.ingredient_added', ['name' => $label]));
    }

    /**
     * Abandon the current search without adding anything, keeping the draft.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $draft = RecipeDraft::fromSession($request->session()->get(RecipeDraft::SESSION_KEY));
        $request->session()->forget(self::CANDIDATES_KEY);

        return $this->backToForm($draft);
    }

    private function backToForm(?RecipeDraft $draft): RedirectResponse
    {
        if ($draft?->recipeId !== null) {
            return redirect()->route('library.recipe.edit', $draft->recipeId);
        }

        return redirect()->route('library.recipe.create');
    }

    private function intOrNull(mixed $value): ?int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);

        return $int === false ? null : $int;
    }
}
