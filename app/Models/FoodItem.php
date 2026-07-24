<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Nutrition\FoodItemKind;
use App\Nutrition\NutrientProfile;
use App\Nutrition\NutrientSource;
use App\Nutrition\ProfileOrigin;
use Database\Factories\FoodItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * A personal-library item: either a direct nutrient profile or a recipe.
 *
 * @property int $id
 * @property string $name
 * @property string|null $alt_name
 * @property FoodItemKind $kind
 * @property ProfileOrigin|null $origin
 * @property string|null $external_id
 * @property float|null $kcal_per_100g
 * @property float|null $protein_g_per_100g
 * @property float|null $fat_g_per_100g
 * @property float|null $carbs_g_per_100g
 * @property float|null $cooked_weight_g
 */
class FoodItem extends Model
{
    /** @use HasFactory<FoodItemFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'alt_name',
        'kind',
        'origin',
        'external_id',
        'kcal_per_100g',
        'protein_g_per_100g',
        'fat_g_per_100g',
        'carbs_g_per_100g',
        'cooked_weight_g',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => FoodItemKind::class,
            'origin' => ProfileOrigin::class,
            'kcal_per_100g' => 'float',
            'protein_g_per_100g' => 'float',
            'fat_g_per_100g' => 'float',
            'carbs_g_per_100g' => 'float',
            'cooked_weight_g' => 'float',
        ];
    }

    /**
     * The ingredient rows that make up this item, when it is a recipe.
     *
     * @return HasMany<RecipeIngredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class, 'recipe_id');
    }

    /**
     * Names this item has also been recognised by — accumulated on confirmation
     * so future lookups match the ways the model actually phrases the product.
     *
     * @return HasMany<FoodItemAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(FoodItemAlias::class);
    }

    public function isRecipe(): bool
    {
        return $this->kind === FoodItemKind::Recipe;
    }

    /**
     * A recipe with no cooked weight cannot be turned into a number.
     *
     * This is the one thing this weight is for: without it the divisor is
     * missing, so the recipe has no per-100 g profile to offer and must not be
     * treated as if it did — not surfaced as a candidate, not stamped verified.
     * The interface reads this to say so, and RecipeCalculator reads it to
     * refuse rather than fall back to the sum of the raw ingredients, which is
     * the wrong divisor this whole change exists to stop using.
     *
     * It says nothing about a recipe's ingredients referring to another recipe
     * that is itself incomplete — that only surfaces when the profile is
     * computed, so it lives there, not here.
     */
    public function needsCookedWeight(): bool
    {
        return $this->isRecipe() && $this->cooked_weight_g === null;
    }

    /**
     * The stored per-100 g profile of a direct item, tagged as coming from the
     * personal library (its resolution tier). Recipes have no stored profile —
     * theirs is computed by RecipeCalculator — so asking here is a programming
     * error rather than a runtime condition to handle.
     */
    public function storedProfile(): NutrientProfile
    {
        if ($this->isRecipe()) {
            throw new RuntimeException('A recipe has no stored profile; use RecipeCalculator.');
        }

        if ($this->kcal_per_100g === null
            || $this->protein_g_per_100g === null
            || $this->fat_g_per_100g === null
            || $this->carbs_g_per_100g === null) {
            throw new RuntimeException("Direct item {$this->id} is missing part of its profile.");
        }

        return new NutrientProfile(
            kcal: $this->kcal_per_100g,
            proteinG: $this->protein_g_per_100g,
            fatG: $this->fat_g_per_100g,
            carbsG: $this->carbs_g_per_100g,
            source: NutrientSource::PersonalLibrary,
        );
    }
}
