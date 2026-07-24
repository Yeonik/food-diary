<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A recipe being assembled, held between requests.
 *
 * Building a recipe from database ingredients is a round trip: the person leaves
 * the recipe form to search USDA or Open Food Facts, picks a result, and comes
 * back with it added. Nothing is fetched in the background — there is no XHR
 * anywhere in this application, and this feature does not introduce the first —
 * so the half-built recipe has to survive that trip somewhere. It survives in
 * the session, the same way the barcode path holds its looked-up product.
 *
 * This is a plain value object over the session array, not the session itself:
 * the controller reads the array out, wraps it, mutates a copy, and writes it
 * back. That keeps the shape in one place and lets it be tested without a
 * request.
 *
 * It carries only what the person typed — a name, a cooked weight, and the
 * ingredient rows chosen so far. It never carries nutrient numbers: an
 * ingredient row is an id and a weight, and the numbers behind that id live on
 * the promoted library item, never here.
 */
final readonly class RecipeDraft
{
    /** Where the assembling recipe lives between the form and the ingredient search. */
    public const SESSION_KEY = 'recipe_draft';

    /**
     * @param  int|null  $recipeId  the recipe being edited, or null for a new one
     * @param  list<array{item_id: int, grams: float}>  $ingredients
     */
    public function __construct(
        public ?int $recipeId,
        public string $name,
        public string $cookedWeight,
        public array $ingredients,
    ) {}

    /**
     * Capture the current state of the recipe form, so leaving it to search for
     * an ingredient does not lose the name, the weight, or the rows so far.
     *
     * Ingredient rows are filtered to the complete ones: a half-filled row the
     * person had not finished is not carried, so it cannot come back as an
     * empty selection.
     *
     * @param  mixed  $rawIngredients  the `ingredients[]` form input, whatever shape it arrives in
     */
    public static function capture(?int $recipeId, ?string $name, ?string $cookedWeight, mixed $rawIngredients): self
    {
        $ingredients = [];

        if (is_array($rawIngredients)) {
            foreach ($rawIngredients as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $itemId = filter_var($row['item_id'] ?? null, FILTER_VALIDATE_INT);
                $grams = filter_var($row['grams'] ?? null, FILTER_VALIDATE_FLOAT);

                if ($itemId !== false && $itemId > 0 && $grams !== false && $grams > 0) {
                    $ingredients[] = ['item_id' => $itemId, 'grams' => $grams];
                }
            }
        }

        return new self(
            recipeId: $recipeId,
            name: is_string($name) ? $name : '',
            cookedWeight: is_string($cookedWeight) ? $cookedWeight : '',
            ingredients: $ingredients,
        );
    }

    /**
     * Read a draft back out of the session array, or null when there is none.
     *
     * @param  mixed  $stored  whatever was in the session under the draft key
     */
    public static function fromSession(mixed $stored): ?self
    {
        if (! is_array($stored)) {
            return null;
        }

        $ingredients = [];
        foreach ($stored['ingredients'] ?? [] as $row) {
            if (is_array($row) && isset($row['item_id'], $row['grams'])) {
                $ingredients[] = ['item_id' => (int) $row['item_id'], 'grams' => (float) $row['grams']];
            }
        }

        return new self(
            recipeId: isset($stored['recipe_id']) ? (int) $stored['recipe_id'] : null,
            name: is_string($stored['name'] ?? null) ? $stored['name'] : '',
            cookedWeight: is_string($stored['cooked_weight_g'] ?? null) ? $stored['cooked_weight_g'] : '',
            ingredients: $ingredients,
        );
    }

    /**
     * The same draft with one more ingredient row appended.
     */
    public function withIngredient(int $itemId, float $grams): self
    {
        return new self(
            recipeId: $this->recipeId,
            name: $this->name,
            cookedWeight: $this->cookedWeight,
            ingredients: [...$this->ingredients, ['item_id' => $itemId, 'grams' => $grams]],
        );
    }

    /**
     * True when this draft belongs to the recipe form being asked for — a new
     * recipe when both are null, or the same recipe id when editing. Stops a
     * draft left from building one recipe leaking into the form for another.
     */
    public function belongsTo(?int $recipeId): bool
    {
        return $this->recipeId === $recipeId;
    }

    /**
     * The rows in the shape the form renders them, so a hydrated form draws
     * exactly what was assembled.
     *
     * @return list<array{item_id: int, grams: float}>
     */
    public function formRows(): array
    {
        return $this->ingredients;
    }

    /**
     * @return array{recipe_id: int|null, name: string, cooked_weight_g: string, ingredients: list<array{item_id: int, grams: float}>}
     */
    public function toArray(): array
    {
        return [
            'recipe_id' => $this->recipeId,
            'name' => $this->name,
            'cooked_weight_g' => $this->cookedWeight,
            'ingredients' => $this->ingredients,
        ];
    }
}
