<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\RecipeIngredientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a recipe: an ingredient item and its weight in grams. The
 * ingredient may itself be a recipe.
 *
 * @property int $id
 * @property int $recipe_id
 * @property int $ingredient_id
 * @property float $grams
 */
class RecipeIngredient extends Model
{
    /** @use HasFactory<RecipeIngredientFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'recipe_id',
        'ingredient_id',
        'grams',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grams' => 'float',
        ];
    }

    /**
     * @return BelongsTo<FoodItem, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(FoodItem::class, 'recipe_id');
    }

    /**
     * @return BelongsTo<FoodItem, $this>
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(FoodItem::class, 'ingredient_id');
    }
}
