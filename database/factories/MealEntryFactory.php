<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MealEntry;
use App\Models\User;
use App\Nutrition\MealType;
use App\Nutrition\NutrientSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealEntry>
 */
class MealEntryFactory extends Factory
{
    protected $model = MealEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Whoever is signed in owns it; with nobody signed in the factory
            // makes an account rather than leaving a record with no owner.
            'user_id' => auth()->id() ?? User::factory(),
            'logged_at' => fake()->dateTimeBetween('-1 month'),
            'meal' => fake()->randomElement(MealType::cases())->value,
            'name' => fake()->words(2, true),
            'grams' => fake()->randomFloat(1, 20, 400),
            'kcal' => fake()->randomFloat(1, 20, 800),
            'protein_g' => fake()->randomFloat(1, 0, 60),
            'fat_g' => fake()->randomFloat(1, 0, 60),
            'carbs_g' => fake()->randomFloat(1, 0, 120),
            'source' => NutrientSource::PersonalLibrary->value,
            'food_item_id' => null,
        ];
    }
}
