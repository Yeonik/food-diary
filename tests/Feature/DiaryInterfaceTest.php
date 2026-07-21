<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\MealEntry;
use App\Nutrition\MealType;
use App\Nutrition\NutrientSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiaryInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaining_is_shown_with_a_goal_and_hidden_without_one(): void
    {
        MealEntry::factory()->create([
            'logged_at' => now(),
            'meal' => MealType::Lunch->value,
            'kcal' => 750,
            'source' => NutrientSource::PersonalLibrary->value,
        ]);

        // No goal: no "Remaining" at all.
        $this->get(route('diary.index'))->assertOk()->assertDontSee('Remaining');

        Goal::factory()->create(['daily_kcal' => 2000]);

        // With a goal: remaining = 2000 − 750 = 1,250, shown as a plain number.
        $this->get(route('diary.index'))->assertOk()->assertSee('Remaining')->assertSee('1,250');
    }

    public function test_an_estimate_is_marked_unverified_in_the_diary(): void
    {
        MealEntry::factory()->create([
            'logged_at' => now(),
            'name' => 'Home stew',
            'source' => NutrientSource::Estimated->value,
        ]);

        $this->get(route('diary.index'))->assertOk()->assertSee('unverified');
    }

    public function test_an_entry_can_be_deleted(): void
    {
        $entry = MealEntry::factory()->create(['logged_at' => now()]);

        $this->delete(route('entries.destroy', $entry))->assertRedirect();

        $this->assertModelMissing($entry);
    }

    public function test_a_weight_reading_is_recorded_and_replaced_per_day(): void
    {
        $this->post(route('weight.store'), ['recorded_on' => '2026-06-01', 'weight_kg' => 80])->assertRedirect();
        $this->get(route('weight.index'))->assertOk()->assertSee('80');

        // A second reading for the same day replaces the first.
        $this->post(route('weight.store'), ['recorded_on' => '2026-06-01', 'weight_kg' => 81])->assertRedirect();

        $this->assertDatabaseCount('weight_entries', 1);
        $this->get(route('weight.index'))->assertSee('81');
    }
}
