<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FoodItem;
use App\Models\RecipeIngredient;
use App\Models\User;
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
            // Whoever is signed in owns it; with nobody signed in the factory
            // makes an account rather than leaving a record with no owner.
            'user_id' => auth()->id() ?? User::factory(),
            'recipe_id' => FoodItem::factory()->recipe(),
            'ingredient_id' => FoodItem::factory(),
            'grams' => fake()->randomFloat(1, 10, 500),
        ];
    }
}
