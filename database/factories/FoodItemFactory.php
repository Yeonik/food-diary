<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FoodItem;
use App\Models\User;
use App\Nutrition\FoodItemKind;
use App\Nutrition\ProfileOrigin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoodItem>
 */
class FoodItemFactory extends Factory
{
    protected $model = FoodItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Whoever is signed in owns it; with nobody signed in the factory
            // makes an account rather than leaving a record with no owner.
            'user_id' => auth()->id() ?? User::factory(),
            'name' => fake()->unique()->words(2, true),
            'alt_name' => null,
            'kind' => FoodItemKind::Direct->value,
            'origin' => ProfileOrigin::Manual->value,
            'external_id' => null,
            'kcal_per_100g' => fake()->randomFloat(1, 20, 400),
            'protein_g_per_100g' => fake()->randomFloat(1, 0, 30),
            'fat_g_per_100g' => fake()->randomFloat(1, 0, 30),
            'carbs_g_per_100g' => fake()->randomFloat(1, 0, 60),
        ];
    }

    /**
     * A direct item with an exact, given per-100 g profile — handy when a test
     * needs to check arithmetic against known numbers.
     */
    public function direct(float $kcal, float $protein, float $fat, float $carbs): self
    {
        return $this->state(fn (): array => [
            'kind' => FoodItemKind::Direct->value,
            'origin' => ProfileOrigin::Manual->value,
            'kcal_per_100g' => $kcal,
            'protein_g_per_100g' => $protein,
            'fat_g_per_100g' => $fat,
            'carbs_g_per_100g' => $carbs,
        ]);
    }

    /**
     * A recipe: no stored profile, its numbers come from ingredient rows.
     */
    public function recipe(): self
    {
        return $this->state(fn (): array => [
            'kind' => FoodItemKind::Recipe->value,
            'origin' => null,
            'kcal_per_100g' => null,
            'protein_g_per_100g' => null,
            'fat_g_per_100g' => null,
            'carbs_g_per_100g' => null,
        ]);
    }
}
