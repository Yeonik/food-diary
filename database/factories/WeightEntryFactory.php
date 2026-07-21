<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WeightEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeightEntry>
 */
class WeightEntryFactory extends Factory
{
    protected $model = WeightEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recorded_on' => fake()->unique()->dateTimeBetween('-3 months')->format('Y-m-d'),
            'weight_kg' => fake()->randomFloat(1, 50, 100),
        ];
    }
}
