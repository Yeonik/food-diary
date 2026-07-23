<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Goal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The settings form drives the goal switch and the meal-visibility toggles. An
 * enabled goal stores its target; a disabled one stores no target at all; an
 * unchecked meal is hidden (its flag false) without touching any entry.
 */
class GoalSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    public function test_enabling_the_goal_stores_the_target_and_meal_toggles(): void
    {
        // Snack omitted from the payload — an unchecked box is simply absent.
        $this->patch(route('goal.update'), [
            'goal_enabled' => '1',
            'daily_kcal' => 1800,
            'show_breakfast' => '1',
            'show_lunch' => '1',
            'show_dinner' => '1',
        ])->assertRedirect();

        $goal = Goal::query()->latest('id')->firstOrFail();
        $this->assertSame(1800.0, $goal->daily_kcal);
        $this->assertTrue($goal->show_breakfast);
        $this->assertFalse($goal->show_snack);
    }

    public function test_disabling_the_goal_clears_the_target(): void
    {
        Goal::factory()->create(['daily_kcal' => 2000]);

        // No goal_enabled key: the switch is off, even though a number is posted.
        $this->patch(route('goal.update'), ['daily_kcal' => 2000])->assertRedirect();

        $this->assertNull(Goal::query()->latest('id')->firstOrFail()->daily_kcal);
    }
}
