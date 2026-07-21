<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\MealEntry;
use App\Nutrition\DailyTotals;
use App\Nutrition\NutrientSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sums_the_days_entries(): void
    {
        $entries = MealEntry::factory()->makeMany([
            ['kcal' => 300, 'protein_g' => 20, 'fat_g' => 10, 'carbs_g' => 30],
            ['kcal' => 500, 'protein_g' => 25, 'fat_g' => 15, 'carbs_g' => 60],
        ]);

        $summary = (new DailyTotals)->summarise($entries, null);

        $this->assertSame(800.0, $summary->kcal);
        $this->assertSame(45.0, $summary->proteinG);
    }

    public function test_remaining_is_target_minus_logged_when_a_goal_exists(): void
    {
        $goal = Goal::factory()->make(['daily_kcal' => 2000]);
        $entries = MealEntry::factory()->makeMany([['kcal' => 750]]);

        $summary = (new DailyTotals)->summarise($entries, $goal);

        $this->assertSame(1250.0, $summary->remainingKcal);
        $this->assertTrue($summary->hasGoal());
    }

    public function test_no_goal_means_no_remaining_is_shown(): void
    {
        $entries = MealEntry::factory()->makeMany([['kcal' => 750]]);

        $summary = (new DailyTotals)->summarise($entries, null);

        $this->assertNull($summary->remainingKcal);
        $this->assertFalse($summary->hasGoal());
    }

    public function test_it_flags_when_the_day_contains_an_estimate(): void
    {
        $entries = MealEntry::factory()->makeMany([
            ['kcal' => 300, 'source' => NutrientSource::PersonalLibrary->value],
            ['kcal' => 200, 'source' => NutrientSource::Estimated->value],
        ]);

        $summary = (new DailyTotals)->summarise($entries, null);

        $this->assertTrue($summary->hasEstimates);
    }
}
