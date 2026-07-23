<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Models\RecipeIngredient;
use App\Nutrition\Exceptions\RecipeCycleException;
use App\Nutrition\FoodItemKind;
use App\Nutrition\ProfileOrigin;
use App\Nutrition\RecipeCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            } catch (RecipeCycleException) {
                // Left out on purpose: no number is better than a wrong one.
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

    public function createRecipe(): View
    {
        return view('library.recipe', [
            'recipe' => null,
            'ingredients' => FoodItem::query()->orderBy('name')->get(),
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
                ]);

                $this->syncIngredients($recipe, $validated['ingredients']);

                // Reuse the domain cycle guard: computing the profile throws if
                // the ingredients form a loop, which rolls the whole thing back.
                $calculator->profileFor($recipe->load('ingredients.ingredient'));

                return $recipe;
            });
        } catch (RecipeCycleException) {
            return back()->withErrors(['ingredients' => __('library.cycle_error')])->withInput();
        }

        return redirect()->route('library.index')->with('status', __('library.recipe_saved', ['name' => $recipe->name]));
    }

    public function editRecipe(FoodItem $item): View
    {
        abort_unless($item->isRecipe(), 404);

        return view('library.recipe', [
            'recipe' => $item->load('ingredients'),
            'ingredients' => FoodItem::query()->where('id', '!=', $item->id)->orderBy('name')->get(),
        ]);
    }

    public function updateRecipe(Request $request, FoodItem $item, RecipeCalculator $calculator): RedirectResponse
    {
        abort_unless($item->isRecipe(), 404);

        $validated = $this->validateRecipe($request);

        try {
            DB::transaction(function () use ($item, $validated, $calculator): void {
                $item->update(['name' => $validated['name']]);
                $item->ingredients()->delete();
                $this->syncIngredients($item, $validated['ingredients']);

                $calculator->profileFor($item->load('ingredients.ingredient'));
            });
        } catch (RecipeCycleException) {
            return back()->withErrors(['ingredients' => __('library.cycle_error')])->withInput();
        }

        return redirect()->route('library.index')->with('status', __('library.recipe_updated'));
    }

    public function destroy(FoodItem $item): RedirectResponse
    {
        // An item a recipe depends on cannot be deleted out from under it (the
        // database enforces this too); say so rather than letting it fail.
        if (RecipeIngredient::query()->where('ingredient_id', $item->id)->exists()) {
            return back()->withErrors(['delete' => __('library.in_use_error')]);
        }

        $item->delete();

        return redirect()->route('library.index')->with('status', __('library.item_removed'));
    }

    public function merge(Request $request, FoodItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'target_id' => ['required', 'integer', Rule::exists('food_items', 'id')->whereNot('id', $item->id)],
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
            $target = FoodItem::find($targetId);
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
     * @return array{name: string, ingredients: list<array{item_id: int, grams: float}>}
     */
    private function validateRecipe(Request $request): array
    {
        /** @var array{name: string, ingredients: list<array{item_id: int, grams: float}>} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.item_id' => ['required', 'integer', Rule::exists('food_items', 'id')],
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
