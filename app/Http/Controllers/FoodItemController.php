<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Models\RecipeIngredient;
use App\Nutrition\Exceptions\RecipeCycleException;
use App\Nutrition\Exceptions\RecipeIncompleteException;
use App\Nutrition\FoodItemKind;
use App\Nutrition\ProfileOrigin;
use App\Nutrition\RecipeCalculator;
use App\Support\RecipeDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The personal library: direct items and recipes, listed, edited, and merged.
 * Correcting an item here changes future logs only — past entries kept their
 * snapshot, which is proven by SnapshotImmutabilityTest.
 */
class FoodItemController extends Controller
{
    public function index(Request $request, RecipeCalculator $calculator): View
    {
        $search = is_string($request->query('q')) ? $request->query('q') : '';

        $items = FoodItem::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->with('ingredients.ingredient')
            ->paginate(30)
            ->withQueryString();

        // A recipe stores no per-100 g figures of its own — they come from its
        // ingredients — so the list computes them for the rows it is about to
        // draw. A recipe that somehow cycles is listed without a figure rather
        // than taking the whole screen down; saving one is already refused.
        $recipeKcal = [];
        foreach ($items as $item) {
            if (! $item->isRecipe()) {
                continue;
            }

            try {
                $recipeKcal[$item->id] = $calculator->profileFor($item)->kcal;
            } catch (RecipeCycleException|RecipeIncompleteException) {
                // Left out on purpose: no number is better than a wrong one.
                // A recipe missing its cooked weight lands here too — the row is
                // drawn without a figure, and the next commit says why on it.
            }
        }

        return view('library.index', [
            'items' => $items,
            'search' => $search,
            'recipeKcal' => $recipeKcal,
        ]);
    }

