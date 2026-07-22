<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MealEntry;
use App\Nutrition\MealType;
use App\Nutrition\NutrientSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The average per day divides by the days that were logged, not by the length
 * of the range. Three logged days out of a seven-day window divide by three —
 * empty days are "not logged", not "ate zero", and must not water the average
 * down with invented zeros.
 */
class HistoryAverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_average_divides_by_days_with_entries_not_the_whole_range(): void
    {
        foreach ([0, 1, 2] as $daysAgo) {
            MealEntry::factory()->create([
                'logged_at' => now()->subDays($daysAgo),
                'meal' => MealType::Breakfast,
                'kcal' => 300,
                'grams' => 100,
                'protein_g' => 0,
                'fat_g' => 0,
                'carbs_g' => 0,
                'source' => NutrientSource::Manual,
            ]);
        }

        // 900 kcal over 3 logged days of a 7-day window: average is 900 / 3 = 300,
        // never 900 / 7 = 129.
        $response = $this->get(route('history.index', ['range' => 'week']));

        $response->assertOk()
            ->assertSee('300')
            ->assertDontSee('129');
    }
}
