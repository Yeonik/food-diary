<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\MealEntry;
use App\Nutrition\MealType;
use App\Nutrition\NutrientSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Turning a meal off is presentation only. Its section disappears from the Day
 * screen, but nothing is deleted and the calories logged in it still count
 * towards the day's total — hiding must never quietly change the arithmetic.
 */
class MealVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    public function test_a_hidden_meal_is_not_shown_but_still_counts_in_the_day_total(): void
    {
        Goal::factory()->create(['daily_kcal' => null, 'show_snack' => false]);

        MealEntry::factory()->create([
            'logged_at' => now(),
            'meal' => MealType::Breakfast,
            'name' => 'Morning oats',
            'kcal' => 200,
            'source' => NutrientSource::Manual,
        ]);
        MealEntry::factory()->create([
            'logged_at' => now(),
            'meal' => MealType::Snack,
            'name' => 'Late cottage cheese',
            'kcal' => 111,
            'source' => NutrientSource::Manual,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        // The hidden meal and its entry are gone from the screen...
        $response->assertDontSee('Late cottage cheese');
        // ...the visible meal's entry is still there...
        $response->assertSee('Morning oats');
        // ...and the day total still includes the hidden meal (200 + 111 = 311).
        $response->assertSee('311');
    }
}