    public function create(): View
    {
        return view('library.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateDirect($request);

        FoodItem::create([
            'name' => $validated['name'],
            'alt_name' => $validated['alt_name'] ?? null,
            'external_id' => $validated['external_id'] ?? null,
            'kind' => FoodItemKind::Direct->value,
            'origin' => ProfileOrigin::Manual->value,
            'kcal_per_100g' => $validated['kcal_per_100g'],
            'protein_g_per_100g' => $validated['protein_g_per_100g'],
            'fat_g_per_100g' => $validated['fat_g_per_100g'],
            'carbs_g_per_100g' => $validated['carbs_g_per_100g'],
        ]);

        return redirect()->route('library.index')->with('status', __('library.item_added'));
    }

    public function edit(FoodItem $item): View
    {
        abort_if($item->isRecipe(), 404);

        return view('library.edit', ['item' => $item]);
    }

    public function update(Request $request, FoodItem $item): RedirectResponse
    {
        abort_if($item->isRecipe(), 404);

        $validated = $this->validateDirect($request);
        $item->update($validated);

        return redirect()->route('library.index')->with('status', __('library.item_corrected'));
    }

    public function createRecipe(Request $request): View
    {
        // A recipe being assembled through the ingredient search survives in the
        // session; hydrate the form from it so the rows added there are here.
        // Only a draft for a new recipe (no id) belongs on this screen.
        $draft = RecipeDraft::fromSession($request->session()->get(RecipeDraft::SESSION_KEY));
        if ($draft !== null && ! $draft->belongsTo(null)) {
            $draft = null;
        }

        return view('library.recipe', [
            'recipe' => null,
            'ingredients' => FoodItem::query()->orderBy('name')->get(),
            'total' => null,
            'incomplete' => false,
            // No ingredients yet, so no raw sum to compare against.
            'rawSum' => null,
            'draft' => $draft,
        ]);
    }

    public function storeRecipe(Request $request, RecipeCalculator $calculator): RedirectResponse
    {
        $validated = $this->validateRecipe($request);

        try {
            $recipe = DB::transaction(function () use ($validated, $calculator): FoodItem {
                $recipe = FoodItem::create([
                    'name' => $validated['name'],
                    'kind' => FoodItemKind::Recipe->value,
                    'cooked_weight_g' => $validated['cooked_weight_g'],
                ]);

                $this->syncIngredients($recipe, $validated['ingredients']);

                // Reuse the domain cycle guard: computing the profile throws if
                // the ingredients form a loop, which rolls the whole thing back.
                $calculator->profileFor($recipe->load('ingredients.ingredient'));

                return $recipe;
            });
        } catch (RecipeCycleException) {
            return back()->withErrors(['ingredients' => __('library.cycle_error')])->withInput();
        } catch (RecipeIncompleteException) {
            // This recipe has a cooked weight — validation required it — so the
            // one missing it is an ingredient recipe. Refuse rather than store a
            // recipe that cannot be turned into a number.
            return back()->withErrors(['ingredients' => __('library.ingredient_needs_cooked_weight')])->withInput();
        }

        // Saved: the draft it was assembled from has done its job.
        $request->session()->forget(RecipeDraft::SESSION_KEY);

        return redirect()->route('library.index')->with('status', __('library.recipe_saved', ['name' => $recipe->name]));
    }

    public function editRecipe(Request $request, FoodItem $item, RecipeCalculator $calculator): View
    {
        abort_unless($item->isRecipe(), 404);

        // A draft assembled through the ingredient search for THIS recipe takes
        // precedence over the saved rows, so an ingredient just added there is
        // shown. A draft for a different recipe is ignored.
        $draft = RecipeDraft::fromSession($request->session()->get(RecipeDraft::SESSION_KEY));
        if ($draft !== null && ! $draft->belongsTo($item->id)) {
            $draft = null;
        }

        // What the ingredients currently come to, per 100 g. A saved recipe
        // cannot hold a cycle — saving one is refused — but the guard stays,
        // because a screen should not be the thing that fails.
        $incomplete = false;

        try {
            $total = $calculator->profileFor($item->load('ingredients.ingredient'));
        } catch (RecipeCycleException) {
            // No total, because the ingredients cycle. The form still opens.
            $total = null;
        } catch (RecipeIncompleteException) {
            // No total, because a cooked weight is missing — this recipe's, or
            // one it is built on. Distinguished from a cycle so the screen can
            // say which, and so it reads as "fill this in", not "something is
            // wrong".
            $total = null;
            $incomplete = true;
        }

        return view('library.recipe', [
            'recipe' => $item->load('ingredients'),
            'ingredients' => FoodItem::query()->where('id', '!=', $item->id)->orderBy('name')->get(),
            'total' => $total,
            'incomplete' => $incomplete,
            // The raw ingredient total, shown beside the cooked-weight field as a
            // reference — not a constraint. A dish can weigh more or less than
            // this, but seeing 300 g of ingredients makes a mistyped 30 g cooked
            // weight obvious. Summed from the saved ingredients, so it is there
            // with JavaScript off.
            'rawSum' => $item->ingredients->sum('grams'),
            'draft' => $draft,
        ]);
    }

    public function updateRecipe(Request $request, FoodItem $item, RecipeCalculator $calculator): RedirectResponse
    {
        abort_unless($item->isRecipe(), 404);

        $validated = $this->validateRecipe($request);

        try {
            DB::transaction(function () use ($item, $validated, $calculator): void {
                $item->update([
                    'name' => $validated['name'],
                    'cooked_weight_g' => $validated['cooked_weight_g'],
                ]);
                $item->ingredients()->delete();
                $this->syncIngredients($item, $validated['ingredients']);

                $calculator->profileFor($item->load('ingredients.ingredient'));
            });
        } catch (RecipeCycleException) {
            return back()->withErrors(['ingredients' => __('library.cycle_error')])->withInput();
        } catch (RecipeIncompleteException) {
            return back()->withErrors(['ingredients' => __('library.ingredient_needs_cooked_weight')])->withInput();
        }

        // Saved: the draft it may have been assembled from is spent.
        $request->session()->forget(RecipeDraft::SESSION_KEY);

        return redirect()->route('library.index')->with('status', __('library.recipe_updated'));
    }

    public function destroy(FoodItem $item): RedirectResponse
    {
        // An item a recipe depends on cannot be deleted out from under it (the
        // database enforces this too); say so rather than letting it fail.
        if (RecipeIngredient::query()->where('ingredient_id', $item->id)->exists()) {
            return back()->withErrors(['delete' => __('library.in_use_error')]);
        }

        DB::transaction(function () use ($item): void {
            // Past entries keep their numbers and lose only the link back. The
            // database used to do this itself, with ON DELETE SET NULL; it
            // cannot any more, because that link is now half of a key whose
            // other half is the owner, and SET NULL would null the owner too.
            // So the unlinking is written down here — and if it were ever left
            // out, the delete below would be refused rather than quietly
            // reaching across. The merge does the same thing by repointing.
            MealEntry::query()->where('food_item_id', $item->id)->update(['food_item_id' => null]);

            $item->delete();
        });

        return redirect()->route('library.index')->with('status', __('library.item_removed'));
    }

    public function merge(Request $request, FoodItem $item): RedirectResponse
    {
        // The survivor arrives in the body, not the path, so route model binding
        // never sees it and the model's scope never runs. A bare `exists` here
        // would ask the whole table, which is the one place in this controller
        // where an id can name a row its owner did not write. Constrained to the
        // signed-in person, somebody else's id fails validation in exactly the
        // same words as an id that never existed.
        $validated = $request->validate([
            'target_id' => ['required', 'integer', Rule::exists('food_items', 'id')
                ->where('user_id', Auth::id())
                ->whereNot('id', $item->id)],
        ]);

        $targetId = (int) $validated['target_id'];

        DB::transaction(function () use ($item, $targetId): void {
            // Repoint everything that referenced the duplicate at the survivor,
            // then drop the duplicate. Historical entries keep their snapshot;
            // only their provenance link moves.
            RecipeIngredient::query()->where('ingredient_id', $item->id)->update(['ingredient_id' => $targetId]);
            MealEntry::query()->where('food_item_id', $item->id)->update(['food_item_id' => $targetId]);

            // Don't lose a second-language name the duplicate carried: if the
            // survivor has no alt name, adopt one of the duplicate's names that
            // it does not already have. Never overwrite an existing alt name.
            // Read back through the model, so the scope answers this too. The
            // validation above already settled it; a write to `alt_name` is
            // worth having the second lock on.
            $target = FoodItem::query()->find($targetId);
            if ($target !== null && ($target->alt_name === null || trim($target->alt_name) === '')) {
                foreach ([$item->alt_name, $item->name] as $candidate) {
                    if (is_string($candidate) && trim($candidate) !== '' && strcasecmp($candidate, $target->name) !== 0) {
                        $target->alt_name = trim($candidate);
                        $target->save();
                        break;
                    }
                }
            }

            $item->delete();
        });

        return redirect()->route('library.index')->with('status', __('library.duplicates_merged'));
    }

    /**
     * @return array{name: string, alt_name: string|null, external_id: string|null, kcal_per_100g: float, protein_g_per_100g: float, fat_g_per_100g: float, carbs_g_per_100g: float}
     */
    private function validateDirect(Request $request): array
    {
        /** @var array{name: string, alt_name: string|null, external_id: string|null, kcal_per_100g: float, protein_g_per_100g: float, fat_g_per_100g: float, carbs_g_per_100g: float} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // The same food's name in another language, optional — lets a photo
            // resolve it by either name later.
            'alt_name' => ['nullable', 'string', 'max:255'],
            // Barcode: the stable id that makes this product match exactly, no
            // camera needed. Stored as external_id, digits and hyphens only.
            'external_id' => ['nullable', 'string', 'max:64', 'regex:/^[0-9-]+$/'],
            'kcal_per_100g' => ['required', 'numeric', 'min:0'],
            'protein_g_per_100g' => ['required', 'numeric', 'min:0'],
            'fat_g_per_100g' => ['required', 'numeric', 'min:0'],
            'carbs_g_per_100g' => ['required', 'numeric', 'min:0'],
        ]);

        return $validated;
    }

    /**
     * @return array{name: string, cooked_weight_g: float, ingredients: list<array{item_id: int, grams: float}>}
     */
    private function validateRecipe(Request $request): array
    {
        // A comma decimal, the way the weight log already accepts one: a Russian
        // keyboard offers a comma and half the scales in the world print one, so
        // rejecting a correct weight over its punctuation would be its own bug.
        $cooked = $request->input('cooked_weight_g');
        if (is_string($cooked)) {
            $request->merge(['cooked_weight_g' => str_replace(',', '.', trim($cooked))]);
        }

        /** @var array{name: string, cooked_weight_g: float, ingredients: list<array{item_id: int, grams: float}>} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Required, and this is the point of the whole change: a recipe with
            // no cooked weight has no honest number, so the form does not let one
            // be defined without it. No upper bound tied to the ingredient sum —
            // a dish both absorbs water and boils it off, so the cooked weight
            // can legitimately sit either side of the raw total.
            'cooked_weight_g' => ['required', 'numeric', 'min:0.1', 'max:20000'],
            'ingredients' => ['required', 'array', 'min:1'],
            // The same shape as the merge target: an id from the body, checked
            // against the whole table unless it is told whose table to look at.
            // A recipe built on somebody else's item would hold a line pointing
            // across the boundary, and the calculator would read through it.
            'ingredients.*.item_id' => ['required', 'integer', Rule::exists('food_items', 'id')
                ->where('user_id', Auth::id())],
            'ingredients.*.grams' => ['required', 'numeric', 'min:0.1', 'max:5000'],
        ]);

        return $validated;
    }

    /**
     * @param  list<array{item_id: int, grams: float}>  $ingredients
     */
    private function syncIngredients(FoodItem $recipe, array $ingredients): void
    {
        foreach ($ingredients as $ingredient) {
            RecipeIngredient::create([
                'recipe_id' => $recipe->id,
                'ingredient_id' => $ingredient['item_id'],
                'grams' => $ingredient['grams'],
            ]);
        }
    }
}
