<?php

declare(strict_types=1);

namespace App\Models;

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
 * @property FoodItemKind $kind
 * @property ProfileOrigin|null $origin
 * @property string|null $external_id
 * @property float|null $kcal_per_100g
 * @property float|null $protein_g_per_100g
 * @property float|null $fat_g_per_100g
 * @property float|null $carbs_g_per_100g
 */
class FoodItem extends Model
{
    /** @use HasFactory<FoodItemFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'kind',
        'origin',
        'external_id',
        'kcal_per_100g',
        'protein_g_per_100g',
        'fat_g_per_100g',
        'carbs_g_per_100g',
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

    public function isRecipe(): bool
    {
        return $this->kind === FoodItemKind::Recipe;
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
