<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MealEntry;
use App\Nutrition\MealType;
use App\Nutrition\NutrientSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The custom range, end to end through the controller: only entries that fall
 * inside the two chosen dates are aggregated, and the average divides by the
 * logged days within it.
 */
class HistoryRangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    public function test_a_custom_range_aggregates_only_entries_within_it(): void
    {
        MealEntry::factory()->create([
            'logged_at' => '2026-06-05 12:00:00',
            'meal' => MealType::Lunch,
            'kcal' => 400,
            'protein_g' => 0,
            'fat_g' => 0,
            'carbs_g' => 0,
            'source' => NutrientSource::Manual,
        ]);
        MealEntry::factory()->create([
            'logged_at' => '2026-06-20 12:00:00',
            'meal' => MealType::Lunch,
            'kcal' => 999,
            'protein_g' => 0,
            'fat_g' => 0,
            'carbs_g' => 0,
            'source' => NutrientSource::Manual,
        ]);

        $this->get(route('history.index', ['range' => 'range', 'from' => '2026-06-01', 'to' => '2026-06-10']))
            ->assertOk()
            ->assertSee('400')      // the in-range day drives the average (400 / 1 logged day)
            ->assertDontSee('999'); // the entry outside the range is not counted
    }
}
