<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Goal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    protected $model = Goal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'daily_kcal' => 2000.0,
            'protein_g' => 120.0,
            'fat_g' => 70.0,
            'carbs_g' => 220.0,
        ];
    }
}
