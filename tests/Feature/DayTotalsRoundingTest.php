<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MealEntry;
use App\Nutrition\MealType;
use App\Nutrition\NutrientSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the screen adds up must add up. Each entry is shown as a whole number,
 * so the meal subtotal and the day total are the sum of those rounded numbers —
 * not the exact sum rounded once, which can leave the visible figures failing
 * to reconcile (118 + 128 shown, subtotal 245).
 */
class DayTotalsRoundingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    public function test_displayed_entries_add_up_to_the_subtotal_and_the_day_total(): void
    {
        // Fractions chosen so exact-then-round disagrees with round-then-sum:
        //   dinner  117.5 + 127.5 -> shown 118 + 128 = 246 (exact sum 245.0 -> 245)
        //   + breakfast 100.5     -> shown 101; day total 347 (exact 345.5 -> 346)
        $make = fn (MealType $meal, float $kcal) => MealEntry::factory()->create([
            'logged_at' => now(),
            'meal' => $meal,
            'kcal' => $kcal,
            'grams' => 100,
            'protein_g' => 0,
            'fat_g' => 0,
            'carbs_g' => 0,
            'source' => NutrientSource::PersonalLibrary,
        ]);

        $make(MealType::Breakfast, 100.5);
        $make(MealType::Dinner, 117.5);
        $make(MealType::Dinner, 127.5);

        $response = $this->get('/');

        $response->assertOk();
        // The rounded entries the user reads.
        $response->assertSee('101')->assertSee('118')->assertSee('128');
        // Meal subtotal is their sum, and the day total is the sum of subtotals.
        $response->assertSee('246')->assertSee('347');
        // Never the exact-then-round figures that would not reconcile on screen.
        $response->assertDontSee('245')->assertDontSee('346');
    }
}
