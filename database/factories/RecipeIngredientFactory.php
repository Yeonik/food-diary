<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FoodItem;
use App\Models\RecipeIngredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeIngredient>
 */
class RecipeIngredientFactory extends Factory
{
    protected $model = RecipeIngredient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => FoodItem::factory()->recipe(),
            'ingredient_id' => FoodItem::factory(),
            'grams' => fake()->randomFloat(1, 10, 500),
        ];
    }
}
